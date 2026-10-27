<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan kolom `site` dan `job_level` pada tabel `users` dan `organizations`
     * untuk standarisasi format data Excel MDKA Group (Site Tambang & Job Grade).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'site')) {
                $table->string('site', 100)
                    ->nullable()
                    ->default('Site Pani Project (PETS/GSM)')
                    ->index()
                    ->after('department')
                    ->comment('Contoh: PT Gorontalo Sejahtera Mining, Site Wetar, Site Tujuh Bukit, Site Pani Project');
            }

            if (!Schema::hasColumn('users', 'job_level')) {
                $table->string('job_level', 50)
                    ->nullable()
                    ->default('Staff')
                    ->index()
                    ->after('site')
                    ->comment('Contoh: Non-Staff, Staff, Supervisor, Superintendent, Manager');
            }
        });

        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'site')) {
                $table->string('site', 100)
                    ->nullable()
                    ->default('Site Pani Project (PETS/GSM)')
                    ->index()
                    ->after('type')
                    ->comment('Site lokasi operasional organisasi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'job_level')) {
                $table->dropIndex(['job_level']);
                $table->dropColumn('job_level');
            }
            if (Schema::hasColumn('users', 'site')) {
                $table->dropIndex(['site']);
                $table->dropColumn('site');
            }
        });

        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'site')) {
                $table->dropIndex(['site']);
                $table->dropColumn('site');
            }
        });
    }
};

