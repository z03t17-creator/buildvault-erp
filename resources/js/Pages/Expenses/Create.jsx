import DateInput from '@/Components/DateInput';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyAmount from '@/Components/MoneyAmount';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

const moneyFieldClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 font-sans text-lg font-semibold tabular-nums shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function isValidIsoDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) {
        return false;
    }
    const d = new Date(`${value}T00:00:00`);
    return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === value;
}

function labelFor(t, prefix, value) {
    const key = `${prefix}_${value}`;
    const translated = t(key);
    return translated !== key ? translated : String(value || '').replace(/_/g, ' ');
}

function CurrencyCard({
    currency,
    active,
    available,
    amount,
    onSelect,
    onAmountChange,
    amountError,
    t,
    allowDecimals,
}) {
    return (
        <div
            className={
                'w-full rounded-2xl border p-4 transition ' +
                (active
                    ? 'border-rose-500 bg-rose-50/80 ring-2 ring-rose-500/25 dark:border-rose-400 dark:bg-rose-950/30'
                    : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900')
            }
        >
            <button
                type="button"
                onClick={onSelect}
                className="flex w-full items-start justify-between gap-3 text-start"
                aria-pressed={active}
            >
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {currency}
                    </p>
                    <p className="mt-1 text-sm font-medium text-slate-600 dark:text-slate-300">
                        {t('available_cash')}
                    </p>
                    <p
                        dir="ltr"
                        className="mt-0.5 font-sans text-xl font-semibold tabular-nums text-slate-900 dark:text-white"
                    >
                        <MoneyAmount
                            value={available}
                            label={currency}
                            size="lg"
                            showLabel={false}
                            accent={active}
                        />
                    </p>
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {active
                            ? t('expense_form_amount_active')
                            : t('expense_form_tap_currency')}
                    </p>
                </div>
                <span
                    className={
                        'inline-flex h-9 w-9 items-center justify-center rounded-xl text-sm font-bold ' +
                        (active
                            ? 'bg-rose-600 text-white dark:bg-rose-400 dark:text-rose-950'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800')
                    }
                >
                    {currency === 'USD' ? '$' : 'د.ع'}
                </span>
            </button>

            {active && (
                <div className="mt-4 border-t border-rose-200/70 pt-4 dark:border-rose-800/50">
                    <InputLabel
                        value={t('expense_form_spend_amount')}
                        htmlFor={`amount-${currency}`}
                    />
                    <MoneyInput
                        id={`amount-${currency}`}
                        className={moneyFieldClass}
                        value={amount}
                        onValueChange={onAmountChange}
                        allowDecimals={allowDecimals}
                        isFocused
                        placeholder="0"
                    />
                    <InputError message={amountError} className="mt-1" />
                </div>
            )}
        </div>
    );
}

function PreviewStat({ label, value, currency, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900 dark:text-white',
        rose: 'text-rose-800 dark:text-rose-200',
        free: 'text-emerald-700 dark:text-emerald-300',
        warn: 'text-amber-800 dark:text-amber-200',
    };

    return (
        <div className="bv-card px-4 py-3.5">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div
                dir="ltr"
                className={
                    'mt-1.5 font-sans text-2xl font-semibold tracking-normal tabular-nums ' +
                    (tones[tone] || tones.default)
                }
            >
                {value == null || value === '' ? (
                    '—'
                ) : (
                    <MoneyAmount
                        value={value}
                        label={currency}
                        size="xl"
                        showLabel={false}
                        accent={tone === 'free'}
                    />
                )}
            </div>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{currency}</p>
        </div>
    );
}

export default function Create({
    projects,
    categories,
    paymentMethods,
    currencies,
    availableCash,
}) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const fileRef = useRef(null);
    const [localErrors, setLocalErrors] = useState({});
    const { data, setData, post, processing, errors } = useForm({
        project_id: '',
        category: 'materials',
        currency: 'IQD',
        amount: '',
        expense_date: new Date().toISOString().slice(0, 10),
        supplier: '',
        payment_method: 'cash',
        description: '',
        receipt: null,
    });

    const mergedErrors = useMemo(
        () => ({ ...localErrors, ...errors }),
        [localErrors, errors],
    );

    const amountNum = Number(data.amount) || 0;
    const availableForCurrency =
        data.currency === 'USD'
            ? Number(availableCash?.available_usd) || 0
            : Number(availableCash?.available_iqd) || 0;
    const remaining = Math.round((availableForCurrency - amountNum) * 100) / 100;
    const overAvailable = amountNum > 0 && amountNum > availableForCurrency;
    const currencyLabel = data.currency === 'USD' ? usd : iqd;
    const receiptName = data.receipt?.name || '';

    const validate = () => {
        const next = {};
        if (!data.project_id) {
            next.project_id = t('validation_project_required');
        }
        if (!data.amount || amountNum <= 0) {
            next.amount = t('validation_amount_required');
        }
        if (!isValidIsoDate(data.expense_date)) {
            next.expense_date = t('validation_date_required');
        }
        if (!(currencies || ['USD', 'IQD']).includes(data.currency)) {
            next.currency = t('validation_currency_required');
        }
        if (!data.category) {
            next.category = t('expense_form_validation_category');
        }
        setLocalErrors(next);
        return Object.keys(next).length === 0;
    };

    const submit = (e) => {
        e.preventDefault();
        if (!validate()) {
            return;
        }
        post(route('expenses.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('new_expense')}
                    subtitle={t('expense_form_hint')}
                    icon={<NavIcon name="expenses" className="text-lg" />}
                    actions={
                        <Link href={route('expenses.index')}>
                            <SecondaryButton type="button">
                                <NavIcon name="expenses" className="text-sm" />
                                {t('expenses')}
                            </SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('new_expense')} />
            <PageShell narrow className="!space-y-4">
                <section className="overflow-hidden rounded-2xl border border-rose-200/80 bg-gradient-to-br from-rose-50 via-white to-white dark:border-rose-900/50 dark:from-rose-950/40 dark:via-slate-950 dark:to-slate-950">
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-dashed border-rose-200/80 px-4 py-3 dark:border-rose-900/60 sm:px-5">
                        <div className="flex items-start gap-3">
                            <span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-600 text-white dark:bg-rose-400 dark:text-rose-950">
                                <NavIcon name="expenses" className="text-lg" />
                            </span>
                            <div>
                                <p className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                    {t('expense_form_receipt_title')}
                                </p>
                                <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                    {t('expense_form_receipt_hint')}
                                </p>
                            </div>
                        </div>
                        <span className="rounded-lg bg-amber-500/15 px-2.5 py-1 text-xs font-semibold text-amber-950 dark:text-amber-200">
                            {t('status_pending')}
                        </span>
                    </div>
                    <div className="grid gap-3 px-4 py-4 sm:grid-cols-3 sm:px-5">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                {t('expense_form_preview_spend')}
                            </p>
                            <p
                                dir="ltr"
                                className="mt-1 font-sans text-xl font-semibold tabular-nums text-rose-800 dark:text-rose-200"
                            >
                                {amountNum > 0 ? (
                                    <MoneyAmount
                                        value={amountNum}
                                        label={currencyLabel}
                                        size="lg"
                                        showLabel={false}
                                    />
                                ) : (
                                    '—'
                                )}
                            </p>
                            <p className="mt-0.5 text-xs text-slate-500">{currencyLabel}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                {t('category')}
                            </p>
                            <p className="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {labelFor(t, 'expense_category', data.category)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                {t('expense_date')}
                            </p>
                            <p className="mt-1 font-sans text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-100">
                                {data.expense_date || '—'}
                            </p>
                        </div>
                    </div>
                </section>

                <section>
                    <div className="mb-2 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="expenses" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('expense_form_currency_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('expense_form_currency_hint')}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <CurrencyCard
                            currency="USD"
                            active={data.currency === 'USD'}
                            available={availableCash?.available_usd}
                            amount={data.currency === 'USD' ? data.amount : ''}
                            onSelect={() => setData('currency', 'USD')}
                            onAmountChange={(raw) => setData('amount', raw)}
                            amountError={
                                data.currency === 'USD' ? mergedErrors.amount : undefined
                            }
                            t={t}
                            allowDecimals
                        />
                        <CurrencyCard
                            currency="IQD"
                            active={data.currency === 'IQD'}
                            available={availableCash?.available_iqd}
                            amount={data.currency === 'IQD' ? data.amount : ''}
                            onSelect={() => setData('currency', 'IQD')}
                            onAmountChange={(raw) => setData('amount', raw)}
                            amountError={
                                data.currency === 'IQD' ? mergedErrors.amount : undefined
                            }
                            t={t}
                            allowDecimals={false}
                        />
                    </div>
                    <InputError message={mergedErrors.currency} className="mt-2" />
                </section>

                {amountNum > 0 ? (
                    <section>
                        <div className="mb-2">
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('expense_form_preview_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {overAvailable
                                    ? t('expense_form_preview_over_hint')
                                    : t('expense_form_preview_hint')}
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <PreviewStat
                                label={t('available_cash')}
                                value={availableForCurrency}
                                currency={currencyLabel}
                            />
                            <PreviewStat
                                label={t('expense_form_preview_spend')}
                                value={amountNum}
                                currency={currencyLabel}
                                tone="rose"
                            />
                            <PreviewStat
                                label={t('expense_form_preview_remaining')}
                                value={remaining}
                                currency={currencyLabel}
                                tone={overAvailable ? 'warn' : 'free'}
                            />
                        </div>
                    </section>
                ) : null}

                <form
                    noValidate
                    onSubmit={submit}
                    className="bv-card space-y-4 p-4 sm:p-5"
                    encType="multipart/form-data"
                >
                    <div className="flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="payouts" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('expense_form_details_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('expense_form_details_hint')}
                            </p>
                        </div>
                    </div>

                    <FormSection cols={2}>
                        <FormField>
                            <InputLabel value={t('project')} htmlFor="project_id" />
                            <select
                                id="project_id"
                                className={fieldClass}
                                value={data.project_id}
                                onChange={(e) => setData('project_id', e.target.value)}
                            >
                                <option value="">{t('expense_form_project_placeholder')}</option>
                                {(projects || []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={mergedErrors.project_id}
                                className="mt-1"
                            />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('category')} htmlFor="category" />
                            <select
                                id="category"
                                className={fieldClass}
                                value={data.category}
                                onChange={(e) => setData('category', e.target.value)}
                            >
                                {(categories || []).map((c) => (
                                    <option key={c} value={c}>
                                        {labelFor(t, 'expense_category', c)}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={mergedErrors.category}
                                className="mt-1"
                            />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('expense_date')} htmlFor="expense_date" />
                            <DateInput
                                id="expense_date"
                                className="mt-1"
                                value={data.expense_date}
                                onValueChange={(next) => setData('expense_date', next)}
                                required
                            />
                            <InputError
                                message={mergedErrors.expense_date}
                                className="mt-1"
                            />
                        </FormField>
                        <FormField>
                            <InputLabel
                                value={t('payment_method')}
                                htmlFor="payment_method"
                            />
                            <select
                                id="payment_method"
                                className={fieldClass}
                                value={data.payment_method}
                                onChange={(e) =>
                                    setData('payment_method', e.target.value)
                                }
                            >
                                {(paymentMethods || []).map((m) => (
                                    <option key={m} value={m}>
                                        {labelFor(t, 'payment', m)}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField className="sm:col-span-2">
                            <InputLabel
                                value={t('supplier_person')}
                                htmlFor="supplier"
                            />
                            <TextInput
                                id="supplier"
                                className={fieldClass}
                                value={data.supplier}
                                onChange={(e) => setData('supplier', e.target.value)}
                                placeholder={t('expense_form_supplier_placeholder')}
                            />
                        </FormField>
                        <FormField className="sm:col-span-2">
                            <InputLabel
                                value={t('description')}
                                htmlFor="description"
                            />
                            <TextInput
                                id="description"
                                className={fieldClass}
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder={t('expense_form_description_placeholder')}
                            />
                        </FormField>
                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('receipt_file')} />
                            <input
                                ref={fileRef}
                                type="file"
                                className="sr-only"
                                accept="image/*,.pdf"
                                onChange={(e) =>
                                    setData('receipt', e.target.files?.[0] || null)
                                }
                            />
                            <div className="mt-1 flex flex-wrap items-center gap-2">
                                <SecondaryButton
                                    type="button"
                                    onClick={() => fileRef.current?.click()}
                                >
                                    <NavIcon name="payouts" className="text-sm" />
                                    {t('expense_form_choose_receipt')}
                                </SecondaryButton>
                                <span className="text-sm text-slate-500 dark:text-slate-400">
                                    {receiptName || t('expense_form_no_receipt')}
                                </span>
                            </div>
                            <InputError
                                message={mergedErrors.receipt}
                                className="mt-1"
                            />
                        </FormField>
                    </FormSection>

                    <FormActions>
                        <PrimaryButton
                            type="submit"
                            disabled={processing}
                            className="!bg-rose-600 hover:!bg-rose-500 dark:!bg-rose-400 dark:!text-rose-950 dark:hover:!bg-rose-300"
                        >
                            <NavIcon name="expenses" className="text-sm" />
                            {t('create_pending')}
                        </PrimaryButton>
                        <Link href={route('expenses.index')}>
                            <SecondaryButton type="button">{t('cancel')}</SecondaryButton>
                        </Link>
                    </FormActions>
                </form>
            </PageShell>
        </AuthenticatedLayout>
    );
}
