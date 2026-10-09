import DateInput from '@/Components/DateInput';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import InsuranceHoldToggle from '@/Components/InsuranceHoldToggle';
import MoneyAmount from '@/Components/MoneyAmount';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { deskFieldClass, deskMoneyClass, segmentClass } from '@/Components/StaffDesk';
import SuggestionCombobox from '@/Components/SuggestionCombobox';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { isValidIsoDate, todayIsoDate } from '@/lib/isoDate';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const fieldClass = deskFieldClass;
const moneyFieldClass = deskMoneyClass;

function emptyItem(rate = null) {
    return {
        staff_rate_id: rate?.id || '',
        item_name: rate?.item_name || '',
        unit: rate?.unit || 'دانە',
        quantity: '',
        unit_rate: rate?.rate != null ? String(rate.rate) : '',
        currency: rate?.currency || 'IQD',
    };
}

function formFromLine(line, today, preselectStaffId) {
    const items =
        Array.isArray(line?.items) && line.items.length
            ? line.items.map((row) => ({
                  staff_rate_id: row.staff_rate_id || '',
                  item_name: row.item_name || '',
                  unit: row.unit || 'دانە',
                  quantity: row.quantity != null ? String(row.quantity) : '',
                  unit_rate: row.unit_rate != null ? String(row.unit_rate) : '',
                  currency: row.currency || line.currency || 'IQD',
              }))
            : [emptyItem()];

    return {
        staff_id: line?.staff_id
            ? String(line.staff_id)
            : preselectStaffId
              ? String(preselectStaffId)
              : '',
        occurred_on: line?.occurred_on || today || todayIsoDate(),
        project_id: line?.project_id ? String(line.project_id) : '',
        note: line?.note || '',
        purpose: line?.purpose || '',
        apply_insurance: line ? !!line.apply_insurance : true,
        amount: line?.amount != null && line.kind !== 'unit_pay' ? String(line.amount) : '',
        currency: line?.currency || 'IQD',
        days_count: line?.days_count != null ? String(line.days_count) : '',
        day_rate: line?.day_rate != null ? String(line.day_rate) : '',
        site_kind: line?.site_kind || '',
        block: line?.block || '',
        zone: line?.zone || '',
        floor: line?.floor || '',
        apartment_number: line?.apartment_number || '',
        apartment_model: line?.apartment_model || '',
        villa_number: line?.villa_number || '',
        area: line?.area || '',
        items,
    };
}

function holdPreview(amount, applyInsurance) {
    if (!(amount > 0)) return null;
    if (applyInsurance) {
        return {
            hold: Math.round(amount * 0.1 * 100) / 100,
            leaves: Math.round(amount * 0.9 * 100) / 100,
        };
    }
    return { hold: 0, leaves: amount };
}

