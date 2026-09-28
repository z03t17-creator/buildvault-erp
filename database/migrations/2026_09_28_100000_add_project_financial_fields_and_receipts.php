<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5: project contract/financial fields + money-received receipts.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('client')->nullable()->after('name');
            $table->string('contract_number')->nullable()->after('location');
            $table->decimal('contract_value_iqd', 18, 2)->default(0)->after('total_budget_usd');
            $table->decimal('budget_iqd', 18, 2)->default(0)->after('contract_value_iqd');
        });

        Schema::create('project_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_iqd', 18, 2);
            $table->decimal('amount_usd', 18, 2)->default(0);
            $table->decimal('exchange_rate', 18, 4)->default(0);
            $table->date('received_on');
            $table->string('source')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_receipts');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'client',
                'contract_number',
                'contract_value_iqd',
                'budget_iqd',
            ]);
        });
    }
};
