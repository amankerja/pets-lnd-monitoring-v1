<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TUGAS 1: Delta Migration untuk "Level x Position" & Standar Kompetensi Jabatan.
     */
    public function up(): void
    {
        // 1. Tabel `job_positions` (Master Level x Position)
        if (!Schema::hasTable('job_positions')) {
            Schema::create('job_positions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique()->comment('Contoh: SUP-ENG, OPR-MIN, TCH-ELE');
                $table->string('level_name', 80)->index()->comment('Contoh: Operator, Technician, Supervisor, Manager');
                $table->string('position_title', 120)->index()->comment('Contoh: Mechanical, Electrical, HSE, Mining Production');
                $table->enum('organization_type', ['internal', 'contractor', 'both'])
                    ->default('both')
                    ->index();
                $table->timestamps();
            });
        }

        // 2. Tabel `position_skill_standards` (Setting Competency on Level x Position)
        if (!Schema::hasTable('position_skill_standards')) {
            Schema::create('position_skill_standards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('job_position_id')
                    ->constrained('job_positions')
                    ->cascadeOnDelete();
                $table->foreignId('skill_id')
                    ->constrained('skills')
                    ->cascadeOnDelete();
                $table->unsignedTinyInteger('required_target_level')
                    ->default(3)
                    ->comment('Standar Target Level Kompetensi Jabatan (1-5)');
                $table->boolean('is_mandatory')->default(true);
                $table->timestamps();

                $table->unique(['job_position_id', 'skill_id'], 'position_skill_standards_pos_skill_unique');
            });
        }

        // 3. Update tabel `users` (Tambahkan `job_position_id`)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'job_position_id')) {
                $table->foreignId('job_position_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('job_positions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'job_position_id')) {
                $table->dropConstrainedForeignId('job_position_id');
            }
        });

        Schema::dropIfExists('position_skill_standards');
        Schema::dropIfExists('job_positions');
    }
};

