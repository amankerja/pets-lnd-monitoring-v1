<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Tampilkan halaman Login dengan daftar akun demo untuk 5 Role RBAC.
     */
    public function create(): Response
    {
        $isDemoMode = (bool) (config('app.debug', false) || app()->environment('local', 'testing'));

        $demoAccounts = $isDemoMode ? [
            [
                'role_label' => 'Super Admin L&D (Global)',
                'role' => 'lnd',
                'email' => 'admin@pani.co.id',
                'description' => 'Akses global seluruh modul, matriks, dan analitik',
            ],
            [
                'role_label' => 'Trainer / Instruktur',
                'role' => 'trainer',
                'email' => 'hendra.trainer@pani.co.id',
                'description' => 'Akses jadwal mengajar, proyektor QR, & teaching hours',
            ],
            [
                'role_label' => 'Admin Departemen Internal',
                'role' => 'dept_admin',
                'email' => 'dept.mining@pani.co.id',
                'description' => 'Kelola pekerja Dept. Mining, TNA, & renewal alert H-60',
            ],
            [
                'role_label' => 'Admin Perusahaan Kontraktor',
                'role' => 'contractor_admin',
                'email' => 'admin@majujaya-vendor.co.id',
                'description' => 'Kelola pekerja PT Maju Jaya Kontraktor & pengajuan batch',
            ],
            [
                'role_label' => 'Peserta Pelatihan (Pekerja)',
                'role' => 'peserta',
                'email' => 'peserta1@pani.co.id',
                'description' => 'Mobile PWA, scan QR presensi, radar skill, & self-report',
            ],
        ] : [];

        return Inertia::render('Auth/Login', [
            'status' => session('status'),
            'isDemoMode' => $isDemoMode,
            'demoAccounts' => $demoAccounts,
        ]);
    }

    /**
     * Proses autentikasi login dan arahkan ke dashboard sesuai role pengguna.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (!Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password yang Anda masukkan tidak sesuai.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        return match ($user->role) {
            'peserta' => redirect()->intended(route('mobile.home')),
            'dept_admin', 'contractor_admin' => redirect()->intended(route('org-admin.portal')),
            'trainer' => redirect()->intended(route('trainer.schedule-hours')),
            default => redirect()->intended(route('dashboard')),
        };
    }

    /**
     * Keluar dari sesi login (Logout).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar dari sistem.');
    }
}

