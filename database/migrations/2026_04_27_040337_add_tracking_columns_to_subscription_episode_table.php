<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_episode', function (Blueprint $table): void {
            $table->timestamp('notified_at')->nullable()->after('episode_id');
            $table->timestamp('requested_at')->nullable()->after('notified_at');
        });

        DB::table('subscription_episode')->update([
            'notified_at' => DB::raw('created_at'),
            'requested_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('subscription_episode', function (Blueprint $table): void {
            $table->dropColumn(['notified_at', 'requested_at']);
        });
    }
};
