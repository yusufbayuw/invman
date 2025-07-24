<?php

namespace App\Http\Controllers;

use RouterOS\Query;
use RouterOS\Client;
use RouterOS\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MikrotikHotspotCaptiveController extends Controller
{
    // buat controller untuk menerima https://BASE_URL/captive-login?mac={mac_address}&ip={ip_address}
    public function login(Request $request)
    {
        $mac = $request->query('mac');
        $ip = $request->query('ip');
        session(['hotspot_mac' => $mac, 'hotspot_ip' => $ip]);

        // Jika user belum login, redirect ke halaman login sistem
        // if (Auth::user()->id ?? true) {
            // Simpan mac & ip di session agar bisa diakses setelah login
        //    return redirect()->route('mikrotik.login');
        //}

        // kirim data user active ke mikrotik

        // 🔐 Setting API MikroTik
        $client = new Client([
            'host' => '10.10.1.1',
            'user' => 'admin',
            'pass' => '',
        ]);

        $username = Auth::user()->username ?? 'invman';
        $password = 'invman';

        // ✅ Cek apakah user sudah ada
        $checkUser = new Query('/ip/hotspot/user/print');
        $checkUser->where('name', $username);
        $users = $client->query($checkUser)->read();

        if (count($users) === 0) {
            // 🆕 User belum ada → tambahkan
            $addUser = new Query('/ip/hotspot/user/add');
            $addUser->equal('name', $username);
            $addUser->equal('password', $password);
            $addUser->equal('profile', 'default'); // ganti kalau pakai profil khusus
            $client->query($addUser)->read();
        }

        // 🔐 Paksa login user secara aktif ke hotspot
        $loginUser = new Query('/ip/hotspot/active/login');
        $loginUser->equal('user', $username);
        $loginUser->equal('password', $password);
        $loginUser->equal('mac-address', $mac);
        $loginUser->equal('ip', $ip);

        try {
            $client->query($loginUser)->read();
            return view('mikrotik.success'); // sukses login
        } catch (\Exception $e) {
            return false; // gagal login
        }

        // Hapus data dari session
        // session()->forget(['hotspot_mac', 'hotspot_ip']);

        // Redirect ke halaman setelah login
        return redirect()->route('/admin')->with('success', 'Login hotspot berhasil.');
    }
}
