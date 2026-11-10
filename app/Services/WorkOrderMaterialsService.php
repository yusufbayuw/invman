<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketTeamMember;
use App\Models\User;
use App\Models\WorkOrderMaterialLine;
use App\Models\WorkOrderMaterialOrder;
use App\Models\WorkOrderMaterialReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Work-order-specific material ledger. Manually confirmed stock is NOT
 * global inventory stock and must never mutate borrowable asset quantities.
 * All writes serialize on the ticket and line to prevent over-issuing.
 */
final class WorkOrderMaterialsService
{
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['material' => $message]);
    }

    private function milli(mixed $value, bool $allowZero = false): int
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            $this->reject('Jumlah material tidak valid.');
        }
        $value = (string) $value;
        if (! preg_match('/^(0|[1-9][0-9]{0,5})(\.[0-9]{1,3})?$/D', $value)) {
            $this->reject('Jumlah material maksimal tiga angka desimal dan 999.999.');
        }
        $milli = (int) round((float) $value * 1000);
        if ($milli < 0 || (! $allowZero && $milli === 0)) {
            $this->reject('Jumlah material harus lebih besar dari nol.');
        }
        return $milli;
    }

    private function decimal(int $milli): string
    {
        return number_format($milli / 1000, 3, '.', '');
    }

    private function audit(Ticket $ticket, User $user, string $action, string $notes): void
    {
        $ticket->events()->create([
            'actor_id' => $user->id, 'action' => $action, 'notes' => $notes,
        ]);
    }

    private function authorized(Ticket $ticket, User $user, bool $manager = false): void
    {
        $fresh = User::query()->find($user->id);
        if (! $fresh?->is_enabled || ! $ticket->maintenance
            || ! in_array($ticket->status, ['triaged', 'in_progress', 'waiting_parts'], true)
            || ($ticket->handlingPlan && $ticket->handlingPlan->decision !== 'repair')) {
            $this->reject('Tahap tiket atau kewenangan tidak sesuai untuk pemenuhan material.');
        }
        if (app(WorkOrderWorkflowService::class)->canPerform($ticket, $fresh, 'assign')) {
            return;
        }
        if (! $manager && $ticket->assigned_to === $fresh->id
            && TicketTeamMember::qualifiesFor($ticket, $fresh)) {
            return;
        }
        $this->reject('Hanya petugas berwenang yang dapat mengelola material tiket ini.');
    }

    private function lineForWrite(WorkOrderMaterialLine $line): array
    {
        $ticket = Ticket::query()->lockForUpdate()->findOrFail($line->ticket_id);
        $locked = WorkOrderMaterialLine::query()->where('ticket_id', $ticket->id)
            ->lockForUpdate()->findOrFail($line->id);
        return [$ticket, $locked];
    }

    /** @return array{ready:int,issued:int,needed:int,committed:int,shortage:int} */
    private function totals(WorkOrderMaterialLine $line): array
    {
        $accepted = 0;
        $committed = 0;
        foreach ($line->orders()->with('receipts')->get() as $order) {
            foreach ($order->receipts as $receipt) {
                if ($receipt->inspected_at) {
                    $accepted += $this->milli($receipt->accepted_quantity, true);
                }
            }
            if (in_array($order->status, ['requested', 'approved', 'ordered'], true)) {
                $delivered = 0;
                foreach ($order->receipts as $receipt) {
                    if ($receipt->inspected_at) {
                        $delivered += $this->milli($receipt->quantity, true);
                    }
                }
                $committed += max(0, $this->milli($order->quantity) - $delivered);
            }
        }
        $required = $this->milli($line->required_quantity);
        $ready = $this->milli($line->stock_allocated_quantity, true) + $accepted;
        return [
            'ready' => $ready, 'issued' => $this->milli($line->issued_quantity, true),
            'needed' => $required, 'committed' => $committed,
            'shortage' => max(0, $required - $ready - $committed),
        ];
    }

    /** @return array{required:string,ready:string,issued:string,outstanding:string,shortage:string} */
    public function summary(WorkOrderMaterialLine $line): array
    {
        $t = $this->totals($line);
        return [
            'required' => $this->decimal($t['needed']),
            'ready' => $this->decimal($t['ready']),
            'issued' => $this->decimal($t['issued']),
            'outstanding' => $this->decimal($t['committed']),
            'shortage' => $this->decimal($t['shortage']),
        ];
    }

    public function addRequirement(Ticket $ticket, User $user, string $name, string $unit, mixed $quantity): WorkOrderMaterialLine
    {
        $name = trim($name);
        $unit = trim($unit);
        $qty = $this->milli($quantity);
        if ($name === '' || mb_strlen($name) > 200 || $unit === '' || mb_strlen($unit) > 25) {
            $this->reject('Nama material dan satuan wajib diisi.');
        }
        return DB::transaction(function () use ($ticket, $user, $name, $unit, $qty): WorkOrderMaterialLine {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->authorized($locked, $user);
            if ($locked->workOrderMaterials()->count() >= 30) {
                $this->reject('Satu pekerjaan maksimal 30 kebutuhan material.');
            }
            $line = $locked->workOrderMaterials()->create([
                'name' => $name, 'unit' => $unit,
                'required_quantity' => $this->decimal($qty), 'created_by' => $user->id,
            ]);
            $this->audit($locked, $user, 'work_order_material_required',
                $name.' '.$line->required_quantity.' '.$unit);
            return $line;
        }, 3);
    }

    public function allocateStock(WorkOrderMaterialLine $line, User $user, mixed $quantity): void
    {
        $qty = $this->milli($quantity);
        DB::transaction(function () use ($line, $user, $qty): void {
            [$ticket, $locked] = $this->lineForWrite($line);
            $this->authorized($ticket, $user, true);
            $totals = $this->totals($locked);
            if ($qty > $totals['shortage']) {
                $this->reject('Alokasi stok melebihi kebutuhan yang belum dipenuhi atau sudah dipesan.');
            }
            $locked->forceFill([
                'stock_allocated_quantity' => $this->decimal(
                    $this->milli($locked->stock_allocated_quantity, true) + $qty),
                'stock_confirmed_by' => $user->id, 'stock_confirmed_at' => now(),
            ])->save();
            $this->audit($ticket, $user, 'work_order_material_stock_allocated',
                $locked->name.' '.$this->decimal($qty).' '.$locked->unit.' (dikonfirmasi manual)');
        }, 3);
    }

    public function requestPurchase(WorkOrderMaterialLine $line, User $user, mixed $quantity, string $reason): WorkOrderMaterialOrder
    {
        $qty = $this->milli($quantity);
        if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 2000) {
            $this->reject('Alasan pembelian minimal lima karakter.');
        }
        return DB::transaction(function () use ($line, $user, $qty, $reason): WorkOrderMaterialOrder {
            [$ticket, $locked] = $this->lineForWrite($line);
            $this->authorized($ticket, $user);
            if ($qty > $this->totals($locked)['shortage']) {
                $this->reject('Permintaan melebihi kekurangan setelah memperhitungkan stok dan pembelian aktif.');
            }
            $order = $locked->orders()->create([
                'quantity' => $this->decimal($qty), 'status' => 'requested',
                'reason' => trim($reason), 'requested_by' => $user->id,
            ]);
            $this->audit($ticket, $user, 'work_order_material_purchase_requested',
                'Permintaan #'.$order->id.': '.$locked->name.' '.$order->quantity.' '.$locked->unit);
            return $order;
        }, 3);
    }

    public function approvePurchase(WorkOrderMaterialOrder $order, User $user, bool $approve, string $notes): void
    {
        if (mb_strlen(trim($notes)) < 5 || mb_strlen($notes) > 2000) {
            $this->reject('Catatan keputusan pembelian minimal lima karakter.');
        }
        DB::transaction(function () use ($order, $user, $approve, $notes): void {
            [$ticket, $line] = $this->lineForWrite($order->line);
            $this->authorized($ticket, $user, true);
            $locked = $line->orders()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'requested' || $locked->requested_by === $user->id) {
                $this->reject('Permintaan tidak aktif atau penyetuju sama dengan pemohon.');
            }
            $locked->forceFill([
                'status' => $approve ? 'approved' : 'rejected',
                'approved_by' => $user->id, 'approved_at' => now(),
            ])->save();
            $this->audit($ticket, $user, 'work_order_material_purchase_decided',
                'Permintaan #'.$locked->id.' '.($approve ? 'disetujui' : 'ditolak').': '.trim($notes));
        }, 3);
    }

    public function placeOrder(WorkOrderMaterialOrder $order, User $user, string $vendor, string $reference, mixed $amount): void
    {
        $vendor = trim($vendor);
        $reference = trim($reference);
        if (mb_strlen($vendor) < 2 || mb_strlen($vendor) > 200 || mb_strlen($reference) < 3
            || mb_strlen($reference) > 200 || ! preg_match('/^(0|[1-9][0-9]{0,11})$/D', (string) $amount)) {
            $this->reject('Pemasok, referensi pesanan, dan nominal rupiah harus valid.');
        }
        DB::transaction(function () use ($order, $user, $vendor, $reference, $amount): void {
            [$ticket, $line] = $this->lineForWrite($order->line);
            $this->authorized($ticket, $user, true);
            $locked = $line->orders()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'approved' || ! $locked->approved_at) {
                $this->reject('Hanya permintaan yang disetujui dapat dibuatkan pembelian.');
            }
            $locked->forceFill([
                'status' => 'ordered', 'vendor_name' => $vendor,
                'order_reference' => $reference, 'amount' => (string) $amount,
                'ordered_by' => $user->id, 'ordered_at' => now(),
            ])->save();
            $this->audit($ticket, $user, 'work_order_material_ordered',
                'Pesanan #'.$locked->id.': '.$reference.' / '.$vendor.' / Rp '.$amount);
        }, 3);
    }

    public function receive(WorkOrderMaterialOrder $order, User $user, mixed $quantity, string $reference): WorkOrderMaterialReceipt
    {
        $qty = $this->milli($quantity);
        if (mb_strlen(trim($reference)) < 3 || mb_strlen($reference) > 200) {
            $this->reject('Referensi penerimaan barang wajib diisi.');
        }
        return DB::transaction(function () use ($order, $user, $qty, $reference): WorkOrderMaterialReceipt {
            [$ticket, $line] = $this->lineForWrite($order->line);
            $this->authorized($ticket, $user, true);
            $locked = $line->orders()->lockForUpdate()->findOrFail($order->id);
            $received = 0;
            foreach ($locked->receipts()->lockForUpdate()->get() as $receipt) {
                $received += $this->milli($receipt->quantity);
            }
            if ($locked->status !== 'ordered' || $qty + $received > $this->milli($locked->quantity)) {
                $this->reject('Penerimaan tidak sesuai jumlah pesanan yang belum diterima.');
            }
            $receipt = $locked->receipts()->create([
                'quantity' => $this->decimal($qty), 'reference' => trim($reference),
                'status' => 'pending', 'received_by' => $user->id,
            ]);
            $this->audit($ticket, $user, 'work_order_material_received',
                'Penerimaan #'.$receipt->id.' dari pesanan #'.$locked->id.'; menunggu pemeriksaan.');
            return $receipt;
        }, 3);
    }

    public function inspect(WorkOrderMaterialReceipt $receipt, User $user, mixed $accepted, string $notes): void
    {
        $qty = $this->milli($accepted, true);
        if (mb_strlen(trim($notes)) < 5 || mb_strlen($notes) > 2000) {
            $this->reject('Hasil pemeriksaan dan alasan minimal lima karakter.');
        }
        DB::transaction(function () use ($receipt, $user, $qty, $notes): void {
            [$ticket, $line] = $this->lineForWrite($receipt->order->line);
            $this->authorized($ticket, $user, true);
            $order = $line->orders()->lockForUpdate()->findOrFail($receipt->work_order_material_order_id);
            $locked = $order->receipts()->lockForUpdate()->findOrFail($receipt->id);
            if ($locked->status !== 'pending' || $locked->received_by === $user->id
                || $qty > $this->milli($locked->quantity)) {
                $this->reject('Pemeriksa harus berbeda dari penerima dan jumlah diterima tidak boleh melebihi kiriman.');
            }
            $locked->forceFill([
                'status' => $qty === 0 ? 'rejected'
                    : ($qty === $this->milli($locked->quantity) ? 'accepted' : 'partial'),
                'accepted_quantity' => $this->decimal($qty), 'inspection_notes' => trim($notes),
                'inspected_by' => $user->id, 'inspected_at' => now(),
            ])->save();
            $this->audit($ticket, $user, 'work_order_material_inspected',
                'QC #'.$locked->id.': diterima '.$this->decimal($qty).' dari '.$locked->quantity.
                ' '.$line->unit.'; '.trim($notes));
        }, 3);
    }

    public function issue(WorkOrderMaterialLine $line, User $user, mixed $quantity): void
    {
        $qty = $this->milli($quantity);
        DB::transaction(function () use ($line, $user, $qty): void {
            [$ticket, $locked] = $this->lineForWrite($line);
            $this->authorized($ticket, $user, true);
            $totals = $this->totals($locked);
            if ($totals['issued'] + $qty > min($totals['needed'], $totals['ready'])) {
                $this->reject('Material tidak dapat diserahkan melebihi jumlah siap pakai dan kebutuhan.');
            }
            $locked->forceFill(['issued_quantity' => $this->decimal($totals['issued'] + $qty)])->save();
            $this->audit($ticket, $user, 'work_order_material_issued',
                $locked->name.' '.$this->decimal($qty).' '.$locked->unit.' diserahkan untuk pekerjaan.');
        }, 3);
    }

    public function assertAllIssued(Ticket $ticket): void
    {
        foreach ($ticket->workOrderMaterials()->get() as $line) {
            if ($this->milli($line->issued_quantity, true) < $this->milli($line->required_quantity)) {
                $this->reject('Material wajib belum seluruhnya diserahkan: '.$line->name.'.');
            }
        }
    }

    public function allIssued(Ticket $ticket): bool
    {
        foreach ($ticket->workOrderMaterials()->get() as $line) {
            if ($this->milli($line->issued_quantity, true) < $this->milli($line->required_quantity)) {
                return false;
            }
        }
        return true;
    }
}
