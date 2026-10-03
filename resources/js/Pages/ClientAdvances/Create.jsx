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
import { useMemo, useState } from 'react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

const moneyFieldClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 font-sans text-lg font-semibold tabular-nums shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function roundMoney(n) {
    return Math.round((Number(n) || 0) * 100) / 100;
}

function isValidIsoDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) {
        return false;
    }
    const d = new Date(`${value}T00:00:00`);
    return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === value;
}

function addDaysIso(iso, days) {
    if (!isValidIsoDate(iso)) {
        return null;
    }
    const d = new Date(`${iso}T00:00:00`);
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

function CurrencyCard({
    code,
    label,
    active,
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
                    ? 'border-emerald-500 bg-emerald-50/80 ring-2 ring-emerald-500/25 dark:border-emerald-400 dark:bg-emerald-950/30'
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
                        {label}
                    </p>
                    <p className="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                        {t('client_money_in')}
                    </p>
                    <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {active ? t('client_form_amount_active') : t('client_form_tap_currency')}
                    </p>
                </div>
                <span
                    className={
                        'inline-flex h-9 w-9 items-center justify-center rounded-xl text-sm font-bold ' +
                        (active
                            ? 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-slate-950'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800')
                    }
                >
                    {code === 'USD' ? '$' : 'د.ع'}
                </span>
            </button>

            {active && (
                <div className="mt-4 border-t border-emerald-200/70 pt-4 dark:border-emerald-800/50">
                    <InputLabel value={t('amount')} htmlFor={`amount-${code}`} />
                    <MoneyInput
                        id={`amount-${code}`}
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

function RetentionChoice({ active, locked, title, hint, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={
                'flex min-h-[4.25rem] items-start gap-3 rounded-xl border p-3 text-start transition ' +
                (active
                    ? locked
                        ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-500/25 dark:border-amber-400 dark:bg-amber-950/30'
                        : 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-500/25 dark:border-emerald-400 dark:bg-emerald-950/30'
                    : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900')
            }
        >
            <span
                className={
                    'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ' +
                    (locked
                        ? 'bg-amber-500/15 text-amber-800 dark:text-amber-200'
                        : 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-300')
                }
            >
                <NavIcon name={locked ? 'insurance' : 'moneyIn'} className="text-sm" />
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-semibold text-slate-900 dark:text-white">
                    {title}
                </span>
                <span className="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                    {hint}
                </span>
            </span>
        </button>
    );
}

function PreviewStat({ label, value, currency, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900 dark:text-white',
        locked: 'text-amber-800 dark:text-amber-200',
        free: 'text-emerald-700 dark:text-emerald-300',
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
                    tones[tone]
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

export default function Create({ projects }) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const [localErrors, setLocalErrors] = useState({});
    const { data, setData, post, processing, errors } = useForm({
        project_id: '',
        client_name: '',
        currency: 'USD',
        amount: '',
        received_on: new Date().toISOString().slice(0, 10),
        reference: '',
        notes: '',
        lock_retention: true,
    });

    const mergedErrors = useMemo(
        () => ({ ...localErrors, ...errors }),
        [localErrors, errors],
    );

    const amountNum = Number(data.amount) || 0;
    const lockOn = !!data.lock_retention;
    const locked = lockOn ? roundMoney(amountNum * 0.1) : 0;
    const available = lockOn ? roundMoney(amountNum - locked) : roundMoney(amountNum);
    const maturity = lockOn ? addDaysIso(data.received_on, 180) : null;
    const currencyLabel = data.currency === 'IQD' ? iqd : usd;

    const validate = () => {
        const next = {};
        if (!data.project_id) {
            next.project_id = t('validation_project_required');
        }
        if (!String(data.client_name || '').trim()) {
            next.client_name = t('validation_client_name_required');
        }
        if (!data.amount || amountNum <= 0) {
            next.amount = t('validation_amount_required');
        }
        if (!isValidIsoDate(data.received_on)) {
            next.received_on = t('validation_date_required');
        }
        if (!['USD', 'IQD'].includes(data.currency)) {
            next.currency = t('validation_currency_required');
        }
        setLocalErrors(next);
        return Object.keys(next).length === 0;
    };

    const submit = (e) => {
        e.preventDefault();
        if (!validate()) {
            return;
        }
        post(route('client-advances.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('new_client_advance')}
                    subtitle={t('client_form_hint')}
                    icon={<NavIcon name="clientAdvances" className="text-lg" />}
                    actions={
                        <Link href={route('client-advances.index')}>
                            <SecondaryButton type="button">
                                <NavIcon name="clientAdvances" className="text-sm" />
                                {t('client_advances')}
                            </SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('new_client_advance')} />
            <PageShell narrow className="!space-y-4">
                <section>
                    <div className="mb-2">
                        <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                            {t('currency')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('client_form_currency_hint')}
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <CurrencyCard
                            code="USD"
                            label={usd}
                            active={data.currency === 'USD'}
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
                            code="IQD"
                            label={iqd}
                            active={data.currency === 'IQD'}
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

                <section>
                    <div className="mb-2">
                        <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                            {t('client_form_split_title')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('client_form_split_hint')}
                        </p>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <RetentionChoice
                            active={lockOn}
                            locked
                            title={t('client_form_lock_on')}
                            hint={t('client_form_lock_on_hint')}
                            onClick={() => setData('lock_retention', true)}
                        />
                        <RetentionChoice
                            active={!lockOn}
                            locked={false}
                            title={t('client_form_lock_off')}
                            hint={t('client_form_lock_off_hint')}
                            onClick={() => setData('lock_retention', false)}
                        />
                    </div>
                    <div className="mt-3 grid gap-3 sm:grid-cols-3">
                        <PreviewStat
                            label={t('client_form_preview_gross')}
                            value={amountNum > 0 ? amountNum : null}
                            currency={currencyLabel}
                        />
                        <PreviewStat
                            label={
                                lockOn
                                    ? t('client_form_preview_locked')
                                    : t('client_form_preview_no_lock')
                            }
                            value={amountNum > 0 ? locked : null}
                            currency={currencyLabel}
                            tone="locked"
                        />
                        <PreviewStat
                            label={
                                lockOn
                                    ? t('client_form_preview_available')
                                    : t('client_form_preview_full_available')
                            }
                            value={amountNum > 0 ? available : null}
                            currency={currencyLabel}
                            tone="free"
                        />
                    </div>
                    {lockOn && maturity && (
                        <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            {t('client_form_maturity_hint', { date: maturity })}
                        </p>
                    )}
                </section>

                <form noValidate onSubmit={submit} className="bv-card space-y-4 p-4 sm:p-5">
                    <FormSection cols={2}>
                        <FormField>
                            <InputLabel value={t('project')} htmlFor="project_id" />
                            <select
                                id="project_id"
                                className={fieldClass}
                                value={data.project_id}
                                onChange={(e) => setData('project_id', e.target.value)}
                            >
                                <option value="">{t('client_form_choose_project')}</option>
                                {(projects || []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={mergedErrors.project_id} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('client_name')} htmlFor="client_name" />
                            <TextInput
                                id="client_name"
                                className={fieldClass}
                                value={data.client_name}
                                onChange={(e) => setData('client_name', e.target.value)}
                                placeholder={t('client_form_client_placeholder')}
                                autoComplete="off"
                            />
                            <InputError message={mergedErrors.client_name} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('date')} htmlFor="received_on" />
                            <DateInput
                                id="received_on"
                                className="mt-1"
                                value={data.received_on}
                                onValueChange={(next) => setData('received_on', next)}
                                required
                            />
                            <p className="mt-1 text-xs text-slate-400">{t('date_format_hint')}</p>
                            <InputError message={mergedErrors.received_on} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('reference')} htmlFor="reference" />
                            <TextInput
                                id="reference"
                                className={fieldClass}
                                value={data.reference}
                                onChange={(e) => setData('reference', e.target.value)}
                                placeholder={t('reference_placeholder')}
                            />
                            <InputError message={mergedErrors.reference} className="mt-1" />
                        </FormField>

                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('notes')} htmlFor="notes" />
                            <TextInput
                                id="notes"
                                className={fieldClass}
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                placeholder={t('client_form_notes_placeholder')}
                            />
                            <InputError message={mergedErrors.notes} className="mt-1" />
                        </FormField>
                    </FormSection>

                    <FormActions>
                        <PrimaryButton
                            disabled={processing}
                            className="!bg-emerald-600 hover:!bg-emerald-500"
                        >
                            <NavIcon name="moneyIn" className="text-sm" />
                            {t('new_client_advance')}
                        </PrimaryButton>
                        <Link href={route('client-advances.index')}>
                            <SecondaryButton type="button">{t('cancel')}</SecondaryButton>
                        </Link>
                    </FormActions>
                </form>
            </PageShell>
        </AuthenticatedLayout>
    );
}
