<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_episode', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('episode_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['subscription_id', 'episode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_episode');
    }
};
