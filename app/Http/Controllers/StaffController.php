<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffRate;
use App\Models\Vault;
use App\Models\VaultLine;
use App\Support\DualCurrency;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Staff roster, profile, create, and edit with pay models and unit rates.
 */
class StaffController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewLedger', Vault::class);

        $staff = Staff::query()
            ->withCount('rates')
            ->orderBy('name')
            ->get()
            ->map(fn (Staff $person) => $this->mapStaff($person));

        return Inertia::render('Staff/Index', [
            'staff' => $staff,
            'canCreate' => Gate::allows('manageLedger', Vault::class),
        ]);
    }

    public function show(Staff $staff): Response
    {
        $this->authorize('viewLedger', Vault::class);

        $staff->load(['rates']);

        $pays = VaultLine::query()
            ->where('staff_id', $staff->id)
            ->whereIn('kind', [
                VaultLine::KIND_JOB_PAY,
                VaultLine::KIND_DAILY_PAY,
                VaultLine::KIND_UNIT_PAY,
                VaultLine::KIND_SALARY,
            ])
            ->with(['project:id,name', 'items'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (VaultLine $line) => $this->mapPayLine($line));

        $canManage = Gate::allows('manageLedger', Vault::class);

        return Inertia::render('Staff/Show', [
            'staff' => $this->mapStaff($staff, true),
            'rates' => $staff->rates->map(fn (StaffRate $rate) => $this->mapRate($rate))->values(),
            'payments' => $pays,
            'jobPays' => $pays->whereIn('kind', [
                VaultLine::KIND_JOB_PAY,
                VaultLine::KIND_DAILY_PAY,
                VaultLine::KIND_UNIT_PAY,
            ])->values(),
            'salaries' => $pays->where('kind', VaultLine::KIND_SALARY)->values(),
            'canCreateJobPay' => $canManage && ($staff->isDaily() || $staff->isUnit()),
            'canCreateUnitPay' => $canManage && $staff->isUnit(),
            'canCreateDailyPay' => $canManage && $staff->isDaily(),
            'canCreateSalary' => $canManage && $staff->isMonthly(),
            'canPay' => $canManage,
            'canEdit' => $canManage,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('manageLedger', Vault::class);

        return Inertia::render('Staff/Create', [
            'payModels' => Staff::PAY_MODELS,
            'kinds' => Staff::PAY_MODELS,
            'currencies' => DualCurrency::CURRENCIES,
            'roleSuggestions' => Staff::suggestedRoles(),
            'rateUnitSuggestions' => Staff::suggestedRateUnits(),
            'itemSuggestions' => Staff::suggestedItemNames(),
            'returnTo' => $request->string('return')->toString() ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageLedger', Vault::class);

        $payModel = $request->input('pay_model', $request->input('kind'));
        $payModel = Staff::payModelFromKind(is_string($payModel) ? $payModel : null);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'role' => ['nullable', 'string', 'max:120'],
            'trade' => ['nullable', 'string', 'max:120'],
            'pay_model' => ['nullable', 'string'],
            'kind' => ['nullable', 'string'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'day_rate' => ['nullable', 'numeric', 'min:0'],
            'unit_rate' => ['nullable', 'numeric', 'min:0'],
            'rate_unit' => ['nullable', 'string', 'max:32'],
            'currency' => ['nullable', Rule::in(DualCurrency::CURRENCIES)],
            'rates' => ['nullable', 'array'],
            'rates.*.item_name' => ['nullable', 'string', 'max:160'],
            'rates.*.unit' => ['nullable', 'string', 'max:32'],
            'rates.*.rate' => ['nullable', 'numeric', 'min:0'],
            'rates.*.currency' => ['nullable', Rule::in(DualCurrency::CURRENCIES)],
            'return_to' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $person = $this->persistStaff($data, $payModel);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['pay_model' => $e->getMessage()])->withInput();
        }

        $return = $data['return_to'] ?? null;
        if (is_string($return) && $return !== '' && str_starts_with($return, '/')) {
            return redirect($return)->with('success', __('staff_created'));
        }

        return redirect()
            ->route('staff.show', $person)
            ->with('success', __('staff_created'));
    }

    public function edit(Staff $staff): Response
    {
        $this->authorize('manageLedger', Vault::class);

        $staff->load('rates');

        return Inertia::render('Staff/Create', [
            'staff' => $this->mapStaff($staff, true),
            'payModels' => Staff::PAY_MODELS,
            'kinds' => Staff::PAY_MODELS,
            'currencies' => DualCurrency::CURRENCIES,
            'roleSuggestions' => Staff::suggestedRoles(),
            'rateUnitSuggestions' => Staff::suggestedRateUnits(),
            'itemSuggestions' => Staff::suggestedItemNames(),
        ]);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('manageLedger', Vault::class);

        $payModel = $request->input('pay_model', $request->input('kind'));
        $payModel = Staff::payModelFromKind(is_string($payModel) ? $payModel : null);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'role' => ['nullable', 'string', 'max:120'],
            'trade' => ['nullable', 'string', 'max:120'],
            'pay_model' => ['nullable', 'string'],
            'kind' => ['nullable', 'string'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'day_rate' => ['nullable', 'numeric', 'min:0'],
            'unit_rate' => ['nullable', 'numeric', 'min:0'],
            'rate_unit' => ['nullable', 'string', 'max:32'],
            'currency' => ['nullable', Rule::in(DualCurrency::CURRENCIES)],
            'rates' => ['nullable', 'array'],
            'rates.*.item_name' => ['nullable', 'string', 'max:160'],
            'rates.*.unit' => ['nullable', 'string', 'max:32'],
            'rates.*.rate' => ['nullable', 'numeric', 'min:0'],
            'rates.*.currency' => ['nullable', Rule::in(DualCurrency::CURRENCIES)],
        ]);

        try {
            $person = $this->persistStaff($data, $payModel, $staff);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['pay_model' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('staff.show', $person)
            ->with('success', __('staff_updated'));
    }

    public function destroy(Staff $staff): RedirectResponse
    {
        $this->authorize('manageLedger', Vault::class);

        $staff->delete();

        return redirect()
            ->route('staff.index')
            ->with('success', __('staff_deleted'));
    }

    /**
     * Create or replace a staff record and, for unit pay, their whole price list.
     *
     * @param  array<string, mixed>  $data
     */
    private function persistStaff(array $data, string $payModel, ?Staff $existing = null): Staff
    {
        $role = trim((string) ($data['role'] ?? $data['trade'] ?? ''));

        return DB::transaction(function () use ($data, $payModel, $role, $existing) {
            $attrs = [
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'role' => $role !== '' ? $role : null,
                'pay_model' => $payModel,
                'kind' => Staff::kindFromPayModel($payModel),
                'monthly_salary' => null,
                'day_rate' => null,
                'currency' => null,
                'unit_rate' => null,
                'rate_unit' => null,
            ];

            if ($payModel === Staff::PAY_MONTHLY) {
                $salary = (float) ($data['monthly_salary'] ?? 0);
                if ($salary <= 0) {
                    throw new InvalidArgumentException('Monthly salary is required.');
                }
                $attrs['monthly_salary'] = $salary;
                $attrs['currency'] = $data['currency'] ?? null;
            } elseif ($payModel === Staff::PAY_DAILY) {
                $dayRate = (float) ($data['day_rate'] ?? 0);
                // UI requires day_rate; legacy kind=time may omit it.
                $attrs['day_rate'] = $dayRate > 0 ? $dayRate : null;
                $attrs['currency'] = $data['currency'] ?? null;
            }

            if ($existing) {
                $existing->fill($attrs);
                $existing->save();
                $person = $existing;
            } else {
                $person = Staff::query()->create($attrs);
            }

            // Price lists belong only to unit staff. Replacing them keeps one current catalog.
            $person->rates()->delete();

            if ($payModel !== Staff::PAY_UNIT) {
                return $person->fresh();
            }

            $rates = is_array($data['rates'] ?? null) ? $data['rates'] : [];
            $saved = 0;
            foreach (array_values($rates) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $item = trim((string) ($row['item_name'] ?? ''));
                $unit = trim((string) ($row['unit'] ?? ''));
                $rate = round((float) ($row['rate'] ?? 0), 4);
                $currency = strtoupper((string) ($row['currency'] ?? ''));
                if ($item === '' || $unit === '' || $rate <= 0) {
                    continue;
                }
                if (! in_array($currency, DualCurrency::CURRENCIES, true)) {
                    throw new InvalidArgumentException('Each rate needs USD or IQD.');
                }
                StaffRate::query()->create([
                    'staff_id' => $person->id,
                    'item_name' => $item,
                    'unit' => $unit,
                    'rate' => $rate,
                    'currency' => $currency,
                    'sort_order' => $saved,
                ]);
                $saved++;
                if ($person->currency === null) {
                    $person->currency = $currency;
                    $person->save();
                }
            }

            // Legacy single unit_rate + rate_unit → one staff_rates row.
            if ($saved === 0) {
                $legacyRate = round((float) ($data['unit_rate'] ?? 0), 4);
                $legacyUnit = trim((string) ($data['rate_unit'] ?? ''));
                $legacyCurrency = strtoupper((string) ($data['currency'] ?? ''));
                if ($legacyRate > 0 && $legacyUnit !== '' && in_array($legacyCurrency, DualCurrency::CURRENCIES, true)) {
                    StaffRate::query()->create([
                        'staff_id' => $person->id,
                        'item_name' => 'کار',
                        'unit' => $legacyUnit,
                        'rate' => $legacyRate,
                        'currency' => $legacyCurrency,
                        'sort_order' => 0,
                    ]);
                    $person->unit_rate = $legacyRate;
                    $person->rate_unit = $legacyUnit;
                    $person->currency = $legacyCurrency;
                    $person->save();
                    $saved = 1;
                }
            }

            if ($saved === 0) {
                throw new InvalidArgumentException('Unit staff need at least one rate row.');
            }

            return $person->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapStaff(Staff $person, bool $withRates = false): array
    {
        $payload = [
            'id' => $person->id,
            'name' => $person->name,
            'phone' => $person->phone,
            'role' => $person->role ?: $person->trade,
            'trade' => $person->role ?: $person->trade,
            'pay_model' => $person->resolvePayModel(),
            'kind' => $person->kind,
            'monthly_salary' => $person->monthly_salary !== null
                ? (float) $person->monthly_salary
                : null,
            'day_rate' => $person->day_rate !== null
                ? (float) $person->day_rate
                : null,
            'currency' => $person->currency,
            'unit_rate' => $person->unit_rate !== null
                ? (float) $person->unit_rate
                : null,
            'rate_unit' => $person->rate_unit,
            'rates_count' => (int) ($person->rates_count ?? $person->rates()->count()),
        ];

        if ($withRates) {
            $payload['rates'] = $person->rates
                ->map(fn (StaffRate $rate) => $this->mapRate($rate))
                ->values();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRate(StaffRate $rate): array
    {
        return [
            'id' => $rate->id,
            'item_name' => $rate->item_name,
            'unit' => $rate->unit,
            'rate' => (float) $rate->rate,
            'currency' => $rate->currency,
            'sort_order' => (int) $rate->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPayLine(VaultLine $line): array
    {
        return [
            'id' => $line->id,
            'kind' => $line->kind,
            'occurred_on' => $line->occurred_on?->toDateString(),
            'amount' => (float) $line->amount,
            'currency' => $line->currency,
            'purpose' => $line->purpose,
            'note' => $line->note,
            'hold_amount' => (float) $line->hold_amount,
            'unlock_date' => $line->unlock_date?->toDateString(),
            'hold_released_at' => $line->hold_released_at?->toDateTimeString(),
            'hold_open' => $line->holdIsOpen(),
            'site_kind' => $line->site_kind,
            'block' => $line->block,
            'zone' => $line->zone,
            'floor' => $line->floor,
            'apartment_number' => $line->apartment_number,
            'apartment_model' => $line->apartment_model,
            'villa_number' => $line->villa_number,
            'area' => $line->area,
            'days_count' => $line->days_count !== null ? (float) $line->days_count : null,
            'day_rate' => $line->day_rate !== null ? (float) $line->day_rate : null,
            'items' => $line->relationLoaded('items')
                ? $line->items->map(fn ($item) => [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'unit' => $item->unit,
                    'quantity' => (float) $item->quantity,
                    'unit_rate' => (float) $item->unit_rate,
                    'subtotal' => (float) $item->subtotal,
                ])->values()
                : [],
            'project' => $line->project ? [
                'id' => $line->project->id,
                'name' => $line->project->name,
            ] : null,
        ];
    }
}
