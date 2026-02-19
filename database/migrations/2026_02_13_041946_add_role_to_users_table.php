<?php

use App\Models\User;
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->after('plex_thumb');
        });

        $seedToken = config('services.plex.seed_token');

        if ($seedToken) {
            User::query()
                ->whereNotNull('plex_token')
                ->each(function (User $user) use ($seedToken): void {
                    if ($user->plex_token === $seedToken) {
                        $user->update(['role' => 'admin']);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
