<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('owner_reference_number')->nullable()->unique()->after('role');
        });

        $owners = DB::table('users')
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id']);

        foreach ($owners as $index => $owner) {
            DB::table('users')
                ->where('id', $owner->id)
                ->update(['owner_reference_number' => $index + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['owner_reference_number']);
            $table->dropColumn('owner_reference_number');
        });
    }
};
