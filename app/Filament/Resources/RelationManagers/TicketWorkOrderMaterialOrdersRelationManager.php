<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\WorkOrderMaterialOrder;
use App\Models\WorkOrderMaterialReceipt;
use App\Services\WorkOrderMaterialsService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TicketWorkOrderMaterialOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'materialPurchaseOrders';
    protected static ?string $title = 'Pembelian dan Pemeriksaan Material';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Ticket && $ownerRecord->maintenance !== null
            && TicketResource::canView($ownerRecord);
    }

    public function mount(): void
    {
        abort_unless(static::canViewForRecord($this->getOwnerRecord(), $this->getPageClass()), 403);
        parent::mount();
    }

    private function mayManage(): bool
    {
        return app(WorkOrderMaterialsService::class)->mayManage($this->getOwnerRecord(), auth()->user(), true);
    }

    public function table(Table $table): Table
    {
        return $table->description(
            'Penerimaan tidak otomatis menjadi stok siap pakai. Pemeriksa harus berbeda dari petugas penerima.'
        )->columns([
            Tables\Columns\TextColumn::make('id')->label('Permintaan')->sortable(),
            Tables\Columns\TextColumn::make('line.name')->label('Material'),
            Tables\Columns\TextColumn::make('quantity')->label('Jumlah')->numeric(decimalPlaces: 3)
                ->description(fn (WorkOrderMaterialOrder $record) => $record->line?->unit),
            Tables\Columns\TextColumn::make('status')->label('Tahap')->badge()
                ->formatStateUsing(fn (string $state) => match ($state) {
                    'requested' => 'Menunggu persetujuan',
                    'approved' => 'Disetujui',
                    'ordered' => 'Dipesan',
                    'rejected' => 'Ditolak',
                    default => $state,
                }),
            Tables\Columns\TextColumn::make('vendor_name')->label('Pemasok')->placeholder('-'),
            Tables\Columns\TextColumn::make('order_reference')->label('Referensi')->placeholder('-'),
            Tables\Columns\TextColumn::make('receipts_count')->counts('receipts')->label('Kiriman'),
        ])->actions([
            Tables\Actions\Action::make('approve_purchase')->label('Setujui')
                ->icon('heroicon-o-check')->color('success')
                ->visible(fn (WorkOrderMaterialOrder $record): bool => $this->mayManage()
                    && $record->status === 'requested' && $record->requested_by !== auth()->id())
                ->form([
                    Forms\Components\Textarea::make('notes')->label('Catatan')->required()->minLength(5),
                ])
                ->action(fn (WorkOrderMaterialOrder $record, array $data) =>
                    app(WorkOrderMaterialsService::class)->approvePurchase($record, auth()->user(), true, $data['notes'])),
            Tables\Actions\Action::make('reject_purchase')->label('Tolak')
                ->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn (WorkOrderMaterialOrder $record): bool => $this->mayManage()
                    && $record->status === 'requested' && $record->requested_by !== auth()->id())
                ->form([
                    Forms\Components\Textarea::make('notes')->label('Alasan penolakan')->required()->minLength(5),
                ])
                ->action(fn (WorkOrderMaterialOrder $record, array $data) =>
                    app(WorkOrderMaterialsService::class)->approvePurchase($record, auth()->user(), false, $data['notes'])),
            Tables\Actions\Action::make('place_order')->label('Catat Pembelian')
                ->icon('heroicon-o-shopping-bag')->color('primary')
                ->visible(fn (WorkOrderMaterialOrder $record): bool => $this->mayManage()
                    && $record->status === 'approved')
                ->form([
                    Forms\Components\TextInput::make('vendor')->label('Pemasok')->required()->maxLength(200),
                    Forms\Components\TextInput::make('reference')->label('Referensi pesanan/nota')->required()->maxLength(200),
                    Forms\Components\TextInput::make('amount')->label('Nominal rupiah')
                        ->required()->regex('/^(0|[1-9][0-9]{0,11})$/D'),
                ])
                ->action(fn (WorkOrderMaterialOrder $record, array $data) =>
                    app(WorkOrderMaterialsService::class)->placeOrder($record, auth()->user(),
                        $data['vendor'], $data['reference'], $data['amount'])),
            Tables\Actions\Action::make('receive')->label('Terima Barang')
                ->icon('heroicon-o-inbox-arrow-down')->color('info')
                ->visible(fn (WorkOrderMaterialOrder $record): bool => $this->mayManage()
                    && $record->status === 'ordered')
                ->form([
                    Forms\Components\TextInput::make('quantity')->label('Jumlah datang')->required()->numeric()->gt(0),
                    Forms\Components\TextInput::make('reference')->label('Surat jalan/bukti')->required()->minLength(3),
                ])
                ->action(fn (WorkOrderMaterialOrder $record, array $data) =>
                    app(WorkOrderMaterialsService::class)->receive($record, auth()->user(), $data['quantity'], $data['reference'])),
            Tables\Actions\Action::make('inspect')->label('Periksa Kesesuaian')
                ->icon('heroicon-o-clipboard-document-check')->color('success')
                ->visible(fn (WorkOrderMaterialOrder $record): bool => $this->mayManage()
                    && $record->receipts()->where('status', 'pending')
                        ->where('received_by', '!=', auth()->id())->exists())
                ->form([
                    Forms\Components\Select::make('receipt_id')->label('Kiriman yang diperiksa')
                        ->options(fn (WorkOrderMaterialOrder $record): array => $record->receipts()
                            ->where('status', 'pending')->where('received_by', '!=', auth()->id())->get()
                            ->mapWithKeys(fn (WorkOrderMaterialReceipt $receipt) => [
                                $receipt->id => '#'.$receipt->id.' · '.$receipt->quantity.' '.$record->line->unit.' · '.$receipt->reference,
                            ])->all())->required(),
                    Forms\Components\TextInput::make('accepted')->label('Jumlah sesuai (0 jika ditolak)')
                        ->required()->numeric()->minValue(0),
                    Forms\Components\Textarea::make('notes')->label('Hasil pemeriksaan')->required()->minLength(5),
                ])
                ->action(function (WorkOrderMaterialOrder $record, array $data): void {
                    $receipt = $record->receipts()->findOrFail($data['receipt_id']);
                    app(WorkOrderMaterialsService::class)->inspect($receipt, auth()->user(),
                        $data['accepted'], $data['notes']);
                    Notification::make()->title('Pemeriksaan material tersimpan')->success()->send();
                }),
            Tables\Actions\Action::make('history')->label('Riwayat QC')
                ->icon('heroicon-o-queue-list')
                ->form([
                    Forms\Components\Placeholder::make('history')->label('Kiriman dan hasil pemeriksaan')
                        ->content(fn (WorkOrderMaterialOrder $record): string => $record->receipts
                            ->map(fn (WorkOrderMaterialReceipt $receipt): string =>
                                '#'.$receipt->id.' · '.$receipt->quantity.' · '.$receipt->status.
                                ' · sesuai: '.($receipt->accepted_quantity ?? '-').' · '.
                                ($receipt->inspection_notes ?? 'Belum diperiksa'))
                            ->implode("\n") ?: 'Belum ada kiriman'),
                ]),
        ])->bulkActions([]);
    }
}
