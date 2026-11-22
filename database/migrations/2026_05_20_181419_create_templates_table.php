<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {

            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->enum('type', [
                'email',
                'certificate'
            ]);
            $table->integer('version')->default(1);
            $table->longText('html');
            $table->foreignId('related_template_id')
                ->nullable()
                ->constrained('templates')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};