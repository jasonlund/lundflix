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
        Schema::dropIfExists('plex_webhook_events');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('plex_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('server_uuid');
            $table->string('server_name')->nullable();
            $table->string('media_type');
            $table->string('title');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('show_title')->nullable();
            $table->unsignedSmallInteger('season')->nullable();
            $table->unsignedSmallInteger('episode_number')->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['server_uuid', 'processed_at']);
        });
    }
};
