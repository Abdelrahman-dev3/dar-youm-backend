<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('unit_id');
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone');
            $table->integer('guest_count')->default(1);
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->integer('nights');
            $table->decimal('price_per_night', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('cleaning_fee', 10, 2)->default(0);
            $table->decimal('service_fee', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->enum('booking_source', ['airbnb', 'booking_com', 'agoda', 'vrbo', 'direct', 'other'])->default('direct');
            $table->string('booking_reference')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid');
            $table->text('special_requests')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
            $table->index(['unit_id', 'check_in_date', 'check_out_date']);
            $table->index(['status', 'booking_source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
