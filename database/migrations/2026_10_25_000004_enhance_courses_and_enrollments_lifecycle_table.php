<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TUGAS 1: Penyempurnaan Skema Master Course, Buku Induk Sertifikat (Ledger),
     * Penanda Sertifikat Lama (`is_superseded`), & Sinkronisasi Masa Berlaku `user_skills` (`valid_until`).
     */
    public function up(): void
    {
        // 1. Tabel `courses`
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'is_mandatory')) {
                $table->boolean('is_mandatory')
                    ->default(true)
                    ->after('status')
                    ->index('idx_courses_is_mandatory');
            }

            if (!Schema::hasColumn('courses', 'training_source')) {
                $table->enum('training_source', ['internal', 'external'])
                    ->default('internal')
                    ->after('is_mandatory')
                    ->index('idx_courses_training_source');
            }

            if (!Schema::hasColumn('courses', 'training_category')) {
                $table->string('training_category', 60)
                    ->default('Safety')
                    ->after('training_source')
                    ->index('idx_courses_training_category');
            }

            if (!Schema::hasColumn('courses', 'validity_months')) {
                $table->unsignedSmallInteger('validity_months')
                    ->nullable()
                    ->default(24)
                    ->after('training_category')
                    ->comment('Masa berlaku sertifikat dalam bulan (12, 24, 36, atau null = Berlaku Selamanya / Permanent)');
            }

            if (!Schema::hasColumn('courses', 'skill_id')) {
                $table->foreignId('skill_id')
                    ->nullable()
                    ->after('validity_months')
                    ->constrained('skills')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('courses', 'granted_skill_level')) {
                $table->unsignedTinyInteger('granted_skill_level')
                    ->default(3)
                    ->after('skill_id')
                    ->comment('Level kompetensi (1-5) yang otomatis diberikan ke user_skills saat peserta lulus');
            }
        });

        // 2. Tabel `enrollments` (Buku Induk Sertifikat & Penanda `is_superseded`)
        Schema::table('enrollments', function (Blueprint $table) {
            if (!Schema::hasColumn('enrollments', 'enrollment_type')) {
                $table->enum('enrollment_type', ['regular', 'refreshment', 'historical_migration'])
                    ->default('regular')
                    ->after('course_id')
                    ->index('idx_enrollments_enrollment_type');
            }

            if (!Schema::hasColumn('enrollments', 'completion_status')) {
                $table->enum('completion_status', ['registered', 'ongoing', 'passed', 'failed', 'cancelled'])
                    ->default('registered')
                    ->after('status')
                    ->index('idx_enrollments_completion_status');
            }

            if (!Schema::hasColumn('enrollments', 'completed_at')) {
                $table->date('completed_at')
                    ->nullable()
                    ->after('progress_percentage')
                    ->index('idx_enrollments_completed_at');
            }

            if (!Schema::hasColumn('enrollments', 'certificate_number')) {
                $table->string('certificate_number', 120)
                    ->nullable()
                    ->unique('unq_enrollments_certificate_number')
                    ->after('completed_at');
            }

            if (!Schema::hasColumn('enrollments', 'certificate_file_path')) {
                $table->string('certificate_file_path', 255)
                    ->nullable()
                    ->after('certificate_number')
                    ->comment('Path file PDF/Scan sertifikat fisik (khususnya training eksternal/historis)');
            }

            if (!Schema::hasColumn('enrollments', 'certificate_issued_at')) {
                $table->date('certificate_issued_at')
                    ->nullable()
                    ->after('certificate_file_path')
                    ->index('idx_enrollments_cert_issued_at');
            }

            if (!Schema::hasColumn('enrollments', 'certificate_expires_at')) {
                $table->date('certificate_expires_at')
                    ->nullable()
                    ->after('certificate_issued_at')
                    ->index('idx_enrollments_cert_expires_at');
            }

            if (!Schema::hasColumn('enrollments', 'is_superseded')) {
                // Penanda bahwa sertifikat lama sudah digantikan oleh sertifikat Refreshment baru (Anti Superseded Trap)
                $table->boolean('is_superseded')
                    ->default(false)
                    ->after('certificate_expires_at')
                    ->index('idx_enrollments_is_superseded');
            }
        });

        // Izinkan pekerja memiliki riwayat sertifikat lama (`is_superseded = true`) + sertifikat pembaruan (`refreshment`) di `enrollments`
        try {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->index(['user_id', 'course_id'], 'idx_enrollments_user_course_history');
                $table->dropUnique('uq_enrollment_user_course');
            });
        } catch (\Throwable $e) {
            // Abaikan jika index sudah disesuaikan sebelumnya
        }

        // 3. Tabel `user_skills` (Sinkronisasi langsung Masa Berlaku & Nomor Sertifikat ke Heatmap Skill Matrix)
        Schema::table('user_skills', function (Blueprint $table) {
            if (!Schema::hasColumn('user_skills', 'valid_until')) {
                $table->date('valid_until')
                    ->nullable()
                    ->after('current_level')
                    ->index('idx_user_skills_valid_until');
            }

            if (!Schema::hasColumn('user_skills', 'last_certificate_number')) {
                $table->string('last_certificate_number', 120)
                    ->nullable()
                    ->after('valid_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_skills', function (Blueprint $table) {
            if (Schema::hasColumn('user_skills', 'last_certificate_number')) {
                $table->dropColumn('last_certificate_number');
            }

            if (Schema::hasColumn('user_skills', 'valid_until')) {
                $table->dropIndex('idx_user_skills_valid_until');
                $table->dropColumn('valid_until');
            }
        });

        Schema::table('enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('enrollments', 'is_superseded')) {
                $table->dropIndex('idx_enrollments_is_superseded');
                $table->dropColumn('is_superseded');
            }

            if (Schema::hasColumn('enrollments', 'certificate_file_path')) {
                $table->dropColumn('certificate_file_path');
            }

            if (Schema::hasColumn('enrollments', 'completed_at')) {
                $table->dropIndex('idx_enrollments_completed_at');
                $table->dropColumn('completed_at');
            }

            if (Schema::hasColumn('enrollments', 'completion_status')) {
                $table->dropIndex('idx_enrollments_completion_status');
                $table->dropColumn('completion_status');
            }

            if (Schema::hasColumn('enrollments', 'enrollment_type')) {
                $table->dropIndex('idx_enrollments_enrollment_type');
                $table->dropColumn('enrollment_type');
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'granted_skill_level')) {
                $table->dropColumn('granted_skill_level');
            }

            if (Schema::hasColumn('courses', 'skill_id')) {
                $table->dropConstrainedForeignId('skill_id');
            }

            if (Schema::hasColumn('courses', 'validity_months')) {
                $table->dropColumn('validity_months');
            }
        });
    }
};
