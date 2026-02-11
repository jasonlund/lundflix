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
        Schema::table('movies', function (Blueprint $table) {
            $table->unsignedInteger('tmdb_id')->nullable()->after('num_votes');
            $table->date('release_date')->nullable()->after('tmdb_id');
            $table->json('production_companies')->nullable()->after('release_date');
            $table->json('spoken_languages')->nullable()->after('production_companies');
            $table->string('original_language', 10)->nullable()->after('spoken_languages');
            $table->timestamp('tmdb_synced_at')->nullable()->after('original_language');

            $table->index('tmdb_id');
            $table->index('tmdb_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropIndex(['tmdb_id']);
            $table->dropIndex(['tmdb_synced_at']);
            $table->dropColumn([
                'tmdb_id',
                'release_date',
                'production_companies',
                'spoken_languages',
                'original_language',
                'tmdb_synced_at',
            ]);
        });
    }
};
