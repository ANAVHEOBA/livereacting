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
        Schema::create('streaming_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['youtube', 'facebook', 'twitch', 'rtmp'])->default('rtmp');
            $table->string('name');
            $table->string('platform_id')->nullable(); // YouTube channel ID, Facebook page ID, etc.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('rtmp_url')->nullable(); // For custom RTMP
            $table->string('stream_key')->nullable(); // For custom RTMP
            $table->boolean('is_valid')->default(true);
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('streaming_destinations');
    }
};
