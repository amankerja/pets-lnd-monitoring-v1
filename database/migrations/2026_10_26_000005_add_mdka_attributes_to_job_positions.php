<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            if (!Schema::hasColumn('job_positions', 'section')) {
                $table->string('section', 100)->nullable()->after('position_title');
            }

            if (!Schema::hasColumn('job_positions', 'entity')) {
                $table->string('entity', 50)->default('MDKA')->after('organization_type');
            }

            if (!Schema::hasColumn('job_positions', 'nationality')) {
                $table->string('nationality', 40)->default('Indonesia');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            foreach (['nationality', 'entity', 'section'] as $column) {
                if (Schema::hasColumn('job_positions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
