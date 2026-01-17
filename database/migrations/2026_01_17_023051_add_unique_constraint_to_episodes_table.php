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
        Schema::table('episodes', function (Blueprint $table) {
            // Make number NOT NULL (specials will have assigned numbers during import)
            $table->unsignedInteger('number')->nullable(false)->change();

            // Add unique constraint on (show_id, season, type, number)
            // This allows S24E01 (regular) and S24S01 (special) to coexist
            // Note: We keep the existing (show_id, season, number) index as MySQL uses it for the foreign key
            $table->unique(['show_id', 'season', 'type', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropUnique(['show_id', 'season', 'type', 'number']);
            $table->unsignedInteger('number')->nullable()->change();
        });
    }
};
