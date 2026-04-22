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
        Schema::table('plex_media_servers', function (Blueprint $table): void {
            $table->boolean('poll_recently_added')->default(false)->after('visible');
        });
    }

    public function down(): void
    {
        Schema::table('plex_media_servers', function (Blueprint $table): void {
            $table->dropColumn('poll_recently_added');
        });
    }
};
