<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('certificate_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('subject');
            $table->longText('rendered_html')->nullable();
            $table->enum('status', [
                'pending',
                'sent',
                'error'
            ])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};