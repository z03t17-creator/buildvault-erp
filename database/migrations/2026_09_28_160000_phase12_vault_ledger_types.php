<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 — real business transaction ledger fields (additive).
 * Type values stay free-form strings; new business types are app-level constants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->date('occurred_on')->nullable()->after('type');
            $table->string('reference_code', 120)->nullable()->after('description');
            $table->index(['vault_id', 'occurred_on']);
            $table->index(['type', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['vault_id', 'occurred_on']);
            $table->dropIndex(['type', 'occurred_on']);
            $table->dropColumn(['occurred_on', 'reference_code']);
        });
    }
};
