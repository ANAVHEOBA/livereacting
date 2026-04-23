<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('folder_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->enum('type', ['video', 'audio', 'image'])->default('video');
            $table->enum('source', ['upload', 'google_drive', 'dropbox', 'youtube'])->default('upload');
            $table->text('source_url')->nullable();
            $table->string('storage_path')->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('resolution', 20)->nullable();
            $table->string('format', 50)->nullable();
            $table->string('codec', 50)->nullable();
            $table->enum('status', ['importing', 'processing', 'ready', 'failed'])->default('ready');
            $table->integer('encoding_progress')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'type', 'status']);
            $table->index('folder_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
