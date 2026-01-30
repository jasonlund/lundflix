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
        Schema::create('plex_media_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_identifier')->unique();
            $table->text('access_token');
            $table->boolean('owned')->default(false);
            $table->boolean('is_online')->default(false);
            $table->boolean('visible')->default(false);
            $table->string('uri')->nullable();
            $table->json('connections')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plex_media_servers');
    }
};
