<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('participant_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('template_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('pdf_path')->nullable();
            $table->longText('rendered_html')->nullable();
            $table->enum('status', [
                'pending',
                'generated',
                'sent',
                'error',
                'resent',
                'cancelled'
            ])->default('pending');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};