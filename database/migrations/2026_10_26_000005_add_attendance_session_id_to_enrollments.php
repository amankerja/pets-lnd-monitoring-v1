<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('attendance_session_id')
                ->nullable()
                ->after('course_id')
                ->constrained('attendance_sessions')
                ->nullOnDelete();

            $table->index(['attendance_session_id', 'status'], 'idx_enrollments_session_status');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('idx_enrollments_session_status');
            $table->dropConstrainedForeignId('attendance_session_id');
        });
    }
};
