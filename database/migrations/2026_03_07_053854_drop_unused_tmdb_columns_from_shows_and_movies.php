<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table): void {
            $table->dropColumn([
                'overview',
                'tagline',
                'spoken_languages',
                'production_companies',
                'origin_country',
                'alternative_titles',
                'homepage',
                'in_production',
            ]);
        });

        Schema::table('movies', function (Blueprint $table): void {
            $table->dropColumn([
                'production_companies',
                'spoken_languages',
                'alternative_titles',
                'tagline',
                'budget',
                'revenue',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table): void {
            $table->text('overview')->nullable()->after('web_channel');
            $table->text('tagline')->nullable()->after('overview');
            $table->json('spoken_languages')->nullable()->after('original_language');
            $table->json('production_companies')->nullable()->after('spoken_languages');
            $table->json('origin_country')->nullable()->after('production_companies');
            $table->json('alternative_titles')->nullable()->after('content_ratings');
            $table->string('homepage')->nullable()->after('alternative_titles');
            $table->boolean('in_production')->nullable()->after('homepage');
        });

        Schema::table('movies', function (Blueprint $table): void {
            $table->json('production_companies')->nullable()->after('digital_release_date');
            $table->json('spoken_languages')->nullable()->after('production_companies');
            $table->json('alternative_titles')->nullable()->after('spoken_languages');
            $table->text('tagline')->nullable()->after('original_title');
            $table->unsignedBigInteger('budget')->nullable()->after('status');
            $table->unsignedBigInteger('revenue')->nullable()->after('budget');
        });
    }
};
