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
        Schema::table('users', function (Blueprint $table) {
            $table->string('plex_id')->unique()->nullable()->after('id');
            $table->text('plex_token')->nullable()->after('plex_id');
            $table->string('plex_username')->nullable()->after('plex_token');
            $table->string('plex_thumb')->nullable()->after('plex_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['plex_id', 'plex_token', 'plex_username', 'plex_thumb']);
        });
    }
};
