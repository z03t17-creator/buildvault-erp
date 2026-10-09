<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18 — harden monthly_settlements uniqueness for NULL project_id.
 *
 * Older installs created UNIQUE(vault_id, year_month, project_id). SQL treats
 * NULL as distinct in unique indexes, so multiple all-projects snapshots could
 * coexist. Fresh installs already get project_scope_key from the create
 * migration; this migration is idempotent for both paths.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('monthly_settlements')) {
            return;
        }

        if (! Schema::hasColumn('monthly_settlements', 'project_scope_key')) {
            Schema::table('monthly_settlements', function (Blueprint $table) {
                $table->unsignedBigInteger('project_scope_key')->default(0)->after('project_id');
            });
        }

        // Backfill scope key from project_id (0 = all projects).
        DB::table('monthly_settlements')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('monthly_settlements')->where('id', $row->id)->update([
                    'project_scope_key' => $row->project_id === null ? 0 : (int) $row->project_id,
                ]);
            }
        });

        $this->dedupeSettlements();

        // vault_id FK may rely on the old unique as its supporting index — add
        // an explicit index before dropping uniqueness on MySQL/MariaDB.
        if (! $this->hasIndex('monthly_settlements', 'monthly_settlements_vault_id_index')) {
            Schema::table('monthly_settlements', function (Blueprint $table) {
                $table->index('vault_id', 'monthly_settlements_vault_id_index');
            });
        }

        if ($this->hasIndex('monthly_settlements', 'monthly_settlements_vault_month_project_uq')) {
            Schema::table('monthly_settlements', function (Blueprint $table) {
                $table->dropUnique('monthly_settlements_vault_month_project_uq');
            });
        }

        if (! $this->hasIndex('monthly_settlements', 'monthly_settlements_vault_month_scope_uq')) {
            Schema::table('monthly_settlements', function (Blueprint $table) {
                $table->unique(
                    ['vault_id', 'year_month', 'project_scope_key'],
                    'monthly_settlements_vault_month_scope_uq'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('monthly_settlements')) {
            return;
        }

        // Do not drop project_scope_key or the scope unique here: the create
        // migration (Phase 18) owns that canonical shape on fresh installs.
        // Rolling this migration back only removes the helper vault_id index
        // when the scope unique is still present to support the vault FK.
        if (
            $this->hasIndex('monthly_settlements', 'monthly_settlements_vault_id_index')
            && $this->hasIndex('monthly_settlements', 'monthly_settlements_vault_month_scope_uq')
        ) {
            Schema::table('monthly_settlements', function (Blueprint $table) {
                $table->dropIndex('monthly_settlements_vault_id_index');
            });
        }
    }

    /**
     * Keep the newest snapshot per vault/month/scope; drop older duplicates.
     *
     * Use havingRaw(COUNT(*)) — Laravel's having('c', …) quotes the alias,
     * which PostgreSQL rejects (SQLSTATE 42703). SQLite/MySQL accept either.
     */
    private function dedupeSettlements(): void
    {
        $duplicates = DB::table('monthly_settlements')
            ->select('vault_id', 'year_month', 'project_scope_key', DB::raw('MAX(id) as keep_id'))
            ->groupBy('vault_id', 'year_month', 'project_scope_key')
            ->havingRaw('COUNT(*) > ?', [1])
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('monthly_settlements')
                ->where('vault_id', $dup->vault_id)
                ->where('year_month', $dup->year_month)
                ->where('project_scope_key', $dup->project_scope_key)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA index_list('{$table}')");

            foreach ($rows as $row) {
                $name = is_object($row) ? ($row->name ?? null) : ($row['name'] ?? null);
                if ($name === $indexName) {
                    return true;
                }
            }

            return false;
        }

        // PostgreSQL: information_schema.statistics is extended stats, not indexes.
        if ($driver === 'pgsql') {
            $rows = $connection->select(
                'SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ? LIMIT 1',
                [$table, $indexName]
            );

            return count($rows) > 0;
        }

        // MySQL / MariaDB
        $rows = $connection->select(
            'SELECT INDEX_NAME FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return count($rows) > 0;
    }
};
