<?php

namespace App\Support;

/**
 * Phase 15 — professional report catalog (no attendance).
 */
final class ReportTypes
{
    public const CATEGORY_FINANCIAL = 'financial';

    public const CATEGORY_PAYROLL = 'payroll';

    public const CATEGORY_STOCK = 'stock';

    public const CATEGORY_PROJECT = 'project';

    public const ACCESS_FINANCIAL = 'financial';

    public const ACCESS_STOCK = 'stock';

    public const PROJECT_FINANCIAL = 'project_financial';

    public const INCOME_RECEIVED = 'income_received';

    public const EXPENSE = 'expense';

    public const PROFIT_LOSS = 'profit_loss';

    public const VAULT_LEDGER = 'vault_ledger';

    public const MONTHLY_PAYROLL = 'monthly_payroll';

    public const EMPLOYEE_SALARY = 'employee_salary';

    public const PENALTY = 'penalty';

    public const INSURANCE_RETENTION = 'insurance_retention';

    public const ADVANCE = 'advance';

    public const INVENTORY = 'inventory';

    public const STOCK_MOVEMENT = 'stock_movement';

    public const STOCK_VALUATION = 'stock_valuation';

    public const STOCK_IN_OUT = 'stock_in_out';

    public const LOW_STOCK = 'low_stock';

    public const PROJECT_PROGRESS = 'project_progress';

    public const PROJECT_EXPENSES = 'project_expenses';

    public const PROJECT_MATERIALS = 'project_materials';

    public const WORKER_PRODUCTION = 'worker_production';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array<string, array{
     *     category: string,
     *     access: string,
     *     filters: list<string>,
     *     exports: list<string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            self::PROJECT_FINANCIAL => [
                'category' => self::CATEGORY_FINANCIAL,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['project_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::INCOME_RECEIVED => [
                'category' => self::CATEGORY_FINANCIAL,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['from', 'to', 'project_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::EXPENSE => [
                'category' => self::CATEGORY_FINANCIAL,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['from', 'to', 'project_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::PROFIT_LOSS => [
                'category' => self::CATEGORY_FINANCIAL,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['from', 'to', 'project_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::VAULT_LEDGER => [
                'category' => self::CATEGORY_FINANCIAL,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['from', 'to', 'project_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::MONTHLY_PAYROLL => [
                'category' => self::CATEGORY_PAYROLL,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['month', 'project_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::EMPLOYEE_SALARY => [
                'category' => self::CATEGORY_PAYROLL,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['month', 'worker_id', 'project_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::PENALTY => [
                'category' => self::CATEGORY_PAYROLL,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['from', 'to', 'project_id', 'worker_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::INSURANCE_RETENTION => [
                'category' => self::CATEGORY_PAYROLL,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['from', 'to', 'project_id', 'worker_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::ADVANCE => [
                'category' => self::CATEGORY_PAYROLL,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['from', 'to', 'project_id', 'worker_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::INVENTORY => [
                'category' => self::CATEGORY_STOCK,
                'access' => self::ACCESS_STOCK,
                'filters' => [],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::STOCK_MOVEMENT => [
                'category' => self::CATEGORY_STOCK,
                'access' => self::ACCESS_STOCK,
                'filters' => ['from', 'to', 'project_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::STOCK_VALUATION => [
                'category' => self::CATEGORY_STOCK,
                'access' => self::ACCESS_STOCK,
                'filters' => [],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::STOCK_IN_OUT => [
                'category' => self::CATEGORY_STOCK,
                'access' => self::ACCESS_STOCK,
                'filters' => ['from', 'to', 'project_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::LOW_STOCK => [
                'category' => self::CATEGORY_STOCK,
                'access' => self::ACCESS_STOCK,
                'filters' => [],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::PROJECT_PROGRESS => [
                'category' => self::CATEGORY_PROJECT,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['from', 'to', 'project_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::PROJECT_EXPENSES => [
                'category' => self::CATEGORY_PROJECT,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['from', 'to', 'project_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::PROJECT_MATERIALS => [
                'category' => self::CATEGORY_PROJECT,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['from', 'to', 'project_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
            self::WORKER_PRODUCTION => [
                'category' => self::CATEGORY_PROJECT,
                'access' => self::ACCESS_FINANCIAL,
                'filters' => ['from', 'to', 'project_id', 'worker_id'],
                'exports' => ['pdf', 'xlsx', 'csv', 'print'],
            ],
        ];
    }

    public static function exists(string $type): bool
    {
        return isset(self::definitions()[$type]);
    }

    /**
     * @return array{category: string, access: string, filters: list<string>, exports: list<string>}|null
     */
    public static function get(string $type): ?array
    {
        return self::definitions()[$type] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_FINANCIAL,
            self::CATEGORY_PAYROLL,
            self::CATEGORY_STOCK,
            self::CATEGORY_PROJECT,
        ];
    }
}
