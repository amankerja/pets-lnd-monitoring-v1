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
        if (Schema::hasTable('trainer_tasks_and_kpis')) {
            Schema::table('trainer_tasks_and_kpis', function (Blueprint $table) {
                if (!Schema::hasColumn('trainer_tasks_and_kpis', 'task_type')) {
                    $table->string('task_type', 50)->default('task')->after('title');
                }
                if (!Schema::hasColumn('trainer_tasks_and_kpis', 'priority')) {
                    $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('task_type');
                }
                if (!Schema::hasColumn('trainer_tasks_and_kpis', 'trainer_notes')) {
                    $table->text('trainer_notes')->nullable()->after('description');
                }
                if (!Schema::hasColumn('trainer_tasks_and_kpis', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('trainer_tasks_and_kpis')) {
            Schema::table('trainer_tasks_and_kpis', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('trainer_tasks_and_kpis', 'task_type')) {
                    $columns[] = 'task_type';
                }
                if (Schema::hasColumn('trainer_tasks_and_kpis', 'priority')) {
                    $columns[] = 'priority';
                }
                if (Schema::hasColumn('trainer_tasks_and_kpis', 'trainer_notes')) {
                    $columns[] = 'trainer_notes';
                }
                if (Schema::hasColumn('trainer_tasks_and_kpis', 'completed_at')) {
                    $columns[] = 'completed_at';
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};

