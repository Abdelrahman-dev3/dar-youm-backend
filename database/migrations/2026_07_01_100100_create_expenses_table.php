<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->uuid('expense_category_id');
            $table->uuid('created_by')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->string('currency', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
            $table->foreign('expense_category_id')->references('id')->on('expense_categories');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['property_id', 'expense_date']);
            $table->index(['expense_category_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
