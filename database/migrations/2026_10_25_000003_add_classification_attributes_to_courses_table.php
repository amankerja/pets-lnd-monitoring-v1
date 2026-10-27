<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TUGAS 1: Delta Migration untuk Atribut Klasifikasi Training (`courses`).
     * Menambahkan:
     * 1. `is_mandatory` (boolean, default true, indexed) -> Filter Mandatory vs Non-Mandatory
     * 2. `training_source` (enum: 'internal', 'external', default 'internal', indexed) -> Filter Training Internal vs External
     * 3. `training_category` (string, default 'Safety', indexed) -> Filter Jenis/Bidang Training (Safety, Operation, Plant, Pengawas, Engineering, Leadership, General)
     * 4. Memastikan indeks pencarian cepat pada `code` dan komposit filter klasifikasi.
     */
    public function up(): void
    {
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

            // Composite index untuk akselerasi Multi-Attribute Server-Side Filtering
            $table->index(
                ['code', 'is_mandatory', 'training_source', 'training_category'],
                'idx_courses_multi_filter_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('idx_courses_multi_filter_lookup');

            if (Schema::hasColumn('courses', 'training_category')) {
                $table->dropIndex('idx_courses_training_category');
                $table->dropColumn('training_category');
            }

            if (Schema::hasColumn('courses', 'training_source')) {
                $table->dropIndex('idx_courses_training_source');
                $table->dropColumn('training_source');
            }

            if (Schema::hasColumn('courses', 'is_mandatory')) {
                $table->dropIndex('idx_courses_is_mandatory');
                $table->dropColumn('is_mandatory');
            }
        });
    }
};

