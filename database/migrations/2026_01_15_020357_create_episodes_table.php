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
        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('tvmaze_id')->unique();
            $table->unsignedInteger('season');
            $table->unsignedInteger('number')->nullable();
            $table->string('name');
            $table->string('type')->default('regular');
            $table->date('airdate')->nullable();
            $table->string('airtime')->nullable();
            $table->unsignedInteger('runtime')->nullable();
            $table->json('rating')->nullable();
            $table->json('image')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index(['show_id', 'season', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};
