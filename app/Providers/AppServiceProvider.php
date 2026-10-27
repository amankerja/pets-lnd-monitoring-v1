<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Lokalisasi tanggal (translatedFormat) ke Bahasa Indonesia sesuai APP_LOCALE
        Carbon::setLocale(config('app.locale', 'id'));

        // Optimasi Performa Mesin SQLite: WAL Mode, Fast Synchronous, & FK Enforcement
        if (DB::getDriverName() === 'sqlite') {
            try {
                DB::statement('PRAGMA journal_mode = WAL;');
                DB::statement('PRAGMA synchronous = NORMAL;');
                DB::statement('PRAGMA foreign_keys = ON;');
            } catch (\Throwable) {
                // Abaikan jika database connection belum siap saat artisan command tertentu
            }
        }
    }
}

