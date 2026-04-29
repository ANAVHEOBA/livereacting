<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactive_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('prompt')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_visible')->default(false);
            $table->unsignedInteger('sort_order')->default(1);
            $table->json('settings')->nullable();
            $table->json('results')->nullable();
            $table->timestamps();
        });

        Schema::create('interactive_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interactive_element_id')->constrained()->cascadeOnDelete();
            $table->string('participant_name')->nullable();
            $table->string('response_key')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactive_responses');
        Schema::dropIfExists('interactive_elements');
    }
};
