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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('mediable');
            $table->string('fanart_id');
            $table->string('type');
            $table->string('url');
            $table->string('lang')->nullable();
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedSmallInteger('season')->nullable();
            $table->string('disc')->nullable();
            $table->string('disc_type')->nullable();
            $table->timestamps();

            $table->unique(['mediable_type', 'mediable_id', 'fanart_id']);
            $table->index(['mediable_type', 'mediable_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
