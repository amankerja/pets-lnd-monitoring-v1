<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_training_logistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('external_type', ['inhouse', 'dinas_luar']);
            $table->string('vendor_name', 150);
            $table->string('pic_training', 100);
            $table->enum('training_mode', ['offline', 'online']);
            $table->string('training_room', 100)->nullable();
            $table->integer('training_duration_days')->default(1);
            $table->decimal('training_cost', 15, 2)->default(0);
            $table->json('required_documents_schema')->nullable();
            $table->json('accommodation_checklist')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_training_logistics');
    }
};
