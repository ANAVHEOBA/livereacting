<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('status')->default('draft');
            $table->unsignedInteger('max_guests')->default(1);
            $table->text('host_notes')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('guest_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('role')->default('guest');
            $table->string('token')->unique();
            $table->string('status')->default('pending');
            $table->json('permissions')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('guest_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_invite_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->string('role')->default('guest');
            $table->string('connection_status')->default('joined');
            $table->json('media_state')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_sessions');
        Schema::dropIfExists('guest_invites');
        Schema::dropIfExists('guest_rooms');
    }
};
