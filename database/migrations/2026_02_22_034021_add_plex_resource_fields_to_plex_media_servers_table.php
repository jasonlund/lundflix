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
        Schema::table('plex_media_servers', function (Blueprint $table) {
            $table->string('source_title')->nullable()->after('connections');
            $table->string('owner_thumb')->nullable()->after('source_title');
            $table->string('owner_id')->nullable()->after('owner_thumb');
            $table->string('product_version')->nullable()->after('owner_id');
            $table->string('platform')->nullable()->after('product_version');
            $table->string('platform_version')->nullable()->after('platform');
            $table->timestamp('plex_last_seen_at')->nullable()->after('platform_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plex_media_servers', function (Blueprint $table) {
            $table->dropColumn([
                'source_title',
                'owner_thumb',
                'owner_id',
                'product_version',
                'platform',
                'platform_version',
                'plex_last_seen_at',
            ]);
        });
    }
};
