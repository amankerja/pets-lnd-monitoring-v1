<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'nik' => $request->user()->nik,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'is_trainer' => (bool) ($request->user()->is_trainer ?? false),
                    'trainer_type' => $request->user()->trainer_type,
                    'organization_id' => $request->user()->organization_id,
                    'organization' => $request->user()->organization ? [
                        'id' => $request->user()->organization->id,
                        'code' => $request->user()->organization->code,
                        'name' => $request->user()->organization->name,
                        'type' => $request->user()->organization->type,
                    ] : null,
                    'department' => $request->user()->department,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'scanned_session' => fn () => $request->session()->get('scanned_session'),
            ],
        ];
    }
}