export default function StaffPayForm({
    projects = [],
    currencies = ['USD', 'IQD'],
    staff = [],
    today,
    preselectStaffId = null,
    canEditRate = false,
    siteKinds = ['villa', 'building'],
    placeSuggestions = [],
    salaryDues = {},
    line = null,
}) {
    const t = useTranslations();
    const [localErrors, setLocalErrors] = useState({});
    const previousStaffId = useRef(line?.id ? String(line.staff_id) : null);

    const { data, setData, post, put, processing, errors } = useForm(
        formFromLine(line, today, preselectStaffId),
    );

    const mergedErrors = useMemo(
        () => ({ ...localErrors, ...errors }),
        [localErrors, errors],
    );

    const selected = staff.find((s) => String(s.id) === String(data.staff_id));
    const payModel = selected?.pay_model || null;
    const isMonthly = payModel === 'monthly';
    const isDaily = payModel === 'daily';
    const isUnit = payModel === 'unit';
    const rates = selected?.rates || [];

    useEffect(() => {
        if (!selected) return;
        if (line?.id && previousStaffId.current === String(data.staff_id)) {
            return;
        }
        previousStaffId.current = String(data.staff_id);

        if (isMonthly) {
            const due =
                salaryDues[selected.id] != null
                    ? salaryDues[selected.id]
                    : selected.salary_due != null
                      ? selected.salary_due
                      : selected.monthly_salary;
            setData({
                ...data,
                staff_id: String(selected.id),
                amount: due != null ? String(due) : '',
                currency: selected.currency || data.currency || 'IQD',
                apply_insurance: false,
                day_rate: '',
                days_count: '',
                items: [emptyItem()],
            });
            return;
        }

        if (isDaily) {
            setData({
                ...data,
                staff_id: String(selected.id),
                day_rate:
                    selected.day_rate != null ? String(selected.day_rate) : '',
                currency: selected.currency || data.currency || 'IQD',
                apply_insurance: true,
                amount: '',
                items: [emptyItem()],
            });
            return;
        }

        if (isUnit) {
            const first = rates[0] || null;
            setData({
                ...data,
                staff_id: String(selected.id),
                apply_insurance: true,
                currency: first?.currency || selected.currency || data.currency || 'IQD',
                amount: '',
                day_rate: '',
                days_count: '',
                items: [emptyItem(first)],
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.staff_id]);

    const dailySubtotal = useMemo(() => {
        const days = Number(data.days_count) || 0;
        const rate = Number(data.day_rate) || 0;
        if (days <= 0 || rate <= 0) return 0;
        return Math.round(days * rate * 100) / 100;
    }, [data.days_count, data.day_rate]);

    const unitRows = useMemo(() => {
        return (data.items || []).map((row) => {
            const qty = Number(row.quantity) || 0;
            const rate = Number(row.unit_rate) || 0;
            const subtotal =
                qty > 0 && rate > 0 ? Math.round(qty * rate * 100) / 100 : 0;
            return { ...row, subtotal };
        });
    }, [data.items]);

    const unitTotal = useMemo(
        () =>
            Math.round(
                unitRows.reduce((sum, row) => sum + (row.subtotal || 0), 0) * 100,
            ) / 100,
        [unitRows],
    );

    const monthlyAmount = Number(data.amount) || 0;
    const previewAmount = isMonthly
        ? monthlyAmount
        : isDaily
          ? dailySubtotal
          : unitTotal;
    const preview = holdPreview(previewAmount, data.apply_insurance);
    const previewCurrency = isUnit
        ? unitRows.find((r) => r.currency)?.currency || data.currency
        : data.currency;

    const setItem = (index, key, value) => {
        const next = data.items.map((row, i) =>
            i === index ? { ...row, [key]: value } : row,
        );
        setData('items', next);
    };

    const pickRate = (index, rateId) => {
        const rate = rates.find((r) => String(r.id) === String(rateId));
        if (!rate) {
            setItem(index, 'staff_rate_id', '');
            return;
        }
        const next = data.items.map((row, i) =>
            i === index
                ? {
                      ...row,
                      staff_rate_id: rate.id,
                      item_name: rate.item_name,
                      unit: rate.unit,
                      unit_rate: String(rate.rate),
                      currency: rate.currency,
                  }
                : row,
        );
        setData('items', next);
        setData('currency', rate.currency);
    };

    const addItem = () => setData('items', [...data.items, emptyItem(rates[0])]);
    const removeItem = (index) => {
        if (data.items.length <= 1) return;
        setData(
            'items',
            data.items.filter((_, i) => i !== index),
        );
    };

    const validate = () => {
        const next = {};
        if (!isValidIsoDate(data.occurred_on)) {
            next.occurred_on = t('validation_date_required');
        }
        if (!data.staff_id) {
            next.staff_id = t('vault_form_pick_staff');
        }
        if (isMonthly) {
            if (!(Number(data.amount) > 0)) {
                next.amount = t('validation_amount_required');
            }
        }
        if (isDaily) {
            const days = Number(data.days_count) || 0;
            const rate = Number(data.day_rate) || 0;
            if (days > 0 || rate > 0) {
                if (!(days > 0)) next.days_count = t('staff_pay_days_required');
                if (!(rate > 0)) next.day_rate = t('validation_amount_required');
            } else if (!(Number(data.amount) > 0)) {
                next.days_count = t('staff_pay_days_required');
            }
        }
        if (isUnit) {
            const ok = unitRows.some((r) => r.subtotal > 0);
            if (!ok) next.items = t('staff_pay_items_required');
            const currenciesUsed = new Set(
                unitRows.filter((r) => r.subtotal > 0).map((r) => r.currency),
            );
            if (currenciesUsed.size > 1) {
                next.items = t('staff_pay_one_currency');
            }
        }
        setLocalErrors(next);
        return Object.keys(next).length === 0;
    };

    const samePlace = (left, right) =>
        String(left ?? '').trim().toLocaleLowerCase() === String(right ?? '').trim().toLocaleLowerCase();

    const inProject = (row) => {
        if (!data.project_id) return true;
        if (row.project_id == null || row.project_id === '') return true;
        return String(row.project_id) === String(data.project_id);
    };

    const placeOptions = (rows, key, predicate) => {
        const seen = new Set();
        const out = [];
        for (const row of rows) {
            if (!predicate(row)) continue;
            const value = String(row[key] ?? '').trim();
            if (!value) continue;
            const id = value.toLocaleLowerCase();
            if (seen.has(id)) continue;
            seen.add(id);
            out.push(value);
        }
        return out;
    };

    const buildingRows = (Array.isArray(placeSuggestions) ? placeSuggestions : []).filter(
        (row) => row.site_kind === 'building' && inProject(row),
    );
    const villaRows = (Array.isArray(placeSuggestions) ? placeSuggestions : []).filter(
        (row) => row.site_kind === 'villa' && inProject(row),
    );
    const blockChosen = (row) => !data.block || samePlace(row.block, data.block);
    const zoneChosen = (row) => !data.zone || samePlace(row.zone, data.zone);
    const floorChosen = (row) => !data.floor || samePlace(row.floor, data.floor);
    const apartmentChosen = (row) =>
        !data.apartment_number || samePlace(row.apartment_number, data.apartment_number);
    const villaChosen = (row) => !data.villa_number || samePlace(row.villa_number, data.villa_number);

    const blockSuggestions = placeOptions(buildingRows, 'block', () => true);
    const buildingZoneSuggestions = placeOptions(buildingRows, 'zone', blockChosen);
    const floorSuggestions = placeOptions(buildingRows, 'floor', (row) => blockChosen(row) && zoneChosen(row));
    const apartmentSuggestions = placeOptions(
        buildingRows,
        'apartment_number',
        (row) => blockChosen(row) && zoneChosen(row) && floorChosen(row),
    );
    const apartmentModelSuggestions = placeOptions(
        buildingRows,
        'apartment_model',
        (row) => blockChosen(row) && zoneChosen(row) && floorChosen(row) && apartmentChosen(row),
    );
    const villaSuggestions = placeOptions(villaRows, 'villa_number', () => true);
    const villaZoneSuggestions = placeOptions(villaRows, 'zone', villaChosen);
    const areaSuggestions = placeOptions(
        villaRows,
        'area',
        (row) => villaChosen(row) && zoneChosen(row),
    );

    const setPlace = (key, value) => {
        const next = { ...data, [key]: value };
        const clear = (keys) => {
            keys.forEach((field) => {
                next[field] = '';
            });
        };
        if (key === 'project_id') {
            clear(['block', 'zone', 'floor', 'apartment_number', 'apartment_model', 'villa_number', 'area']);
        }
        if (key === 'site_kind') {
            clear(['zone']);
            if (value === 'building') clear(['villa_number', 'area']);
            if (value === 'villa') clear(['block', 'floor', 'apartment_number', 'apartment_model']);
        }
        if (key === 'block') clear(['zone', 'floor', 'apartment_number', 'apartment_model']);
        if (key === 'zone' && data.site_kind === 'building') {
            clear(['floor', 'apartment_number', 'apartment_model']);
        }
        if (key === 'zone' && data.site_kind === 'villa') clear(['area']);
        if (key === 'floor') clear(['apartment_number', 'apartment_model']);
        if (key === 'apartment_number') clear(['apartment_model']);
        if (key === 'villa_number') clear(['zone', 'area']);
        setData(next);
    };

    const submit = (e) => {
        e.preventDefault();
        if (!validate()) return;
        if (line?.id) {
            put(route('vault.lines.staff-pay.update', line.id));
            return;
        }
        post(route('vault.lines.staff-pay.store'));
    };

    const itemSelect = (row, index, id) => (
        <select
            id={id}
            className={fieldClass}
            value={row.staff_rate_id}
            onChange={(e) => pickRate(index, e.target.value)}
        >
            <option value="">{t('staff_rate_item')}</option>
            {rates.map((rate) => (
                <option key={rate.id} value={rate.id}>
                    {rate.item_name} ({rate.unit})
                </option>
            ))}
        </select>
    );

    const editing = Boolean(line?.id);
    const title = editing ? t('staff_pay_edit_title') : t('vault_form_job_pay');

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={title}
                    subtitle={editing ? t('staff_pay_edit_hint') : t('staff_pay_form_hint')}
                    icon={
                        <NavIcon
                            name="advances"
                            className="text-lg text-amber-600 dark:text-amber-300"
                        />
                    }
                />
            }
        >
            <Head title={title} />
            <PageShell className="!max-w-6xl !space-y-4">
                <form noValidate onSubmit={submit} className="bv-card space-y-4 p-4 sm:p-5">
                    <FormSection cols={2}>
                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('vault_form_pick_staff')} htmlFor="staff_id" />
                            <select
                                id="staff_id"
                                className={fieldClass}
                                value={data.staff_id}
                                onChange={(e) => setData('staff_id', e.target.value)}
                            >
                                <option value="">{t('vault_form_pick_staff')}</option>
                                {staff.map((person) => (
                                    <option key={person.id} value={person.id}>
                                        {person.name}
                                        {person.role ? ` — ${person.role}` : ''}
                                        {person.pay_model
                                            ? ` (${t(`staff_pay_${person.pay_model}`)})`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                            <InputError message={mergedErrors.staff_id} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('date')} htmlFor="occurred_on" />
                            <DateInput
                                id="occurred_on"
                                className={fieldClass}
                                value={data.occurred_on}
                                onChange={(next) => setData('occurred_on', next)}
                            />
                            <InputError message={mergedErrors.occurred_on} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('project')} htmlFor="project_id" />
                            <select
                                id="project_id"
                                className={fieldClass}
                                value={data.project_id}
                                onChange={(e) => setPlace('project_id', e.target.value)}
                            >
                                <option value="">{t('vault_form_pick_project')}</option>
                                {projects.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                    </FormSection>

                    {/* Place block */}
                    {selected ? (
                        <div className="space-y-3 rounded-xl border border-slate-700 bg-slate-950/40 p-3">
                            <p className="text-sm font-semibold text-slate-100">
                                {t('staff_pay_place')}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {siteKinds.map((kind) => (
                                    <button
                                        key={kind}
                                        type="button"
                                        onClick={() => setPlace('site_kind', kind)}
                                        className={segmentClass(data.site_kind === kind, 'amber')}
                                        aria-pressed={data.site_kind === kind}
                                    >
                                        {t(`staff_pay_site_${kind}`)}
                                    </button>
                                ))}
                            </div>

                            {data.site_kind === 'building' ? (
                                <FormSection cols={2}>
                                    <FormField>
                                        <InputLabel value={t('staff_pay_block')} htmlFor="block" />
                                        <SuggestionCombobox
                                            id="block"
                                            className={fieldClass}
                                            value={data.block}
                                            onChange={(next) => setPlace('block', next)}
                                            suggestions={blockSuggestions}
                                            placeholder="A1"
                                        />
                                    </FormField>
                                    <FormField>
                                        <InputLabel value={t('staff_pay_zone')} htmlFor="zone" />
                                        <SuggestionCombobox
                                            id="zone"
                                            className={fieldClass}
                                            value={data.zone}
                                            onChange={(next) => setPlace('zone', next)}
                                            suggestions={buildingZoneSuggestions}
                                        />
                                    </FormField>
                                    <FormField>
                                        <InputLabel value={t('staff_pay_floor')} htmlFor="floor" />
                                        <SuggestionCombobox
                                            id="floor"
                                            className={fieldClass}
                                            value={data.floor}
                                            onChange={(next) => setPlace('floor', next)}
                                            suggestions={floorSuggestions}
                                        />
                                    </FormField>
                                    <FormField>
                                        <InputLabel
                                            value={t('staff_pay_apartment_number')}
                                            htmlFor="apartment_number"
                                        />
                                        <SuggestionCombobox
                                            id="apartment_number"
                                            className={fieldClass}
                                            value={data.apartment_number}
                                            onChange={(next) => setPlace('apartment_number', next)}
                                            suggestions={apartmentSuggestions}
                                        />
                                    </FormField>
                                    <FormField className="sm:col-span-2">
                                        <InputLabel
                                            value={t('staff_pay_apartment_model')}
                                            htmlFor="apartment_model"
                                        />
                                        <SuggestionCombobox
                                            id="apartment_model"
                                            className={fieldClass}
                                            value={data.apartment_model}
                                            onChange={(next) => setPlace('apartment_model', next)}
                                            suggestions={apartmentModelSuggestions}
                                        />
                                    </FormField>
                                </FormSection>
                            ) : null}

                            {data.site_kind === 'villa' ? (
                                <FormSection cols={2}>
                                    <FormField>
                                        <InputLabel
                                            value={t('staff_pay_villa_number')}
                                            htmlFor="villa_number"
                                        />
                                        <SuggestionCombobox
                                            id="villa_number"
                                            className={fieldClass}
                                            value={data.villa_number}
                                            onChange={(next) => setPlace('villa_number', next)}
                                            suggestions={villaSuggestions}
                                        />
                                    </FormField>
                                    <FormField>
                                        <InputLabel value={t('staff_pay_zone')} htmlFor="zone" />
                                        <SuggestionCombobox
                                            id="zone"
                                            className={fieldClass}
                                            value={data.zone}
                                            onChange={(next) => setPlace('zone', next)}
                                            suggestions={villaZoneSuggestions}
                                        />
                                    </FormField>
                                    <FormField className="sm:col-span-2">
                                        <InputLabel value={t('staff_pay_area')} htmlFor="area" />
                                        <SuggestionCombobox
                                            id="area"
                                            className={fieldClass}
                                            value={data.area}
                                            onChange={(next) => setPlace('area', next)}
                                            suggestions={areaSuggestions}
                                        />
                                    </FormField>
                                </FormSection>
                            ) : null}
                        </div>
                    ) : null}

                    {/* Monthly */}
                    {isMonthly ? (
                        <FormSection cols={2}>
                            <FormField>
                                <InputLabel value={t('vault_form_salary_amount')} htmlFor="amount" />
                                <MoneyInput
                                    id="amount"
                                    className={moneyFieldClass}
                                    value={data.amount}
                                    onValueChange={(next) => setData('amount', next)}
                                    allowDecimals={data.currency === 'USD'}
                                />
                                <InputError message={mergedErrors.amount} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('currency')} htmlFor="currency" />
                                <div className="mt-1 flex gap-2">
                                    {currencies.map((code) => (
                                        <button
                                            key={code}
                                            type="button"
                                            onClick={() => setData('currency', code)}
                                            className={segmentClass(data.currency === code)}
                                        >
                                            {code}
                                        </button>
                                    ))}
                                </div>
                            </FormField>
                        </FormSection>
                    ) : null}

                    {/* Daily */}
                    {isDaily ? (
                        <FormSection cols={2}>
                            <FormField>
                                <InputLabel value={t('staff_pay_days')} htmlFor="days_count" />
                                <TextInput
                                    id="days_count"
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    className={fieldClass}
                                    value={data.days_count}
                                    onChange={(e) => setData('days_count', e.target.value)}
                                />
                                <InputError message={mergedErrors.days_count} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('staff_day_rate')} htmlFor="day_rate" />
                                <MoneyInput
                                    id="day_rate"
                                    className={moneyFieldClass}
                                    value={data.day_rate}
                                    onValueChange={(next) => setData('day_rate', next)}
                                    allowDecimals={data.currency === 'USD'}
                                />
                                <InputError message={mergedErrors.day_rate} className="mt-1" />
                            </FormField>
                            {line?.kind === 'job_pay' ? (
                                <FormField className="sm:col-span-2">
                                    <InputLabel value={t('amount')} htmlFor="amount" />
                                    <MoneyInput
                                        id="amount"
                                        className={moneyFieldClass}
                                        value={data.amount}
                                        onValueChange={(next) => setData('amount', next)}
                                        allowDecimals={data.currency === 'USD'}
                                    />
                                    <InputError message={mergedErrors.amount} className="mt-1" />
                                </FormField>
                            ) : null}
                            <FormField className="sm:col-span-2">
                                <p className="text-sm text-slate-600 dark:text-slate-300">
                                    {t('staff_pay_subtotal')}:{' '}
                                    <span dir="ltr" className="font-semibold tabular-nums">
                                        <MoneyAmount
                                            value={dailySubtotal}
                                            label={data.currency}
                                            size="sm"
                                            showLabel={false}
                                        />{' '}
                                        {data.currency}
                                    </span>
                                </p>
                            </FormField>
                        </FormSection>
                    ) : null}

                    {/* Unit rows */}
                    {isUnit ? (
                        <div className="space-y-3 rounded-xl border border-slate-700 bg-slate-950/40 p-3">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-sm font-semibold text-slate-100">
                                    {t('staff_pay_items_title')}
                                </p>
                                <SecondaryButton type="button" onClick={addItem}>
                                    {t('staff_rates_add')}
                                </SecondaryButton>
                            </div>
                            <InputError message={mergedErrors.items} />
                            {rates.length === 0 ? (
                                <p className="text-sm text-rose-300">
                                    {t('staff_pay_no_rates')}
                                </p>
                            ) : null}
                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full min-w-[46rem] border-collapse text-sm">
                                    <thead>
                                        <tr className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                            <th className="px-2 py-2 text-start">{t('staff_rate_item')}</th>
                                            <th className="px-2 py-2 text-start">{t('rate_unit')}</th>
                                            <th className="px-2 py-2 text-start">{t('quantity')}</th>
                                            <th className="px-2 py-2 text-start">{t('unit_rate')}</th>
                                            <th className="px-2 py-2 text-end">{t('staff_pay_subtotal')}</th>
                                            <th className="px-2 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {unitRows.map((row, index) => (
                                            <tr key={index} className="border-t border-slate-800">
                                                <td className="px-2 py-2 align-top">{itemSelect(row, index)}</td>
                                                <td className="px-2 py-2 align-top">
                                                    <TextInput className={fieldClass} value={row.unit} readOnly />
                                                </td>
                                                <td className="px-2 py-2 align-top">
                                                    <MoneyInput
                                                        className={moneyFieldClass}
                                                        value={row.quantity}
                                                        onValueChange={(next) => setItem(index, 'quantity', next)}
                                                        allowDecimals
                                                        placeholder={t('vault_form_quantity_label', { unit: row.unit || '' })}
                                                    />
                                                </td>
                                                <td className="px-2 py-2 align-top">
                                                    <MoneyInput
                                                        className={moneyFieldClass}
                                                        value={row.unit_rate}
                                                        onValueChange={(next) => setItem(index, 'unit_rate', next)}
                                                        allowDecimals
                                                        disabled={!canEditRate}
                                                    />
                                                </td>
                                                <td className="px-2 py-2 text-end align-middle">
                                                    <span dir="ltr" className="font-semibold tabular-nums text-slate-100">
                                                        {row.subtotal || 0}
                                                    </span>
                                                </td>
                                                <td className="px-2 py-2 align-top">
                                                    <button
                                                        type="button"
                                                        onClick={() => removeItem(index)}
                                                        className="min-h-[2.75rem] rounded-xl border border-slate-700 px-3 text-sm text-slate-300"
                                                        aria-label={t('remove')}
                                                    >
                                                        ×
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="space-y-3 md:hidden">
                                {unitRows.map((row, index) => (
                                    <div key={index} className="space-y-2 rounded-xl border border-slate-800 bg-slate-900/70 p-3">
                                        {itemSelect(row, index, `item_m_${index}`)}
                                        <TextInput className={fieldClass} value={row.unit} readOnly />
                                        <MoneyInput
                                            className={moneyFieldClass}
                                            value={row.quantity}
                                            onValueChange={(next) => setItem(index, 'quantity', next)}
                                            allowDecimals
                                            placeholder={t('vault_form_quantity_label', { unit: row.unit || '' })}
                                        />
                                        <MoneyInput
                                            className={moneyFieldClass}
                                            value={row.unit_rate}
                                            onValueChange={(next) => setItem(index, 'unit_rate', next)}
                                            allowDecimals
                                            disabled={!canEditRate}
                                        />
                                        <div className="flex items-center justify-between">
                                            <span dir="ltr" className="font-semibold tabular-nums text-slate-100">
                                                {row.subtotal || 0} {row.currency}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => removeItem(index)}
                                                className="min-h-[2.75rem] rounded-xl border border-slate-700 px-3 text-sm text-slate-300"
                                                aria-label={t('remove')}
                                            >
                                                ×
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <p className="text-sm font-semibold text-slate-100">
                                {t('staff_pay_subtotal')}:{' '}
                                <span dir="ltr" className="tabular-nums">
                                    {unitTotal} {previewCurrency}
                                </span>
                            </p>
                        </div>
                    ) : null}

                    {selected ? (
                        <InsuranceHoldToggle
                            checked={!!data.apply_insurance}
                            onChange={(next) => setData('apply_insurance', next)}
                            label={t('vault_form_apply_insurance')}
                            onLabel={t('vault_form_apply_insurance_on')}
                            offLabel={t('vault_form_apply_insurance_off')}
                            hintOn={t('vault_form_apply_insurance_hint_on')}
                            hintOff={t('vault_form_apply_insurance_hint_off')}
                            tone={isMonthly ? 'sky' : 'amber'}
                        />
                    ) : null}

                    {preview && selected ? (
                        <p className="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-xs text-slate-300">
                            {data.apply_insurance
                                ? t('vault_form_job_pay_split', {
                                      hold: preview.hold,
                                      leaves: preview.leaves,
                                      currency: previewCurrency,
                                  })
                                : t('vault_form_apply_insurance_hint_off')}
                        </p>
                    ) : null}

                    <FormSection cols={1}>
                        <FormField>
                            <InputLabel value={t('purpose')} htmlFor="purpose" />
                            <TextInput
                                id="purpose"
                                className={fieldClass}
                                value={data.purpose}
                                onChange={(e) => setData('purpose', e.target.value)}
                                placeholder={t('vault_form_purpose_placeholder')}
                            />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('note')} htmlFor="note" />
                            <TextInput
                                id="note"
                                className={fieldClass}
                                value={data.note}
                                onChange={(e) => setData('note', e.target.value)}
                                placeholder={t('vault_form_note_placeholder')}
                            />
                        </FormField>
                    </FormSection>

                    <FormActions>
                        <PrimaryButton
                            disabled={processing || !selected}
                            className="!bg-amber-600 hover:!bg-amber-500"
                        >
                            {editing ? t('staff_edit_save') : t('vault_form_save_job_pay')}
                        </PrimaryButton>
                        <Link
                            href={
                                editing && line?.kind === 'salary' && line?.staff_id
                                    ? route('staff.show', line.staff_id)
                                    : route('vault.job-pay.index')
                            }
                        >
                            <SecondaryButton type="button">{t('cancel')}</SecondaryButton>
                        </Link>
                    </FormActions>
                </form>
            </PageShell>
        </AuthenticatedLayout>
    );
}
