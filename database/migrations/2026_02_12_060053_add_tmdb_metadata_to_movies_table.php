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
            $table->string('original_title')->nullable()->after('alternative_titles');
            $table->string('tagline')->nullable()->after('original_title');
            $table->string('status')->nullable()->after('tagline');
            $table->unsignedBigInteger('budget')->nullable()->after('status');
            $table->unsignedBigInteger('revenue')->nullable()->after('budget');
            $table->json('origin_country')->nullable()->after('revenue');
            $table->json('release_dates')->nullable()->after('origin_country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn([
                'original_title',
                'tagline',
                'status',
                'budget',
                'revenue',
                'origin_country',
                'release_dates',
            ]);
        });
    }
};
