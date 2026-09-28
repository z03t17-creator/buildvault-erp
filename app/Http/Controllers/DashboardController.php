<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\Project;
use App\Models\RetentionHold;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\ExchangeRateService;
use App\Services\LiquidityService;
use App\Services\RetentionHoldService;
use App\Support\AuditActions;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function __construct(
        private readonly RetentionHoldService $holds,
        private readonly ExchangeRateService $fx,
        private readonly LiquidityService $liquidity,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = $this->primaryRole($user);
        $rate = $this->fx->getUsdToIqd();

        $maturedHolds = collect();
        if ($user && ($user->can('viewRetention', Vault::class) || $user->can('manageRetention', Vault::class))) {
            $maturedHolds = $this->holds->maturedAwaitingRelease()->map(function ($hold) use ($rate) {
                $hold->setAttribute('amount_iqd', round((float) $hold->amount_usd * $rate, 0));

                return $hold;
            });
        }

        return Inertia::render('Dashboard', [
            'roleHome' => $role,
            'maturedHolds' => $maturedHolds,
            'summary' => $this->summaryForRole($role, $rate),
        ]);
    }

    private function primaryRole(?User $user): string
    {
        if (! $user) {
            return 'guest';
        }

        foreach (Roles::ALL as $name) {
            if ($user->hasRole($name)) {
                return $name;
            }
        }

        return 'unknown';
    }

    /**
     * Lightweight, real counts/snapshots — no fake stock numbers.
     *
     * @return array<string, mixed>
     */
    private function summaryForRole(string $role, float $rate): array
    {
        return match ($role) {
            Roles::SUPER_ADMIN => $this->superAdminSummary(),
            Roles::BOSS_CONTRACTOR => $this->bossFinancialSnapshot($rate),
            Roles::ACCOUNTANT => $this->accountantOpsSummary($rate),
            Roles::STOCK_MANAGER => [
                'stock_module' => 'coming_soon',
                'placeholder' => true,
            ],
            default => [],
        };
    }

    /**
     * @return array<string, int>
     */
    private function superAdminSummary(): array
    {
        return [
            'users' => User::query()->count(),
            'projects' => Project::query()->count(),
            'workers' => Worker::query()->count(),
            'pending_payouts' => Payout::query()->where('status', Payout::STATUS_PENDING)->count(),
            'matured_holds' => RetentionHold::query()->where('status', RetentionHold::STATUS_MATURED)->count(),
            'audit_events' => $this->auditEventCount(),
        ];
    }

    /**
     * @return array<string, float|int|null>
     */
    private function bossFinancialSnapshot(float $rate): array
    {
        $vault = Vault::query()->where('name', VaultSeeder::NAME)->first()
            ?? Vault::query()->orderBy('id')->first();

        if (! $vault) {
            return [
                'vault_balance_iqd' => null,
                'available_iqd' => null,
                'pending_payouts_iqd' => null,
                'reserved_insurance_iqd' => null,
                'projects' => Project::query()->count(),
                'workers' => Worker::query()->count(),
                'pending_payouts_count' => Payout::query()->where('status', Payout::STATUS_PENDING)->count(),
            ];
        }

        $toIqd = static fn (float $usd): float => round($usd * $rate, 0);

        return [
            'vault_balance_iqd' => $toIqd((float) $vault->balance_usd),
            'available_iqd' => $toIqd($this->liquidity->availableUsd($vault)),
            'pending_payouts_iqd' => $toIqd($this->liquidity->pendingPayoutsUsd($vault)),
            'reserved_insurance_iqd' => $toIqd($this->liquidity->reservedInsuranceUsd($vault)),
            'projects' => Project::query()->count(),
            'workers' => Worker::query()->count(),
            'pending_payouts_count' => Payout::query()->where('status', Payout::STATUS_PENDING)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function accountantOpsSummary(float $rate): array
    {
        unset($rate);

        return [
            'pending_payouts' => Payout::query()->where('status', Payout::STATUS_PENDING)->count(),
            'matured_holds' => RetentionHold::query()->where('status', RetentionHold::STATUS_MATURED)->count(),
            'projects' => Project::query()->count(),
            'workers' => Worker::query()->count(),
        ];
    }

    private function auditEventCount(): int
    {
        if (! Schema::hasTable('activity_log')) {
            return 0;
        }

        return Activity::query()
            ->where('log_name', AuditActions::LOG_NAME)
            ->count();
    }
}
