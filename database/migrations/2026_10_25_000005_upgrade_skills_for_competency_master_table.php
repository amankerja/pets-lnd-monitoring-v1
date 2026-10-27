<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TUGAS 1: Delta Migration untuk Master Data Kompetensi (Tabel `skills`).
     * Menambahkan kolom:
     * - `competency_type` (enum: 'EXTERNAL_MANDATORY', 'INTERNAL_MANDATORY', 'NON_MANDATORY')
     * - `description` (text, nullable)
     */
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            if (!Schema::hasColumn('skills', 'competency_type')) {
                $table->enum('competency_type', [
                    'EXTERNAL_MANDATORY',
                    'INTERNAL_MANDATORY',
                    'NON_MANDATORY',
                ])
                    ->default('INTERNAL_MANDATORY')
                    ->after('category')
                    ->index('idx_skills_competency_type')
                    ->comment('EXTERNAL_MANDATORY = Sertifikasi Luar & Wajib, INTERNAL_MANDATORY = Internal & Wajib, NON_MANDATORY = Opsional');
            }

            if (!Schema::hasColumn('skills', 'description')) {
                $table->text('description')
                    ->nullable()
                    ->after('competency_type')
                    ->comment('Deskripsi standar & acuan kompetensi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            if (Schema::hasColumn('skills', 'description')) {
                $table->dropColumn('description');
            }

            if (Schema::hasColumn('skills', 'competency_type')) {
                $table->dropIndex('idx_skills_competency_type');
                $table->dropColumn('competency_type');
            }
        });
    }
};

