<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 21 / Refactor Phase 1 — dual-currency Qasa vault, people kinds,
 * client+staff retention layers, spatial units, soft deletes.
 * Postgres + SQLite safe (no DB-specific DDL).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Transactions: running balances + soft delete (Qasa-shaped dual columns) ---
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'balance_after_usd')) {
                $table->decimal('balance_after_usd', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('transactions', 'balance_after_iqd')) {
                $table->decimal('balance_after_iqd', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('transactions', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // --- Soft deletes on money entities ---
        foreach ([
            'expenses',
            'payouts',
            'penalties',
            'employee_advances',
            'retention_holds',
            'project_receipts',
        ] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->softDeletes();
                });
            }
        }

        // --- People: labor_kind + staff/worker pay fields ---
        Schema::table('workers', function (Blueprint $table) {
            if (! Schema::hasColumn('workers', 'labor_kind')) {
                $table->string('labor_kind', 32)->default('unclassified');
            }
            if (! Schema::hasColumn('workers', 'rate_unit')) {
                $table->string('rate_unit', 32)->nullable(); // m2|item|matxal|door|…
            }
            if (! Schema::hasColumn('workers', 'rate_currency')) {
                $table->string('rate_currency', 3)->nullable(); // USD|IQD
            }
            if (! Schema::hasColumn('workers', 'unit_rate')) {
                $table->decimal('unit_rate', 18, 4)->nullable();
            }
            if (! Schema::hasColumn('workers', 'monthly_salary_usd')) {
                $table->decimal('monthly_salary_usd', 18, 2)->default(0);
            }
            if (! Schema::hasColumn('workers', 'monthly_salary_iqd')) {
                $table->decimal('monthly_salary_iqd', 18, 2)->default(0);
            }
            if (! Schema::hasColumn('workers', 'classified_at')) {
                $table->timestamp('classified_at')->nullable();
            }
            if (! Schema::hasColumn('workers', 'classified_by')) {
                $table->foreignId('classified_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('workers', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (! $this->indexExists('workers', 'workers_labor_kind_index')) {
            Schema::table('workers', function (Blueprint $table) {
                $table->index('labor_kind');
            });
        }

        // --- Dual columns on advances + staff retention ---
        Schema::table('employee_advances', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_advances', 'amount_usd')) {
                $table->decimal('amount_usd', 18, 2)->default(0);
            }
            if (! Schema::hasColumn('employee_advances', 'remaining_usd')) {
                $table->decimal('remaining_usd', 18, 2)->default(0);
            }
            if (! Schema::hasColumn('employee_advances', 'currency')) {
                $table->string('currency', 3)->default('IQD');
            }
        });

        Schema::table('retention_holds', function (Blueprint $table) {
            if (! Schema::hasColumn('retention_holds', 'amount_iqd')) {
                $table->decimal('amount_iqd', 18, 2)->default(0);
            }
            if (! Schema::hasColumn('retention_holds', 'released_amount_iqd')) {
                $table->decimal('released_amount_iqd', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('retention_holds', 'layer')) {
                // staff = company→staff work-pay lock; kept for clarity alongside client_retention_holds
                $table->string('layer', 32)->default('staff');
            }
            if (! Schema::hasColumn('retention_holds', 'maturity_days')) {
                $table->unsignedSmallInteger('maturity_days')->default(180);
            }
        });

        // Penalties: ensure dual amounts default safely (amount_iqd already nullable from Phase 8)
        Schema::table('penalties', function (Blueprint $table) {
            if (! Schema::hasColumn('penalties', 'currency')) {
                $table->string('currency', 3)->nullable();
            }
        });

        // --- Staff statements (earned / paid / remaining dual) ---
        if (! Schema::hasTable('staff_statements')) {
            Schema::create('staff_statements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->string('period', 7)->nullable(); // Y-m optional period bucket
                $table->string('label')->nullable();
                $table->decimal('earned_usd', 18, 2)->default(0);
                $table->decimal('earned_iqd', 18, 2)->default(0);
                $table->decimal('paid_usd', 18, 2)->default(0);
                $table->decimal('paid_iqd', 18, 2)->default(0);
                $table->decimal('remaining_usd', 18, 2)->default(0);
                $table->decimal('remaining_iqd', 18, 2)->default(0);
                $table->decimal('retention_held_usd', 18, 2)->default(0);
                $table->decimal('retention_held_iqd', 18, 2)->default(0);
                $table->decimal('advances_usd', 18, 2)->default(0);
                $table->decimal('advances_iqd', 18, 2)->default(0);
                $table->decimal('penalties_usd', 18, 2)->default(0);
                $table->decimal('penalties_iqd', 18, 2)->default(0);
                $table->string('status', 32)->default('open');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['worker_id', 'status']);
                $table->index(['project_id', 'period']);
            });
        }

        // --- Client advances (building client → company) ---
        if (! Schema::hasTable('client_advances')) {
            Schema::create('client_advances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vault_id')->nullable()->constrained()->nullOnDelete();
                $table->string('client_name')->nullable();
                $table->decimal('amount_usd', 18, 2)->default(0);
                $table->decimal('amount_iqd', 18, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->date('received_on');
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['project_id', 'received_on']);
            });
        }

        // --- Client retention holds (10% / 180 days on client advances) ---
        if (! Schema::hasTable('client_retention_holds')) {
            Schema::create('client_retention_holds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_advance_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vault_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount_usd', 18, 2)->default(0);
                $table->decimal('amount_iqd', 18, 2)->default(0);
                $table->decimal('hold_pct', 5, 2)->default(10);
                $table->unsignedSmallInteger('maturity_days')->default(180);
                $table->date('hold_start');
                $table->date('maturity_date');
                $table->string('status', 32)->default('holding'); // holding|matured|released
                $table->timestamp('released_at')->nullable();
                $table->decimal('released_amount_usd', 18, 2)->nullable();
                $table->decimal('released_amount_iqd', 18, 2)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
                $table->index('maturity_date');
                $table->index(['project_id', 'status']);
            });
        }

        // --- Spatial: building blocks + apartment units ---
        if (! Schema::hasTable('building_blocks')) {
            Schema::create('building_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tower_id')->nullable()->constrained()->nullOnDelete();
                $table->string('code', 64); // B1, B3, …
                $table->string('name')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['project_id', 'code']);
            });
        }

        if (! Schema::hasTable('apartment_units')) {
            Schema::create('apartment_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('building_block_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('tower_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('floor_id')->nullable()->constrained()->nullOnDelete();
                $table->string('unit_label', 64); // apartment / villa number
                $table->unsignedSmallInteger('floor_number')->nullable();
                $table->string('category', 32); // mdf|laminate|metxal|packet|entrance
                $table->string('status', 32)->default('pending'); // pending|in_progress|done|company
                $table->foreignId('assigned_worker_id')->nullable()->constrained('workers')->nullOnDelete();
                $table->boolean('is_company_crew')->default(false); // xoman / خۆمان
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['project_id', 'category']);
                $table->index(['building_block_id', 'floor_number']);
                $table->index(['floor_id', 'unit_label']);
            });
        }

        // --- Attendance ready for Workers (Stock Manager writer) ---
        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'project_id')) {
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('attendances', 'entered_by')) {
                $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('attendances', 'shift_start')) {
                $table->time('shift_start')->nullable();
            }
            if (! Schema::hasColumn('attendances', 'forfeit_day')) {
                $table->boolean('forfeit_day')->default(false);
            }
            if (! Schema::hasColumn('attendances', 'penalty_id')) {
                $table->foreignId('penalty_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('attendances', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (! Schema::hasColumn('attendances', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apartment_units');
        Schema::dropIfExists('building_blocks');
        Schema::dropIfExists('client_retention_holds');
        Schema::dropIfExists('client_advances');
        Schema::dropIfExists('staff_statements');

        Schema::table('attendances', function (Blueprint $table) {
            foreach (['project_id', 'entered_by', 'shift_start', 'forfeit_day', 'penalty_id', 'notes', 'deleted_at'] as $col) {
                if (Schema::hasColumn('attendances', $col)) {
                    if (in_array($col, ['project_id', 'entered_by', 'penalty_id'], true)) {
                        try {
                            $table->dropConstrainedForeignId($col);
                        } catch (\Throwable) {
                            $table->dropColumn($col);
                        }
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });

        Schema::table('retention_holds', function (Blueprint $table) {
            foreach (['amount_iqd', 'released_amount_iqd', 'layer', 'maturity_days', 'deleted_at'] as $col) {
                if (Schema::hasColumn('retention_holds', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('employee_advances', function (Blueprint $table) {
            foreach (['amount_usd', 'remaining_usd', 'currency', 'deleted_at'] as $col) {
                if (Schema::hasColumn('employee_advances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('penalties', function (Blueprint $table) {
            if (Schema::hasColumn('penalties', 'currency')) {
                $table->dropColumn('currency');
            }
            if (Schema::hasColumn('penalties', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });

        Schema::table('workers', function (Blueprint $table) {
            if (Schema::hasColumn('workers', 'classified_by')) {
                try {
                    $table->dropConstrainedForeignId('classified_by');
                } catch (\Throwable) {
                    $table->dropColumn('classified_by');
                }
            }
            foreach ([
                'labor_kind', 'rate_unit', 'rate_currency', 'unit_rate',
                'monthly_salary_usd', 'monthly_salary_iqd', 'classified_at', 'deleted_at',
            ] as $col) {
                if (Schema::hasColumn('workers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        foreach (['expenses', 'payouts', 'project_receipts'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('deleted_at');
                });
            }
        }

        Schema::table('transactions', function (Blueprint $table) {
            foreach (['balance_after_usd', 'balance_after_iqd', 'deleted_at'] as $col) {
                if (Schema::hasColumn('transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                if (($row->name ?? '') === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $indexName]
            );

            return count($rows) > 0;
        }

        // MySQL / others — best-effort
        try {
            $db = Schema::getConnection()->getDatabaseName();
            $rows = DB::select(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$db, $table, $indexName]
            );

            return count($rows) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
};
