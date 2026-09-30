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
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

const moneyFieldClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 font-sans text-lg font-semibold tabular-nums shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function DirectionCard({ active, tone, icon, title, hint, onClick }) {
    const tones = {
        in: active
            ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-500/30 dark:border-emerald-400 dark:bg-emerald-950/40'
            : 'border-slate-200 bg-white hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-600',
        out: active
            ? 'border-rose-500 bg-rose-50 ring-2 ring-rose-500/30 dark:border-rose-400 dark:bg-rose-950/40'
            : 'border-slate-200 bg-white hover:border-rose-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-rose-600',
    };
    const iconTone =
        tone === 'in'
            ? 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-slate-950'
            : 'bg-rose-600 text-white dark:bg-rose-500 dark:text-slate-950';

    return (
        <button
            type="button"
            onClick={onClick}
            className={
                'flex min-h-[5rem] items-start gap-3 rounded-xl border p-3 text-start transition ' +
                tones[tone]
            }
            aria-pressed={active}
        >
            <span
                className={
                    'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ' +
                    iconTone
                }
            >
                <NavIcon name={icon} className="text-base" />
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-semibold text-slate-900 dark:text-white">
                    {title}
                </span>
                <span className="mt-0.5 block text-xs font-medium text-slate-500 dark:text-slate-400">
                    {hint}
                </span>
            </span>
        </button>
    );
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
                    ? 'border-teal-500 bg-teal-50/80 ring-2 ring-teal-500/25 dark:border-teal-400 dark:bg-teal-950/30'
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
                </div>
                <span
                    className={
                        'inline-flex h-9 w-9 items-center justify-center rounded-xl text-sm font-bold ' +
                        (active
                            ? 'bg-teal-600 text-white dark:bg-teal-500 dark:text-slate-950'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800')
                    }
                >
                    {currency === 'USD' ? '$' : 'د.ع'}
                </span>
            </button>

            {active && (
                <div className="mt-4 border-t border-teal-200/70 pt-4 dark:border-teal-800/50">
                    <InputLabel value={t('amount')} htmlFor={`amount-${currency}`} />
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

function isValidIsoDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) {
        return false;
    }
    const d = new Date(`${value}T00:00:00`);
    return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === value;
}

export default function LedgerForm({
    mode = 'create',
    transaction,
    projects,
    available,
}) {
    const t = useTranslations();
    const editing = mode === 'edit';
    const [localErrors, setLocalErrors] = useState({});
    const { data, setData, post, put, processing, errors } = useForm({
        direction: transaction?.direction || 'in',
        currency: transaction?.currency || 'USD',
        amount: transaction?.amount ?? '',
        occurred_on: transaction?.occurred_on || new Date().toISOString().slice(0, 10),
        description: transaction?.description || '',
        project_id: transaction?.project_id || '',
        reference_code: transaction?.reference_code || '',
    });

    const title = editing
        ? data.direction === 'out'
            ? t('edit_money_out')
            : t('edit_money_in')
        : data.direction === 'out'
          ? t('money_out')
          : t('money_in');

    const submitLabel =
        data.direction === 'out' ? t('record_money_out') : t('record_money_in');

    const mergedErrors = useMemo(
        () => ({ ...localErrors, ...errors }),
        [localErrors, errors],
    );

    const validate = () => {
        const next = {};
        if (!data.amount || Number(data.amount) <= 0) {
            next.amount = t('validation_amount_required');
        }
        if (!String(data.description || '').trim()) {
            next.description = t('validation_description_required');
        }
        if (!isValidIsoDate(data.occurred_on)) {
            next.occurred_on = t('validation_date_required');
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
        if (editing) {
            put(route('vault.transactions.update', transaction.id));
        } else {
            post(route('vault.transactions.store'));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={title}
                    subtitle={t('money_form_hint')}
                    icon={
                        <NavIcon
                            name={data.direction === 'out' ? 'moneyOut' : 'moneyIn'}
                            className="text-lg"
                        />
                    }
                    actions={
                        <Link href={route('vault.transactions')}>
                            <SecondaryButton type="button">
                                <NavIcon name="vault" className="text-sm" />
                                {t('vault_ledger')}
                            </SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={title} />
            <PageShell narrow className="!space-y-4">
                <section>
                    <div className="mb-2">
                        <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                            {t('direction')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('money_form_direction_hint')}
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <DirectionCard
                            tone="in"
                            active={data.direction === 'in'}
                            icon="moneyIn"
                            title={t('money_in')}
                            hint={t('vault_box_money_in_hint')}
                            onClick={() => setData('direction', 'in')}
                        />
                        <DirectionCard
                            tone="out"
                            active={data.direction === 'out'}
                            icon="moneyOut"
                            title={t('money_out')}
                            hint={t('vault_box_money_out_hint')}
                            onClick={() => setData('direction', 'out')}
                        />
                    </div>
                    <InputError message={mergedErrors.direction} className="mt-2" />
                </section>

                <section>
                    <div className="mb-2">
                        <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                            {t('currency')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('money_form_currency_hint')}
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <CurrencyCard
                            currency="USD"
                            active={data.currency === 'USD'}
                            available={available?.available_usd}
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
                            available={available?.available_iqd}
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

                <form noValidate onSubmit={submit} className="bv-card space-y-4 p-4 sm:p-5">
                    <FormSection cols={2}>
                        <FormField>
                            <InputLabel value={t('date')} htmlFor="occurred_on" />
                            <div className="relative">
                                <span className="pointer-events-none absolute inset-y-0 start-3 flex items-center text-slate-400">
                                    <NavIcon name="calendar" className="text-sm" />
                                </span>
                                <TextInput
                                    id="occurred_on"
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete="off"
                                    placeholder={t('date_placeholder')}
                                    className={`${fieldClass} ps-9 font-sans tabular-nums`}
                                    value={data.occurred_on}
                                    onChange={(e) => setData('occurred_on', e.target.value)}
                                />
                            </div>
                            <p className="mt-1 text-xs text-slate-400">{t('date_format_hint')}</p>
                            <InputError message={mergedErrors.occurred_on} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('project')} htmlFor="project_id" />
                            <select
                                id="project_id"
                                className={fieldClass}
                                value={data.project_id || ''}
                                onChange={(e) => setData('project_id', e.target.value)}
                            >
                                <option value="">{t('optional_project')}</option>
                                {(projects || []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={mergedErrors.project_id} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('description')} htmlFor="description" />
                            <TextInput
                                id="description"
                                className={fieldClass}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder={
                                    data.direction === 'out'
                                        ? t('money_out_description_placeholder')
                                        : t('money_in_description_placeholder')
                                }
                            />
                            <InputError message={mergedErrors.description} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('reference')} htmlFor="reference_code" />
                            <TextInput
                                id="reference_code"
                                className={fieldClass}
                                value={data.reference_code}
                                onChange={(e) => setData('reference_code', e.target.value)}
                                placeholder={t('reference_placeholder')}
                            />
                            <InputError message={mergedErrors.reference_code} className="mt-1" />
                        </FormField>
                    </FormSection>

                    <FormActions>
                        <PrimaryButton
                            disabled={processing}
                            className={
                                data.direction === 'out'
                                    ? '!bg-rose-600 hover:!bg-rose-500'
                                    : '!bg-emerald-600 hover:!bg-emerald-500'
                            }
                        >
                            <NavIcon
                                name={data.direction === 'out' ? 'moneyOut' : 'moneyIn'}
                                className="text-sm"
                            />
                            {editing ? t('save') : submitLabel}
                        </PrimaryButton>
                        <Link href={route('vault.transactions')}>
                            <SecondaryButton type="button">{t('cancel')}</SecondaryButton>
                        </Link>
                    </FormActions>
                </form>
            </PageShell>
        </AuthenticatedLayout>
    );
}
