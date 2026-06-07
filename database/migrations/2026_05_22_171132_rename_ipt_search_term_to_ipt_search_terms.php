<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shows', 'ipt_search_terms')) {
            Schema::table('shows', function (Blueprint $table): void {
                $table->json('ipt_search_terms')->nullable()->after('name');
            });
        }

        if (! Schema::hasColumn('movies', 'ipt_search_terms')) {
            Schema::table('movies', function (Blueprint $table): void {
                $table->json('ipt_search_terms')->nullable()->after('title');
            });
        }

        if (Schema::hasColumn('shows', 'ipt_search_term') && Schema::hasColumn('shows', 'ipt_search_terms')) {
            DB::transaction(function (): void {
                DB::table('shows')
                    ->whereNotNull('ipt_search_term')
                    ->orderBy('id')
                    ->each(function (object $row): void {
                        DB::table('shows')
                            ->where('id', $row->id)
                            ->update(['ipt_search_terms' => json_encode([$row->ipt_search_term])]);
                    });
            });
        }

        if (Schema::hasColumn('shows', 'ipt_search_term')) {
            Schema::table('shows', function (Blueprint $table): void {
                $table->dropColumn('ipt_search_term');
            });
        }
    }

    /**
     * Rollback restores the singular shows.ipt_search_term from the first element of
     * each JSON array before dropping the renamed columns.
     *
     * WARNING: movies.ipt_search_terms has no predecessor column. movies never had an
     * ipt_search_term, so rollback permanently discards all movie search-term data with
     * no recovery path. Back up the movies table before rolling back if that data matters.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('shows', 'ipt_search_term')) {
            Schema::table('shows', function (Blueprint $table): void {
                $table->string('ipt_search_term')->nullable()->after('name');
            });
        }

        if (Schema::hasColumn('shows', 'ipt_search_terms') && Schema::hasColumn('shows', 'ipt_search_term')) {
            DB::transaction(function (): void {
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
            });
        }

        if (Schema::hasColumn('shows', 'ipt_search_terms')) {
            Schema::table('shows', function (Blueprint $table): void {
                $table->dropColumn('ipt_search_terms');
            });
        }

        if (Schema::hasColumn('movies', 'ipt_search_terms')) {
            Schema::table('movies', function (Blueprint $table): void {
                $table->dropColumn('ipt_search_terms');
            });
        }
    }
};
