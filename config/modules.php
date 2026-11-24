<?php

return [
    'inventory' => ['label' => 'Inventaris Barang', 'description' => 'Barang, barang satuan, stok dan relasi aset.'],
    'rooms' => ['label' => 'Gedung & Ruangan', 'description' => 'Gedung, lantai dan ruangan.'],
    'vehicles' => ['label' => 'Kendaraan', 'description' => 'Kendaraan, pengemudi dan pendamping.'],
    'loans' => ['label' => 'Peminjaman', 'description' => 'Pengajuan hingga pengembalian aset.'],
    'loans_item' => ['label' => 'Peminjaman Barang', 'parent' => 'loans', 'requires' => ['inventory']],
    'loans_room' => ['label' => 'Peminjaman Ruangan', 'parent' => 'loans', 'requires' => ['rooms']],
    'loans_vehicle' => ['label' => 'Peminjaman Kendaraan', 'parent' => 'loans', 'requires' => ['vehicles']],
    'tickets' => ['label' => 'Laporan Kerusakan & Perbaikan', 'description' => 'Pelaporan, perawatan dasar dan keselamatan aset.'],
    'work_orders' => ['label' => 'Work Order Lanjutan', 'parent' => 'tickets', 'description' => 'Workflow dinamis, estimasi, material, pembelian, vendor dan revisi.'],
    'recurring' => ['label' => 'Jadwal Rutin Aset', 'description' => 'Pengaturan jadwal berulang; jadwal lama tetap dihormati.'],
    'public_schedule' => ['label' => 'Jadwal Publik', 'description' => 'Jadwal di halaman utama dan QR ruangan.'],
    'reports' => ['label' => 'Laporan & Analitik', 'description' => 'Rekapan penggunaan dan ringkasan peminjaman.'],
    'chat' => ['label' => 'Komunikasi (Chat)', 'description' => 'Chat internal dan tombol mengambang.'],
];
