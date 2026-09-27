<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->default('full'); // full|database|files
            $table->string('filename')->nullable();
            $table->string('disk', 64)->default('backups');
            $table->string('location')->nullable(); // relative path on disk
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status', 32)->default('pending'); // pending|running|completed|failed
            $table->text('message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
