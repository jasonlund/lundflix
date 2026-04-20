<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('requests', function (Blueprint $table): void {
                $table->dropIndex('requests_user_id_status_index');
            });
        }

        Schema::table('requests', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->string('status')->default('pending')->after('user_id');
            $table->index(['user_id', 'status'], 'requests_user_id_status_index');
        });
    }
};
