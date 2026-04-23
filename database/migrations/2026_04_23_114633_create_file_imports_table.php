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
        Schema::create('file_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('file_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('source_url');
            $table->enum('type', ['video', 'audio', 'image'])->default('video');
            $table->enum('status', ['pending', 'downloading', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('progress')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_imports');
    }
};
