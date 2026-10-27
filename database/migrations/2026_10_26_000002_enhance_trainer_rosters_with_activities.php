<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Tambah Kolom Aktivitas pada Tabel `trainer_rosters`
        |--------------------------------------------------------------------------
        */
        Schema::table('trainer_rosters', function (Blueprint $table) {
            if (!Schema::hasColumn('trainer_rosters', 'activity_type')) {
                $table->enum('activity_type', [
                    'TRAINING_CLASS',
                    'FIELD_OBSERVATION',
                    'LND_ADMIN',
                    'STANDBY',
                    'OFF',
                ])->default('TRAINING_CLASS')->after('status');
            }

            if (!Schema::hasColumn('trainer_rosters', 'course_id')) {
                $table->foreignId('course_id')
                    ->nullable()
                    ->after('trainer_id')
                    ->constrained('courses')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('trainer_rosters', 'location')) {
                $table->string('location', 150)
                    ->nullable()
                    ->after('shift');
            }
        });

        /*
        |--------------------------------------------------------------------------
        | 2. Tabel Baru `trainer_master_calendar_logs`
        | Rekap aktivitas harian lintas seluruh instruktur / trainer
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('trainer_master_calendar_logs')) {
            Schema::create('trainer_master_calendar_logs', function (Blueprint $table) {
                $table->id();
                $table->date('calendar_date');
                $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
                $table->enum('activity_type', [
                    'TRAINING_CLASS',
                    'FIELD_OBSERVATION',
                    'LND_ADMIN',
                    'STANDBY',
                    'OFF',
                ])->default('TRAINING_CLASS');
                $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
                $table->string('location', 150)->nullable();
                $table->string('status', 50)->default('on_duty');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['calendar_date', 'trainer_id'], 'tmcl_date_trainer_idx');
                $table->index(['activity_type'], 'tmcl_act_type_idx');
            });
        }

        // Sinkronisasi awal activity_type untuk roster libur/cuti yang sudah ada
        DB::table('trainer_rosters')
            ->whereIn('status', ['off_roster', 'annual_leave', 'sick'])
            ->update(['activity_type' => 'OFF', 'course_id' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_master_calendar_logs');

        Schema::table('trainer_rosters', function (Blueprint $table) {
            if (Schema::hasColumn('trainer_rosters', 'course_id')) {
                $table->dropConstrainedForeignId('course_id');
            }
            if (Schema::hasColumn('trainer_rosters', 'location')) {
                $table->dropColumn('location');
            }
            if (Schema::hasColumn('trainer_rosters', 'activity_type')) {
                $table->dropColumn('activity_type');
            }
        });
    }
};
