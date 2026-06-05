<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->uuid('owner_id')->nullable();
            $table->string('unit_number');
            $table->string('unit_name')->nullable();
            $table->string('unit_name_ar')->nullable();
            $table->string('unit_type'); // studio, 1br, 2br, villa, etc.
            $table->integer('bedrooms')->default(0);
            $table->integer('bathrooms')->default(0);
            $table->decimal('size_sqm', 8, 2)->nullable();
            $table->integer('max_guests')->default(2);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->enum('status', ['available', 'occupied', 'cleaning', 'maintenance', 'blocked'])->default('available');
            $table->string('floor_number')->nullable();
            $table->json('amenities')->nullable();
            $table->json('images')->nullable();
            $table->text('cleaning_notes')->nullable();
            $table->text('maintenance_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['property_id', 'status']);
            $table->unique(['property_id', 'unit_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
