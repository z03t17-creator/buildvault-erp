<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link Worker records to login users (Phase 5.1 — own-data read-only).
     */
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('project_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
