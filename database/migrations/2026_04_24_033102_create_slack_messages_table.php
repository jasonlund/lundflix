<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slack_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->string('message_ts');
            $table->string('type');
            $table->text('content');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['channel', 'message_ts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_messages');
    }
};
