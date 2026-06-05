<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('housekeeping_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('unit_id');
            $table->uuid('reservation_id')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->enum('task_type', ['checkout_cleaning', 'checkin_preparation', 'turnover', 'deep_cleaning', 'inspection'])->default('checkout_cleaning');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'verified'])->default('pending');
            $table->date('scheduled_date');
            $table->time('scheduled_time')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('estimated_duration_minutes')->nullable();
            $table->json('checklist')->nullable();
            $table->json('before_photos')->nullable();
            $table->json('after_photos')->nullable();
            $table->text('notes')->nullable();
            $table->text('issues_found')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->index(['status', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_tasks');
    }
};
