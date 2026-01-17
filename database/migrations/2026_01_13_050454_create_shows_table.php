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
        Schema::create('shows', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tvmaze_id')->unique();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('language')->nullable();
            $table->json('genres')->nullable();
            $table->string('status')->nullable();
            $table->unsignedSmallInteger('runtime')->nullable();
            $table->date('premiered')->nullable();
            $table->date('ended')->nullable();
            $table->string('official_site')->nullable();
            $table->json('schedule')->nullable();
            $table->json('rating')->nullable();
            $table->unsignedSmallInteger('weight')->nullable();
            $table->json('network')->nullable();
            $table->json('web_channel')->nullable();
            $table->json('externals')->nullable();
            $table->json('image')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedInteger('updated_at_tvmaze')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shows');
    }
};
