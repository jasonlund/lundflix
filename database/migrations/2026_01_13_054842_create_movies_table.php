<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('imdb_id')->unique();
            $table->string('title');
            $table->year('year')->nullable();
            $table->smallInteger('runtime')->unsigned()->nullable();
            $table->string('genres')->nullable();
            $table->timestamps();

            $table->index('title');
            $table->index('year');

            if (DB::getDriverName() !== 'sqlite') {
                $table->fullText('title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
