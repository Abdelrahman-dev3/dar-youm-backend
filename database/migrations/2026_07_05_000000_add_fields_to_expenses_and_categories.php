<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add fields to expense_categories table
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('name_ar')->comment('Icon name from lucide-vue-next');
            $table->string('color')->nullable()->after('icon')->comment('Color code (slate, teal, blue, green, orange, red, purple)');
        });

        // Add fields to expenses table
        Schema::table('expenses', function (Blueprint $table) {
            $table->uuid('unit_id')->nullable()->after('property_id')->comment('Optional unit reference');
            $table->string('payment_method')->default('cash')->after('currency')->comment('Payment method: cash, card, bank_transfer, check');
            $table->string('supplier')->nullable()->after('payment_method')->comment('Supplier or vendor name');
            
            // Add foreign key for unit_id
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'payment_method', 'supplier']);
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn(['icon', 'color']);
        });
    }
};
