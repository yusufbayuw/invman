<?php

namespace App\Observers;

use App\Models\G002M007Item;
use App\Models\G005M009ItemReservation;
use App\Models\G005M016ItemReservationDetail;

class G005M009ItemReservationObserver
{
    /**
     * Handle the G005M009ItemReservation "created" event.
     */
    public function created(G005M009ItemReservation $g005M009ItemReservation): void
    {
        // Set status reservasi menjadi 'menunggu persetujuan'
        $g005M009ItemReservation->status = 'menunggu persetujuan';
        $g005M009ItemReservation->saveQuietly();

        /* // Ambil data item berdasarkan ID item pada reservasi
        $item = G002M007Item::find($g005M009ItemReservation->g002_m007_item_id);

        // Kurangi jumlah available_quantity pada item sesuai jumlah yang dipesan
        $item->decrement('available_quantity', $g005M009ItemReservation->quantity);

        // Ambil instance barang yang masih tersedia sebanyak jumlah yang dipesan
        $instances = $item->item_instance()
            ->where('is_available', true)
            ->take($g005M009ItemReservation->quantity)
            ->get();

        // Loop setiap instance barang yang diambil
        foreach ($instances as $instance) {
            // Set status instance barang sesuai status reservasi
            $instance->status = $g005M009ItemReservation->status;
            // Tandai instance barang sebagai tidak tersedia
            $instance->is_available = false;
            $instance->saveQuietly();

            // Buat detail reservasi untuk setiap instance barang
            G005M016ItemReservationDetail::create([
                'g005_m009_item_reservation_id' => $g005M009ItemReservation->id,
                'g002_m015_item_instance_id' => $instance->id,
            ]);
        } */
    }

    /**
     * Handle the G005M009ItemReservation "updated" event.
     */
    public function updated(G005M009ItemReservation $g005M009ItemReservation): void
    {

        /* // Cek apakah field 'status' pada reservasi berubah
        if ($g005M009ItemReservation->isDirty('status')) {
            // Jika status berubah menjadi 'disetujui/dipinjamkan'
            if ($g005M009ItemReservation->status === 'disetujui/dipinjamkan') {

                // Ambil semua instance barang yang terkait dengan detail reservasi
                $instances = $g005M009ItemReservation->item_reservation_detail
                    ->map(function ($detail) {
                        return $detail->item_instance;
                    })
                    ->filter(); // filter out null values
                // Update status setiap instance barang sesuai status reservasi
                foreach ($instances as $instance) {
                    $instance->status = $g005M009ItemReservation->status;
                    $instance->saveQuietly();
                }
                // Jika status berubah menjadi 'dikembalikan'
            } elseif ($g005M009ItemReservation->status === 'dikembalikan') {
                // Ambil semua instance barang yang terkait dengan reservasi
                $instances = $g005M009ItemReservation->item_reservation_detail
                    ->map(function ($detail) {
                        return $detail->item_instance;
                    })
                    ->filter(); // filter out null values

                // Setiap instance barang diubah statusnya menjadi 'tersedia' dan tersedia untuk dipinjam lagi
                foreach ($instances as $instance) {
                    $instance->status = "tersedia";
                    $instance->is_available = true;
                    $instance->saveQuietly();
                }

                // Ambil data item terkait dan tambahkan kembali jumlah available_quantity
                $item = G002M007Item::find($g005M009ItemReservation->g002_m007_item_id);
                if ($item) {
                    $item->increment('available_quantity', $g005M009ItemReservation->quantity);
                    $item->saveQuietly();
                }
            }
        } */
    }

    /**
     * Handle the G005M009ItemReservation "deleted" event.
     */
    public function deleted(G005M009ItemReservation $g005M009ItemReservation): void
    {
        //
    }

    /**
     * Handle the G005M009ItemReservation "restored" event.
     */
    public function restored(G005M009ItemReservation $g005M009ItemReservation): void
    {
        //
    }

    /**
     * Handle the G005M009ItemReservation "force deleted" event.
     */
    public function forceDeleted(G005M009ItemReservation $g005M009ItemReservation): void
    {
        //
    }
}
