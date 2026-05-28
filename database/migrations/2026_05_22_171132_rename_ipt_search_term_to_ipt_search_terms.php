<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table): void {
            $table->json('ipt_search_terms')->nullable()->after('name');
        });

        Schema::table('movies', function (Blueprint $table): void {
            $table->json('ipt_search_terms')->nullable()->after('title');
        });

        DB::table('shows')
            ->whereNotNull('ipt_search_term')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('shows')
                    ->where('id', $row->id)
                    ->update(['ipt_search_terms' => json_encode([$row->ipt_search_term])]);
            });

        Schema::table('shows', function (Blueprint $table): void {
            $table->dropColumn('ipt_search_term');
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table): void {
            $table->string('ipt_search_term')->nullable()->after('name');
        });

        DB::table('shows')
            ->whereNotNull('ipt_search_terms')
            ->orderBy('id')
            ->each(function (object $row): void {
                $terms = json_decode($row->ipt_search_terms, true);

                if (! is_array($terms) || $terms === []) {
                    return;
                }

                DB::table('shows')
                    ->where('id', $row->id)
                    ->update(['ipt_search_term' => $terms[0]]);
            });

        Schema::table('shows', function (Blueprint $table): void {
            $table->dropColumn('ipt_search_terms');
        });

        Schema::table('movies', function (Blueprint $table): void {
            $table->dropColumn('ipt_search_terms');
        });
    }
};
