<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan atribut Roster & Matrix MDKA Group:
     * 1. users: section, entity, nationality
     * 2. position_skill_standards: requirement_type ('M', 'NM', 'NR')
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'section')) {
                $table->string('section', 100)
                    ->nullable()
                    ->after('department')
                    ->comment('Contoh: Facility Electrical, Drill & Blast');
            }

            if (!Schema::hasColumn('users', 'entity')) {
                $table->string('entity', 50)
                    ->default('MDKA')
                    ->after('site')
                    ->comment('Contoh: MDKA, Labour Supply, Contractor');
            }

            if (!Schema::hasColumn('users', 'nationality')) {
                $table->string('nationality', 40)
                    ->default('Indonesia')
                    ->comment('Kewarganegaraan pekerja: Indonesia / Expatriate');
            }
        });

        Schema::table('position_skill_standards', function (Blueprint $table) {
            if (!Schema::hasColumn('position_skill_standards', 'requirement_type')) {
                $table->enum('requirement_type', ['M', 'NM', 'NR'])
                    ->default('M')
                    ->after('required_target_level')
                    ->comment('Status Kebutuhan Jabatan: M (Mandatory), NM (Non-Mandatory), NR (Not Required)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('position_skill_standards', function (Blueprint $table) {
            if (Schema::hasColumn('position_skill_standards', 'requirement_type')) {
                $table->dropColumn('requirement_type');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'nationality')) {
                $table->dropColumn('nationality');
            }
            if (Schema::hasColumn('users', 'entity')) {
                $table->dropColumn('entity');
            }
            if (Schema::hasColumn('users', 'section')) {
                $table->dropColumn('section');
            }
        });
    }
};

