<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     * Contoh pemakaian di route: ->middleware('role:admin,trainer') atau ->middleware('role:peserta')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->values()
            ->all();

        // Sinkronisasi kompatibilitas role `lnd` (baru) dan `admin` (legacy/test)
        if (in_array('lnd', $allowedRoles, true) && !in_array('admin', $allowedRoles, true)) {
            $allowedRoles[] = 'admin';
        }
        if (in_array('admin', $allowedRoles, true) && !in_array('lnd', $allowedRoles, true)) {
            $allowedRoles[] = 'lnd';
        }

        // Jika route membutuhkan role 'trainer', izinkan juga user dengan is_trainer === true
        $isTrainerAuthorized = in_array('trainer', $allowedRoles, true) && (bool) $user->is_trainer;

        if (!in_array($user->role, $allowedRoles, true) && !$isTrainerAuthorized) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akses ditolak. Anda tidak memiliki otorisasi untuk halaman ini.',
                ], 403);
            }

            return match ($user->role) {
                'lnd', 'admin', 'trainer' => redirect()->route('dashboard')->with('error', 'Halaman tersebut tidak sesuai dengan otorisasi role Anda.'),
                'dept_admin', 'contractor_admin' => redirect()->route('org-admin.portal')->with('error', 'Akses dibatasi pada Portal Organisasi / Departemen Anda.'),
                'peserta' => redirect()->route('mobile.home')->with('error', 'Akses dibatasi pada Portal Peserta.'),
                default => abort(403, 'Unauthorized action.'),
            };
        }

        return $next($request);
    }
}

