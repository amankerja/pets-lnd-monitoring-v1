<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        | 1. Tambahan Kolom Trainer pada Tabel `users`
        |--------------------------------------------------------------------------
        */
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_trainer')) {
                $table->boolean('is_trainer')->default(false)->after('role');
            }
            if (!Schema::hasColumn('users', 'trainer_type')) {
                $table->enum('trainer_type', ['INTERNAL_SME', 'FULL_TIME_LND', 'EXTERNAL_VENDOR'])
                    ->nullable()
                    ->after('is_trainer');
            }
            if (!Schema::hasColumn('users', 'roster_pattern')) {
                $table->string('roster_pattern', 50)->nullable()->after('trainer_type');
            }
        });

        /*
        |--------------------------------------------------------------------------
        | 2. Tabel `trainer_authorized_skills`
        | Menghubungkan trainer_id dengan skill_id (Master Competency List)
        | beserta lisensi ToT/BNSP dan masa berlaku
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('trainer_authorized_skills')) {
            Schema::create('trainer_authorized_skills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
                $table->string('license_type', 100)->nullable(); // e.g., 'ToT BNSP Level 4', 'K3 Kemnaker'
                $table->string('license_number', 100)->nullable();
                $table->date('valid_until')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['trainer_id', 'skill_id'], 'trainer_auth_skill_unique');
                $table->index(['trainer_id', 'valid_until'], 'trainer_auth_skill_validity_idx');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Tabel `trainer_rosters`
        | Mencatat jadwal harian Trainer (on_duty, off_roster, annual_leave, sick, site_visit)
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('trainer_rosters')) {
            Schema::create('trainer_rosters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
                $table->date('roster_date');
                $table->enum('status', ['on_duty', 'off_roster', 'annual_leave', 'sick', 'site_visit'])->default('on_duty');
                $table->string('shift', 30)->default('day'); // 'day', 'night', 'full'
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['trainer_id', 'roster_date'], 'trainer_roster_date_unique');
                $table->index(['trainer_id', 'roster_date', 'status'], 'trainer_roster_lookup_idx');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Tabel `trainer_teaching_logs`
        | Mencatat jam mengajar baik otomatis dari sesi absensi QR (auto_qr_session)
        | maupun input manual Admin LnD (manual_lnd_entry) + scanned_participants_count
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('trainer_teaching_logs')) {
            Schema::create('trainer_teaching_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('attendance_session_id')->nullable()->constrained('attendance_sessions')->nullOnDelete();
                $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
                $table->string('training_title');
                $table->date('log_date');
                $table->enum('entry_source', ['auto_qr_session', 'manual_lnd_entry'])->default('auto_qr_session');
                $table->decimal('duration_hours', 5, 2)->default(0.00);
                $table->unsignedInteger('scanned_participants_count')->default(0);
                $table->string('location')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['trainer_id', 'log_date'], 'trainer_teaching_logs_date_idx');
                $table->index(['attendance_session_id'], 'trainer_teaching_logs_session_idx');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Tabel `trainer_evaluations`
        | Menyimpan penilaian bintang (1-5) dan komentar dari peserta yang hadir
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('trainer_evaluations')) {
            Schema::create('trainer_evaluations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('attendance_session_id')->nullable()->constrained('attendance_sessions')->cascadeOnDelete();
                $table->foreignId('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
                $table->foreignId('participant_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating')->default(5); // Rating umum 1 - 5
                $table->unsignedTinyInteger('material_mastery_rating')->default(5); // Penguasaan materi 1-5
                $table->unsignedTinyInteger('delivery_method_rating')->default(5); // Cara penyampaian 1-5
                $table->unsignedTinyInteger('punctuality_rating')->default(5); // Ketepatan waktu 1-5
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->unique(['attendance_session_id', 'participant_id'], 'trainer_eval_session_participant_unique');
                $table->index(['trainer_id', 'created_at'], 'trainer_eval_trainer_idx');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Tabel `trainer_tasks_and_kpis`
        | Menyimpan penugasan & target KPI dari Admin LnD (daily, weekly, monthly, yearly)
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('trainer_tasks_and_kpis')) {
            Schema::create('trainer_tasks_and_kpis', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('period', ['daily', 'weekly', 'monthly', 'yearly'])->default('monthly');
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable();
                $table->decimal('target_teaching_hours', 6, 2)->default(0.00);
                $table->decimal('actual_teaching_hours', 6, 2)->default(0.00);
                $table->unsignedInteger('target_participants_count')->default(0);
                $table->unsignedInteger('actual_participants_count')->default(0);
                $table->decimal('target_rating', 3, 2)->default(4.50);
                $table->decimal('actual_rating', 3, 2)->default(0.00);
                $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('in_progress');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['trainer_id', 'period', 'due_date'], 'trainer_kpis_trainer_period_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_tasks_and_kpis');
        Schema::dropIfExists('trainer_evaluations');
        Schema::dropIfExists('trainer_teaching_logs');
        Schema::dropIfExists('trainer_rosters');
        Schema::dropIfExists('trainer_authorized_skills');

        Schema::table('users', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('users', 'roster_pattern')) $cols[] = 'roster_pattern';
            if (Schema::hasColumn('users', 'trainer_type')) $cols[] = 'trainer_type';
            if (Schema::hasColumn('users', 'is_trainer')) $cols[] = 'is_trainer';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

