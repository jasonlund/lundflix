<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique('media_mediable_type_mediable_id_fanart_id_unique');

            $table->renameColumn('fanart_id', 'file_path');

            $table->dropColumn(['disc', 'disc_type', 'url', 'likes']);

            $table->float('vote_average')->default(0)->after('lang');
            $table->unsignedInteger('vote_count')->default(0)->after('vote_average');
            $table->unsignedInteger('width')->nullable()->after('vote_count');
            $table->unsignedInteger('height')->nullable()->after('width');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->unique(['mediable_type', 'mediable_id', 'file_path']);
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique(['mediable_type', 'mediable_id', 'file_path']);

            $table->renameColumn('file_path', 'fanart_id');

            $table->dropColumn(['vote_average', 'vote_count', 'width', 'height']);

            $table->string('url');
            $table->unsignedInteger('likes')->default(0);
            $table->string('disc')->nullable();
            $table->string('disc_type')->nullable();
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->unique(['mediable_type', 'mediable_id', 'fanart_id']);
        });
    }
};
