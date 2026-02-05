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
        Schema::table('request_items', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('requestable_id');
            $table->foreignId('actioned_by')->nullable()->constrained('users')->after('status');
            $table->timestamp('actioned_at')->nullable()->after('actioned_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->dropForeign(['actioned_by']);
            $table->dropColumn(['status', 'actioned_by', 'actioned_at']);
        });
    }
};
