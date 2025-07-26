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

        if (Auth::check() === false) {
            return redirect()->route('mikrotik.login.show');
        }

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
            // Hapus session setelah sukses
            //session()->forget(['hotspot_mac', 'hotspot_ip']);
            return view('mikrotik.success');
        } catch (\Exception $e) {
            // Hapus session jika gagal
            //session()->forget(['hotspot_mac', 'hotspot_ip']);
            return redirect()->route('mikrotik.login.show')->with('error', 'Login hotspot gagal: ' . $e->getMessage());
        }

        // Hapus data dari session
        // session()->forget(['hotspot_mac', 'hotspot_ip']);

        // Redirect ke halaman setelah login
        return redirect()->route('/admin')->with('success', 'Login hotspot berhasil.');
    }

    public function showLogin()
    {
        // Tampilkan halaman login captive portal
        return view('mikrotik.login');
    }

    public function postLogin(Request $request)
    {
        // Proses login captive portal
        $credentials = $request->only('username', 'password');

        if (Auth::attempt($credentials)) {
            // Jika berhasil, redirect ke halaman yang diinginkan
            return redirect()->route('mikrotik.login.show')->with('success', 'Login berhasil.');
        }

        // Jika gagal, kembali ke halaman login dengan pesan error
        return redirect()->back()->withErrors(['login' => 'Username atau password salah.']);
    }
}
