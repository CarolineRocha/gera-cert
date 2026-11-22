<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        $adminId = DB::table('users')->where('is_admin', true)->orderBy('id')->value('id');
        if ($adminId) {
            DB::table('projects')->whereNull('user_id')->update(['user_id' => $adminId]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
