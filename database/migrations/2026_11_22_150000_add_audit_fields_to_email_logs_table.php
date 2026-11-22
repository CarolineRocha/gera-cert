<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->string('recipient')->nullable()->after('subject');
            $table->foreignId('sent_by_user_id')
                ->nullable()
                ->after('participant_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropForeign(['sent_by_user_id']);
            $table->dropColumn(['recipient', 'sent_by_user_id']);
        });
    }
};
