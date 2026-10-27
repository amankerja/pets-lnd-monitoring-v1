<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mencakup:
     * 1. Tabel `organizations` (internal_dept | contractor)
     * 2. Update `users` (5 Role RBAC & FK organization_id)
     * 3. Update `courses`, `attendance_sessions`, & `enrollments` (Composite Index H-60)
     * 4. Tabel `training_requests` (Pendaftaran Batch & Pengajuan Training Pembaruan)
     * 5. Tabel `self_reported_trainings` (Self-Report Sertifikat Eksternal/Mandiri oleh Peserta)
     * 6. Tabel `trainer_competencies` (Lisensi Kompetensi Mengajar Instruktur)
     * 7. Modul Quiz (`quizzes`, `quiz_questions`, `quiz_attempts`, `quiz_attempt_answers`)
     */
    public function up(): void
    {
        // 1. Tabel Organisasi (Menyatukan Departemen Internal & Perusahaan Kontraktor)
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique(); // Contoh: DEPT-ENG atau VND-PTMJU
            $table->string('name');               // Contoh: Dept. Engineering atau PT Maju Jaya Kontraktor
            $table->enum('type', ['internal_dept', 'contractor'])->default('internal_dept');
            $table->timestamps();

            $table->index('type');
        });

        // 2. Penyesuaian Tabel Users (Tambah relasi organisasi & perluas 5 Role: lnd, trainer, dept_admin, contractor_admin, peserta)
        Schema::table('users', function (Blueprint $table) {
            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                $table->string('role', 30)->default('peserta')->change();
            }
            $table->foreignId('organization_id')
                ->nullable()
                ->after('role')
                ->constrained('organizations')
                ->nullOnDelete();

            $table->index(['organization_id', 'role'], 'users_org_role_idx');
        });

        // 2B. Tambah Masa Berlaku Standar Sertifikat (validity_years) pada Tabel Courses
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedTinyInteger('validity_years')->default(2)->after('status');
        });

        // 3. Tambah Durasi Jam Mengajar & Trainer per Sesi Absen (Untuk kalkulasi sah Teaching Hours)
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('trainer_id')
                ->nullable()
                ->after('course_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->decimal('duration_hours', 4, 1)->default(2.0)->after('session_date');

            $table->index(['trainer_id', 'session_date'], 'att_sessions_trainer_date_idx');
        });

        // 4. Tambah Masa Berlaku Sertifikat & Composite Index di Enrollments (Untuk Subquery whereNotExists H-60 Cepat)
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('registered_by')
                ->nullable()
                ->after('course_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('certificate_number', 80)->nullable()->unique()->after('progress_percentage');
            $table->date('certificate_issued_at')->nullable()->after('certificate_number');
            $table->date('certificate_expires_at')->nullable()->after('certificate_issued_at');

            $table->index('certificate_expires_at', 'enrollments_cert_expires_idx');
            $table->index(['user_id', 'course_id', 'certificate_expires_at'], 'enrollments_user_course_expires_idx');
        });

        // 5. Tabel TNA (Training Needs Analysis), Pendaftaran Batch, & Pengajuan Training Pembaruan
        Schema::create('training_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained('skills')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->enum('request_type', ['new_tna', 'renewal_refreshment', 'batch_registration'])->default('new_tna');
            $table->unsignedInteger('target_participants_count')->default(1);
            $table->json('participant_ids')->nullable();
            $table->text('reason_notes')->nullable();
            $table->enum('status', ['pending_lnd', 'approved', 'scheduled', 'completed', 'rejected'])->default('pending_lnd');
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'training_requests_org_status_idx');
            $table->index(['course_id', 'request_type', 'status'], 'training_requests_course_type_status_idx');
        });

        // 6. Tabel Self-Reported Trainings (Pelaporan Mandiri Sertifikat Eksternal oleh Peserta)
        Schema::create('self_reported_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('course_name');
            $table->string('organizer');
            $table->string('certificate_number', 100);
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->string('document_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'self_reported_user_status_idx');
            $table->index('expires_at', 'self_reported_expires_idx');
        });

        // 7. Tabel Kompetensi Mengajar Trainer (Relasi Trainer dengan Skill yang berhak ia ajarkan)
        Schema::create('trainer_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->string('certification_no', 100)->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['trainer_id', 'skill_id'], 'trainer_skill_competency_unique');
        });

        /*
        |--------------------------------------------------------------------------
        | 8. MODUL QUIZ (Pre-Test & Post-Test)
        | CATATAN: Modul Quiz Interaktif dalam pengembangan
        |--------------------------------------------------------------------------
        */
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->enum('quiz_type', ['pre_test', 'post_test'])->default('pre_test');
            $table->unsignedTinyInteger('passing_score')->default(75);
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['course_id', 'quiz_type'], 'quizzes_course_type_idx');
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->text('question_text');
            $table->json('options'); // Contoh: {"A": "...", "B": "...", "C": "...", "D": "..."}
            $table->string('correct_option', 10);
            $table->unsignedTinyInteger('points')->default(10);
            $table->unsignedSmallInteger('order_index')->default(1);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->boolean('is_passed')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'user_id'], 'quiz_attempts_quiz_user_idx');
        });

        Schema::create('quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('quiz_question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->string('selected_option', 10)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['quiz_attempt_id', 'quiz_question_id'], 'attempt_question_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('trainer_competencies');
        Schema::dropIfExists('self_reported_trainings');
        Schema::dropIfExists('training_requests');

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_user_course_expires_idx');
            $table->dropIndex('enrollments_cert_expires_idx');
            $table->dropConstrainedForeignId('registered_by');
            $table->dropColumn(['certificate_number', 'certificate_issued_at', 'certificate_expires_at']);
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex('att_sessions_trainer_date_idx');
            $table->dropConstrainedForeignId('trainer_id');
            $table->dropColumn('duration_hours');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('validity_years');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_org_role_idx');
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::dropIfExists('organizations');
    }
};
