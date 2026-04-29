<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streaming_destinations', function (Blueprint $table) {
            $table->foreignId('connected_account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('connected_accounts')
                ->nullOnDelete();
            $table->json('metadata')->nullable()->after('token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('streaming_destinations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('connected_account_id');
            $table->dropColumn('metadata');
        });
    }
};
