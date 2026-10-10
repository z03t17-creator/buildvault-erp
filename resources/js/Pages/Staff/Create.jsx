import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
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
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const fieldClass = deskFieldClass;
const moneyFieldClass = deskMoneyClass;

function emptyRate(defaultUnit = '') {
    return { item_name: '', unit: defaultUnit, rate: '', currency: 'IQD' };
}

function ratesFromStaff(staff, defaultUnit = '') {
    const rows = Array.isArray(staff?.rates) ? staff.rates : [];
    if (rows.length === 0) return [emptyRate(defaultUnit)];
    return rows.map((row) => ({
        item_name: row.item_name || '',
        unit: row.unit || defaultUnit,
        rate: row.rate != null && row.rate !== '' ? String(row.rate) : '',
        currency: row.currency || staff?.currency || 'IQD',
    }));
}

export default function Create({
    staff = null,
    payModels = ['monthly', 'daily', 'unit'],
    currencies = ['USD', 'IQD'],
    roleSuggestions = [],
    rateUnitSuggestions = [],
    itemSuggestions = [],
    returnTo,
}) {
    const t = useTranslations();
    const editing = Boolean(staff?.id);
    const defaultUnit = t('staff_rate_unit_default');
    const [localErrors, setLocalErrors] = useState({});
    const { data, setData, post, put, processing, errors } = useForm({
        name: staff?.name || '',
        phone: staff?.phone || '',
        role: staff?.role || staff?.trade || '',
        pay_model: staff?.pay_model || 'monthly',
        monthly_salary: staff?.monthly_salary != null ? String(staff.monthly_salary) : '',
        day_rate: staff?.day_rate != null ? String(staff.day_rate) : '',
        currency: staff?.currency || 'IQD',
        rates: ratesFromStaff(staff, defaultUnit),
        return_to: returnTo || '',
    });

    const mergedErrors = useMemo(
        () => ({ ...localErrors, ...errors }),
        [localErrors, errors],
    );

    const isMonthly = data.pay_model === 'monthly';
    const isDaily = data.pay_model === 'daily';
    const isUnit = data.pay_model === 'unit';

    const setRate = (index, key, value) => {
        const next = data.rates.map((row, i) =>
            i === index ? { ...row, [key]: value } : row,
        );
        setData('rates', next);
    };

    const addRate = () => setData('rates', [...data.rates, emptyRate(defaultUnit)]);
    const removeRate = (index) => {
        if (data.rates.length <= 1) return;
        setData(
            'rates',
            data.rates.filter((_, i) => i !== index),
        );
    };

    const validate = () => {
        const next = {};
        if (!String(data.name || '').trim()) {
            next.name = t('vault_form_validation_staff_name');
        }
        if (!payModels.includes(data.pay_model)) {
            next.pay_model = t('staff_pay_model_required');
        }
        if (isMonthly) {
            if (!data.monthly_salary || Number(data.monthly_salary) <= 0) {
                next.monthly_salary = t('validation_amount_required');
            }
            if (!currencies.includes(data.currency)) {
                next.currency = t('validation_currency_required');
            }
        }
        if (isDaily) {
            if (!data.day_rate || Number(data.day_rate) <= 0) {
                next.day_rate = t('validation_amount_required');
            }
            if (!currencies.includes(data.currency)) {
                next.currency = t('validation_currency_required');
            }
        }
        if (isUnit) {
            const ok = (data.rates || []).some(
                (r) =>
                    String(r.item_name || '').trim() &&
                    String(r.unit || '').trim() &&
                    Number(r.rate) > 0 &&
                    currencies.includes(r.currency),
            );
            if (!ok) next.rates = t('staff_rates_required');
        }
        setLocalErrors(next);
        return Object.keys(next).length === 0;
    };

    const submit = (e) => {
        e.preventDefault();
        if (!validate()) return;
        if (editing) {
            put(route('staff.update', staff.id));
            return;
        }
        post(route('staff.store'));
    };

    const cancelHref = returnTo || (editing ? route('staff.show', staff.id) : route('staff.index'));
    const pageTitle = editing ? t('staff_edit_title') : t('staff_create_title');
    const pageHint = editing ? t('staff_edit_hint') : t('staff_create_hint');

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={pageTitle}
                    subtitle={pageHint}
                    icon={<NavIcon name="workers" className="text-lg text-teal-600 dark:text-teal-300" />}
                />
            }
        >
            <Head title={pageTitle} />

            <PageShell className="!max-w-5xl !space-y-4">
                <form noValidate onSubmit={submit} className="bv-card space-y-4 p-4 sm:p-5">
                    <FormSection cols={2}>
                        <FormField>
                            <InputLabel value={t('name')} htmlFor="name" />
                            <TextInput
                                id="name"
                                className={fieldClass}
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                autoFocus
                            />
                            <InputError message={mergedErrors.name} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('phone')} htmlFor="phone" />
                            <TextInput
                                id="phone"
                                className={fieldClass}
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                            />
                        </FormField>

                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('staff_role')} htmlFor="role" />
                            <SuggestionCombobox
                                id="role"
                                className={fieldClass}
                                value={data.role}
                                onChange={(next) => setData('role', next)}
                                suggestions={roleSuggestions}
                                placeholder={t('staff_role_placeholder')}
                            />
                        </FormField>

                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('staff_pay_model')} htmlFor="pay_model" />
                            <div className="mt-1 flex flex-wrap gap-2">
                                {payModels.map((model) => (
                                    <button
                                        key={model}
                                        type="button"
                                        onClick={() => setData('pay_model', model)}
                                        className={segmentClass(data.pay_model === model)}
                                        aria-pressed={data.pay_model === model}
                                    >
                                        {t(`staff_pay_${model}`)}
                                    </button>
                                ))}
                            </div>
                            <InputError message={mergedErrors.pay_model} className="mt-1" />
                        </FormField>

                        {isMonthly ? (
                            <>
                                <FormField>
                                    <InputLabel value={t('monthly_salary')} htmlFor="monthly_salary" />
                                    <MoneyInput
                                        id="monthly_salary"
                                        className={moneyFieldClass}
                                        value={data.monthly_salary}
                                        onValueChange={(next) => setData('monthly_salary', next)}
                                        allowDecimals={data.currency === 'USD'}
                                        placeholder="0"
                                    />
                                    <InputError message={mergedErrors.monthly_salary} className="mt-1" />
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
                            </>
                        ) : null}

                        {isDaily ? (
                            <>
                                <FormField>
                                    <InputLabel value={t('staff_day_rate')} htmlFor="day_rate" />
                                    <MoneyInput
                                        id="day_rate"
                                        className={moneyFieldClass}
                                        value={data.day_rate}
                                        onValueChange={(next) => setData('day_rate', next)}
                                        allowDecimals={data.currency === 'USD'}
                                        placeholder="0"
                                    />
                                    <InputError message={mergedErrors.day_rate} className="mt-1" />
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
                            </>
                        ) : null}
                    </FormSection>

                    {isUnit ? (
                        <div className="space-y-3 rounded-xl border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-950/40">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                    {t('staff_rates_title')}
                                </p>
                                <SecondaryButton type="button" onClick={addRate}>
                                    {t('staff_rates_add')}
                                </SecondaryButton>
                            </div>
                            <InputError message={mergedErrors.rates} />
                            <div className="hidden md:block">
                                <table className="w-full border-collapse text-sm">
                                    <thead>
                                        <tr className="text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                            <th className="px-2 py-2 text-start">{t('staff_rate_item')}</th>
                                            <th className="px-2 py-2 text-start">{t('rate_unit')}</th>
                                            <th className="px-2 py-2 text-start">{t('unit_rate')}</th>
                                            <th className="px-2 py-2 text-start">{t('currency')}</th>
                                            <th className="px-2 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.rates.map((row, index) => (
                                            <tr key={index} className="border-t border-slate-200 dark:border-slate-800">
                                                <td className="px-2 py-2 align-top">
                                                    <SuggestionCombobox
                                                        id={`item_${index}`}
                                                        className={fieldClass}
                                                        value={row.item_name}
                                                        onChange={(next) => setRate(index, 'item_name', next)}
                                                        suggestions={itemSuggestions}
                                                        placeholder={t('staff_rate_item')}
                                                    />
                                                </td>
                                                <td className="px-2 py-2 align-top">
                                                    <SuggestionCombobox
                                                        id={`unit_${index}`}
                                                        className={fieldClass}
                                                        value={row.unit}
                                                        onChange={(next) => setRate(index, 'unit', next)}
                                                        suggestions={rateUnitSuggestions}
                                                        placeholder={t('rate_unit')}
                                                    />
                                                </td>
                                                <td className="px-2 py-2 align-top">
                                                    <MoneyInput
                                                        id={`rate_${index}`}
                                                        className={moneyFieldClass}
                                                        value={row.rate}
                                                        onValueChange={(next) => setRate(index, 'rate', next)}
                                                        allowDecimals
                                                        placeholder="0"
                                                    />
                                                </td>
                                                <td className="px-2 py-2 align-top">
                                                    <div className="flex gap-1">
                                                        {currencies.map((code) => (
                                                            <button
                                                                key={code}
                                                                type="button"
                                                                onClick={() => setRate(index, 'currency', code)}
                                                                className={segmentClass(row.currency === code)}
                                                            >
                                                                {code}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </td>
                                                <td className="px-2 py-2 align-top">
                                                    <button
                                                        type="button"
                                                        onClick={() => removeRate(index)}
                                                        className="min-h-[2.75rem] rounded-xl border border-slate-200 px-3 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300"
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
                                {data.rates.map((row, index) => (
                                    <div
                                        key={index}
                                        className="space-y-2 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900/70"
                                    >
                                        <SuggestionCombobox
                                            id={`item_m_${index}`}
                                            className={fieldClass}
                                            value={row.item_name}
                                            onChange={(next) => setRate(index, 'item_name', next)}
                                            suggestions={itemSuggestions}
                                            placeholder={t('staff_rate_item')}
                                        />
                                        <SuggestionCombobox
                                            id={`unit_m_${index}`}
                                            className={fieldClass}
                                            value={row.unit}
                                            onChange={(next) => setRate(index, 'unit', next)}
                                            suggestions={rateUnitSuggestions}
                                            placeholder={t('rate_unit')}
                                        />
                                        <MoneyInput
                                            id={`rate_m_${index}`}
                                            className={moneyFieldClass}
                                            value={row.rate}
                                            onValueChange={(next) => setRate(index, 'rate', next)}
                                            allowDecimals
                                            placeholder="0"
                                        />
                                        <div className="flex gap-1">
                                            {currencies.map((code) => (
                                                <button
                                                    key={code}
                                                    type="button"
                                                    onClick={() => setRate(index, 'currency', code)}
                                                    className={segmentClass(row.currency === code)}
                                                >
                                                    {code}
                                                </button>
                                            ))}
                                            <button
                                                type="button"
                                                onClick={() => removeRate(index)}
                                                className="min-h-[2.75rem] rounded-xl border border-slate-200 px-3 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300"
                                                aria-label={t('remove')}
                                            >
                                                ×
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : null}

                    <FormActions>
                        <PrimaryButton disabled={processing}>
                            {editing ? t('staff_edit_save') : t('staff_create_save')}
                        </PrimaryButton>
                        <Link href={cancelHref}>
                            <SecondaryButton type="button">{t('cancel')}</SecondaryButton>
                        </Link>
                    </FormActions>
                </form>
            </PageShell>
        </AuthenticatedLayout>
    );
}
