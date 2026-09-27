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
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('role', 32)->default('laborer');
            $table->decimal('daily_rate_usd', 18, 2)->default(0);
            $table->decimal('overtime_rate_usd', 18, 2)->default(0);
            $table->decimal('spending_limit_usd', 18, 2)->default(0);
            $table->string('phone')->nullable();
            $table->string('national_id_number')->nullable();
            $table->string('avatar_path')->nullable();
            $table->timestamps();

            $table->index('role');
            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
