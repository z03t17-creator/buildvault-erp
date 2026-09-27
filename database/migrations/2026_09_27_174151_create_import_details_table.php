<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status', 16)->default('pending'); // pending|valid|invalid|imported|skipped|rolled_back
            $table->json('payload')->nullable();
            $table->json('errors')->nullable();
            $table->nullableMorphs('record'); // linked model after successful import (4.6)
            $table->timestamps();

            $table->index(['import_id', 'status']);
            $table->unique(['import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_details');
    }
};
