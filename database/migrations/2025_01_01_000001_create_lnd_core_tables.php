<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Courses
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('trainer_id')->constrained('users')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'active', 'completed'])->default('draft');
            $table->timestamps();

            $table->index(['status', 'start_date'], 'idx_courses_status_start');
            $table->index('trainer_id', 'idx_courses_trainer');
        });

        // 2. Course Modules
        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('order_index')->default(1);
            $table->timestamps();

            $table->index(['course_id', 'order_index'], 'idx_modules_course_order');
        });

        // 3. Enrollments
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'course_id'], 'uq_enrollment_user_course');
            $table->index(['course_id', 'status'], 'idx_enrollments_course_status');
        });

        // 4. Attendance Sessions
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->date('session_date');
            $table->string('qr_token', 64)->unique();
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['course_id', 'session_date', 'is_active'], 'idx_att_sessions_lookup');
        });

        // 5. Attendances
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['present', 'permit', 'absent'])->default('present');
            $table->timestamp('scanned_at')->nullable();
            $table->string('device_info')->nullable();
            $table->timestamps();

            // Mencegah double-submit saat race condition
            $table->unique(['attendance_session_id', 'user_id'], 'uq_attendance_session_user');
            $table->index(['user_id', 'status'], 'idx_attendances_user_status');
        });

        // 6. Module Progress
        Schema::create('module_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('course_module_id')->constrained('course_modules')->cascadeOnDelete();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'course_module_id'], 'uq_progress_enrollment_module');
            $table->index(['enrollment_id', 'is_completed'], 'idx_progress_enrollment_completed');
        });

        // 7. Skills
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('category', 100);
            $table->timestamps();

            $table->index('category', 'idx_skills_category');
        });

        // 8. User Skills (Skill Matrix & Gap Analysis)
        Schema::create('user_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->unsignedTinyInteger('target_level')->default(3);  // Skala 1-5
            $table->unsignedTinyInteger('current_level')->default(1); // Skala 1-5
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'skill_id'], 'uq_user_skill');
            // Index untuk query cepat Gap Analysis
            $table->index(['skill_id', 'current_level', 'target_level'], 'idx_user_skills_gap_analysis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_skills');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('module_progress');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('course_modules');
        Schema::dropIfExists('courses');
    }
};

