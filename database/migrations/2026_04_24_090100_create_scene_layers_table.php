<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scene_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('file_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['video', 'audio', 'image', 'text', 'countdown', 'overlay']);
            $table->string('name')->nullable();
            $table->text('content')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_visible')->default(true);
            $table->json('position')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['scene_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scene_layers');
    }
};
