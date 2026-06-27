<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->uuid('owner_id')->nullable()->after('user_id');
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['owner_id', 'status']);
        });

        $ownerIds = DB::table('users')
            ->where('role', 'owner')
            ->pluck('id')
            ->all();

        if (!empty($ownerIds)) {
            DB::table('properties')
                ->whereIn('user_id', $ownerIds)
                ->update(['owner_id' => DB::raw('user_id')]);
        }
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropIndex(['owner_id', 'status']);
            $table->dropColumn('owner_id');
        });
    }
};
