<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom perencanaan jadwal untuk Interactive Training Calendar (ATMP / Bulanan / Mingguan).
     *
     * - start_time / end_time : jam operasional sesi (tampilan Plan Mingguan)
     * - location              : ruang / lokasi pelaksanaan
     * - quota                 : kuota peserta (nullable = tanpa batas)
     * - plan_status           : 'pending' (usulan ATMP) | 'approved' (resmi dijadwalkan)
     *
     * Baris lama (sesi yang dibuat via Proyektor QR) otomatis berstatus 'approved'.
     */
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_sessions', 'start_time')) {
                $table->time('start_time')->nullable()->after('session_date');
            }
            if (!Schema::hasColumn('attendance_sessions', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }
            if (!Schema::hasColumn('attendance_sessions', 'location')) {
                $table->string('location', 120)->nullable()->after('end_time');
            }
            if (!Schema::hasColumn('attendance_sessions', 'quota')) {
                $table->unsignedSmallInteger('quota')->nullable()->after('location');
            }
            if (!Schema::hasColumn('attendance_sessions', 'plan_status')) {
                $table->string('plan_status', 20)->default('approved')->after('quota');
                $table->index(['session_date', 'plan_status'], 'idx_att_sessions_calendar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_sessions', 'plan_status')) {
                $table->dropIndex('idx_att_sessions_calendar');
            }
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $drop = array_values(array_filter(
                ['start_time', 'end_time', 'location', 'quota', 'plan_status'],
                fn (string $col) => Schema::hasColumn('attendance_sessions', $col)
            ));
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};

