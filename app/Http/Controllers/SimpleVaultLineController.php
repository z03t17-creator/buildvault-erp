<?php

namespace App\Http\Controllers;

use App\Models\ApartmentUnit;
use App\Models\BuildingBlock;
use App\Models\Project;
use App\Models\Staff;
use App\Models\StaffRate;
use App\Models\Vault;
use App\Models\VaultLine;
use App\Services\SimpleVaultService;
use App\Support\DualCurrency;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Simple-vault money forms — advance, expense, staff pay, salary.
 */
class SimpleVaultLineController extends Controller
{
    public function __construct(
        private readonly SimpleVaultService $vault,
    ) {}

    /**
     * پارەی ستاف roster — daily / unit / legacy job_pay.
     */
    public function indexJobPay(): Response
    {
        $this->authorize('viewLedger', Vault::class);

        $lines = VaultLine::query()
            ->staffPays()
            ->with([
                'staff:id,name,kind,pay_model,role,trade',
                'project:id,name',
                'items',
            ])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (VaultLine $line) => $this->mapStaffPayLine($line));

        return Inertia::render('Vault/Simple/JobPayIndex', [
            'lines' => $lines,
            'canCreate' => Gate::allows('manageLedger', Vault::class),
            'canConfirmHold' => Gate::allows('manageLedger', Vault::class),
        ]);
    }

    public function confirmJobPayHold(VaultLine $line): RedirectResponse
    {
        $this->authorize('manageLedger', Vault::class);

        try {
            $this->vault->releaseStaffHold($line);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['hold' => $e->getMessage()]);
        }

        return back()->with('success', __('job_pay_hold_confirmed'));
    }

    public function createAdvance(): Response
    {
        $this->authorize('manageLedger', Vault::class);

        return Inertia::render('Vault/Simple/AdvanceForm', $this->formShared());
    }

    public function storeAdvance(Request $request): RedirectResponse
    {
        $this->authorize('manageLedger', Vault::class);

        $data = $request->validate([
            'occurred_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', Rule::in(DualCurrency::CURRENCIES)],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->vault->postAdvance([
                ...$data,
                'created_by' => $request->user()?->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('dashboards.vault')
            ->with('success', __('vault_advance_saved'));
    }

    public function createExpense(): Response
    {
        $this->authorize('manageLedger', Vault::class);

        return Inertia::render('Vault/Simple/ExpenseForm', $this->formShared());
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $this->authorize('manageLedger', Vault::class);

        $data = $request->validate([
            'occurred_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', Rule::in(DualCurrency::CURRENCIES)],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'expense_type' => ['nullable', 'string', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->vault->postExpense([
                ...$data,
                'created_by' => $request->user()?->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('dashboards.vault')
            ->with('success', __('vault_expense_saved'));
    }

    /** Unified staff payment form (monthly / daily / unit). */
    public function createStaffPay(Request $request): Response
    {
        $this->authorize('manageLedger', Vault::class);

        $preselect = (int) $request->query('staff_id', 0) ?: null;

        return Inertia::render('Vault/Simple/StaffPayForm', $this->staffPayShared($preselect));
    }

    public function storeStaffPay(Request $request): RedirectResponse
    {
        $this->authorize('manageLedger', Vault::class);

        $data = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'occurred_on' => ['required', 'date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'note' => ['nullable', 'string', 'max:500'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'apply_insurance' => ['sometimes', 'boolean'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', Rule::in(DualCurrency::CURRENCIES)],
            'days_count' => ['nullable', 'numeric', 'gt:0'],
            'day_rate' => ['nullable', 'numeric', 'gt:0'],
            'site_kind' => ['nullable', Rule::in(VaultLine::SITE_KINDS)],
            'block' => ['nullable', 'string', 'max:64'],
            'zone' => ['nullable', 'string', 'max:64'],
            'floor' => ['nullable', 'string', 'max:64'],
            'apartment_number' => ['nullable', 'string', 'max:64'],
            'apartment_model' => ['nullable', 'string', 'max:64'],
            'villa_number' => ['nullable', 'string', 'max:64'],
            'area' => ['nullable', 'string', 'max:64'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.staff_rate_id' => ['nullable', 'integer', 'exists:staff_rates,id'],
            'items.*.item_name' => ['nullable', 'string', 'max:160'],
            'items.*.unit' => ['nullable', 'string', 'max:32'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.currency' => ['nullable', Rule::in(DualCurrency::CURRENCIES)],
        ]);

        $staff = Staff::query()->findOrFail($data['staff_id']);
        $payload = [
            ...$data,
            'created_by' => $request->user()?->id,
        ];

        try {
            if ($staff->isMonthly()) {
                $this->vault->postSalary([
                    ...$payload,
                    'apply_insurance' => $request->boolean('apply_insurance', false),
                    'month' => $data['occurred_on'],
                ]);
            } elseif ($staff->isDaily()) {
                $hasDays = isset($data['days_count']) && (float) $data['days_count'] > 0;
                if ($hasDays) {
                    $this->vault->postDailyPay([
                        ...$payload,
                        'apply_insurance' => $request->boolean('apply_insurance', true),
                    ]);
                } else {
                    // Legacy job_pay: amount + currency (no days).
                    $this->vault->postJobPay([
                        ...$payload,
                        'apply_insurance' => $request->boolean('apply_insurance', true),
                    ]);
                }
            } else {
                $items = $data['items'] ?? [];
                $hasItems = is_array($items) && collect($items)->contains(function ($row) {
                    return is_array($row)
                        && (float) ($row['quantity'] ?? 0) > 0
                        && trim((string) ($row['item_name'] ?? '')) !== '';
                });

                if ($hasItems) {
                    if (! $this->userCanEditRate($request)) {
                        $payload['items'] = $this->lockUnitRatesToStaff($staff, $items);
                    }
                    $this->vault->postUnitPayWithItems([
                        ...$payload,
                        'apply_insurance' => $request->boolean('apply_insurance', true),
                    ]);
                } elseif (isset($data['quantity']) && (float) $data['quantity'] > 0) {
                    // Legacy single-quantity unit pay.
                    $this->vault->postUnitPay([
                        ...$payload,
                        'quantity' => $data['quantity'],
                        'apply_insurance' => $request->has('apply_insurance')
                            ? $request->boolean('apply_insurance')
                            : true,
                    ]);
                } else {
                    throw new InvalidArgumentException('Unit pay needs item rows or a quantity.');
                }
            }
        } catch (InvalidArgumentException $e) {
            $field = $staff->isMonthly()
                ? 'amount'
                : ($staff->isDaily() ? 'amount' : 'quantity');

            return back()->withErrors([$field => $e->getMessage()])->withInput();
        }

        if ($staff->isMonthly()) {
            return redirect()
                ->route('dashboards.vault')
                ->with('success', __('vault_salary_saved'));
        }

        return redirect()
            ->route('vault.job-pay.index')
            ->with('success', __('vault_staff_pay_saved'));
    }

    public function createJobPay(Request $request): Response
    {
        return $this->createStaffPay($request);
    }

    public function storeJobPay(Request $request): RedirectResponse
    {
        return $this->storeStaffPay($request);
    }

    public function createUnitPay(Request $request): Response
    {
        return $this->createStaffPay($request);
    }

    public function storeUnitPay(Request $request): RedirectResponse
    {
        return $this->storeStaffPay($request);
    }

    public function createSalary(Request $request): Response
    {
        return $this->createStaffPay($request);
    }

    public function storeSalary(Request $request): RedirectResponse
    {
        return $this->storeStaffPay($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function staffPayShared(?int $preselectStaffId): array
    {
        $staff = Staff::query()
            ->with('rates')
            ->orderBy('name')
            ->get()
            ->map(function (Staff $person) {
                $dues = $person->isMonthly()
                    ? $this->vault->salaryDueFor($person, now())
                    : null;

                return [
                    'id' => $person->id,
                    'name' => $person->name,
                    'role' => $person->role ?: $person->trade,
                    'pay_model' => $person->resolvePayModel(),
                    'kind' => $person->kind,
                    'monthly_salary' => $person->monthly_salary !== null
                        ? (float) $person->monthly_salary
                        : null,
                    'day_rate' => $person->day_rate !== null
                        ? (float) $person->day_rate
                        : null,
                    'currency' => $person->currency,
                    'salary_due' => $dues,
                    'rates' => $person->rates->map(fn (StaffRate $rate) => [
                        'id' => $rate->id,
                        'item_name' => $rate->item_name,
                        'unit' => $rate->unit,
                        'rate' => (float) $rate->rate,
                        'currency' => $rate->currency,
                    ])->values(),
                ];
            });

        $user = request()->user();
        $snapshot = $this->vault->dashboardSnapshot();
        $salaryDues = [];
        foreach ($staff as $person) {
            if (($person['pay_model'] ?? null) === Staff::PAY_MONTHLY && $person['salary_due'] !== null) {
                $salaryDues[$person['id']] = $person['salary_due'];
            }
        }

        return [
            ...$this->formShared(),
            'staff' => $staff,
            'preselectStaffId' => $preselectStaffId,
            'canEditRate' => $this->userCanEditRate(request()),
            'siteKinds' => VaultLine::SITE_KINDS,
            'placeSuggestions' => $this->placeSuggestions(),
            'itemSuggestions' => Staff::suggestedItemNames(),
            'rateUnitSuggestions' => Staff::suggestedRateUnits(),
            'userRole' => $user?->getRoleNames()->first(),
            'salaryDues' => $salaryDues,
            'estimates' => $snapshot['estimates'],
            'availableCash' => $snapshot['available_cash'],
        ];
    }

    /**
     * Prior pay locations plus the project's blocks and apartments.
     * The form filters these into a villa or building cascade.
     *
     * @return list<array<string, mixed>>
     */
    private function placeSuggestions(): array
    {
        $rows = [];

        $lines = VaultLine::query()
            ->whereNotNull('site_kind')
            ->get([
                'project_id',
                'site_kind',
                'block',
                'zone',
                'floor',
                'apartment_number',
                'apartment_model',
                'villa_number',
                'area',
            ]);

        foreach ($lines as $line) {
            $rows[] = $this->placeRow(
                $line->project_id,
                $line->site_kind,
                $line->block,
                $line->zone,
                $line->floor,
                $line->apartment_number,
                $line->apartment_model,
                $line->villa_number,
                $line->area,
            );
        }

        $blocks = BuildingBlock::query()->with('tower:id,name')->get();
        foreach ($blocks as $block) {
            $rows[] = $this->placeRow(
                $block->project_id,
                VaultLine::SITE_BUILDING,
                $block->code,
                $block->tower?->name,
            );
        }

        $units = ApartmentUnit::query()
            ->with(['buildingBlock.tower:id,name', 'tower:id,name', 'floor:id,name'])
            ->get();
        foreach ($units as $unit) {
            $floor = $unit->floor?->name;
            if ($floor === null && $unit->floor_number !== null) {
                $floor = (string) $unit->floor_number;
            }
            $rows[] = $this->placeRow(
                $unit->project_id,
                VaultLine::SITE_BUILDING,
                $unit->buildingBlock?->code,
                $unit->tower?->name ?: $unit->buildingBlock?->tower?->name,
                $floor,
                $unit->unit_label,
            );
        }

        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $filled = array_filter([
                $row['block'],
                $row['zone'],
                $row['floor'],
                $row['apartment_number'],
                $row['apartment_model'],
                $row['villa_number'],
                $row['area'],
            ], fn ($value) => $value !== null && $value !== '');
            if ($filled === []) {
                continue;
            }
            $key = implode('|', [
                $row['project_id'] ?? '',
                $row['site_kind'] ?? '',
                ...array_map(
                    fn ($value) => mb_strtolower(trim((string) $value)),
                    [
                        $row['block'],
                        $row['zone'],
                        $row['floor'],
                        $row['apartment_number'],
                        $row['apartment_model'],
                        $row['villa_number'],
                        $row['area'],
                    ],
                ),
            ]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function placeRow(
        mixed $projectId,
        ?string $siteKind,
        ?string $block = null,
        ?string $zone = null,
        ?string $floor = null,
        ?string $apartmentNumber = null,
        ?string $apartmentModel = null,
        ?string $villaNumber = null,
        ?string $area = null,
    ): array {
        $clean = function (mixed $value): ?string {
            $value = trim((string) ($value ?? ''));

            return $value === '' ? null : $value;
        };

        return [
            'project_id' => $projectId ? (int) $projectId : null,
            'site_kind' => $clean($siteKind),
            'block' => $clean($block),
            'zone' => $clean($zone),
            'floor' => $clean($floor),
            'apartment_number' => $clean($apartmentNumber),
            'apartment_model' => $clean($apartmentModel),
            'villa_number' => $clean($villaNumber),
            'area' => $clean($area),
        ];
    }

    private function userCanEditRate(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::BOSS_CONTRACTOR,
            Roles::ACCOUNTANT,
        ]);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function lockUnitRatesToStaff(Staff $staff, array $items): array
    {
        $staff->loadMissing('rates');
        $byId = $staff->rates->keyBy('id');
        $out = [];

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rateId = isset($row['staff_rate_id']) ? (int) $row['staff_rate_id'] : 0;
            $rate = $rateId ? $byId->get($rateId) : null;
            if (! $rate) {
                continue;
            }
            $out[] = [
                'staff_rate_id' => $rate->id,
                'item_name' => $rate->item_name,
                'unit' => $rate->unit,
                'quantity' => $row['quantity'] ?? 0,
                'unit_rate' => (float) $rate->rate,
                'currency' => $rate->currency,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapStaffPayLine(VaultLine $line): array
    {
        return [
            'id' => $line->id,
            'kind' => $line->kind,
            'occurred_on' => $line->occurred_on?->toDateString(),
            'amount' => (float) $line->amount,
            'currency' => $line->currency,
            'purpose' => $line->purpose,
            'hold_amount' => (float) $line->hold_amount,
            'unlock_date' => $line->unlock_date?->toDateString(),
            'hold_released_at' => $line->hold_released_at
                ? $line->hold_released_at->toDateTimeString()
                : null,
            'hold_open' => $line->holdIsOpen(),
            'site_kind' => $line->site_kind,
            'villa_number' => $line->villa_number,
            'block' => $line->block,
            'apartment_number' => $line->apartment_number,
            'staff' => $line->staff ? [
                'id' => $line->staff->id,
                'name' => $line->staff->name,
            ] : null,
            'project' => $line->project ? [
                'id' => $line->project->id,
                'name' => $line->project->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formShared(): array
    {
        return [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'currencies' => DualCurrency::CURRENCIES,
            'kinds' => VaultLine::KINDS,
            'today' => now()->toDateString(),
            'staff' => Staff::query()
                ->orderBy('name')
                ->get(['id', 'name', 'kind', 'pay_model', 'role', 'trade']),
        ];
    }
}
