<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->unsignedInteger('tmdb_id')->nullable()->index()->after('thetvdb_id');
            $table->timestamp('tmdb_synced_at')->nullable()->after('tmdb_id');
            $table->text('overview')->nullable()->after('web_channel');
            $table->text('tagline')->nullable()->after('overview');
            $table->string('original_name')->nullable()->after('tagline');
            $table->string('original_language')->nullable()->after('original_name');
            $table->json('spoken_languages')->nullable()->after('original_language');
            $table->json('production_companies')->nullable()->after('spoken_languages');
            $table->json('origin_country')->nullable()->after('production_companies');
            $table->json('content_ratings')->nullable()->after('origin_country');
            $table->json('alternative_titles')->nullable()->after('content_ratings');
            $table->string('homepage')->nullable()->after('alternative_titles');
            $table->boolean('in_production')->nullable()->after('homepage');
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn([
                'tmdb_id',
                'tmdb_synced_at',
                'overview',
                'tagline',
                'original_name',
                'original_language',
                'spoken_languages',
                'production_companies',
                'origin_country',
                'content_ratings',
                'alternative_titles',
                'homepage',
                'in_production',
            ]);
        });
    }
};
