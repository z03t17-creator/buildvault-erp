import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
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
import { useEffect, useMemo, useState } from 'react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function isValidIsoDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(String(value || ''))) {
        return false;
    }
    const d = new Date(`${value}T00:00:00`);
    return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === value;
}

function CurrencyCard({ code, label, active, amount, onSelect, onAmountChange, amountError, t, allowDecimals }) {
    return (
        <button
            type="button"
            onClick={onSelect}
            className={
                'rounded-2xl border p-4 text-start transition ' +
                (active
                    ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-500/30 dark:border-amber-400 dark:bg-amber-950/40'
                    : 'border-slate-200 bg-white hover:border-amber-300 dark:border-slate-700 dark:bg-slate-950 dark:hover:border-amber-700')
            }
        >
            <div className="flex items-center justify-between gap-2">
                <span className="text-sm font-semibold text-slate-900 dark:text-white">{label}</span>
                <span
                    className={
                        'rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ' +
                        (active
                            ? 'bg-amber-600 text-white dark:bg-amber-400 dark:text-amber-950'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400')
                    }
                >
                    {code}
                </span>
            </div>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {active ? t('staff_pay_amount_active') : t('staff_pay_tap_currency', { code })}
            </p>
            {active ? (
                <div className="mt-3" onClick={(e) => e.stopPropagation()}>
                    <MoneyInput
                        className={fieldClass}
                        value={amount}
                        onValueChange={onAmountChange}
                        allowDecimals={allowDecimals}
                    />
                    <InputError message={amountError} className="mt-1" />
                </div>
            ) : null}
        </button>
    );
}

export default function Create({ projects, workers, repaymentMethods, currencies, defaults }) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const [localErrors, setLocalErrors] = useState({});
    const { data, setData, post, processing, errors } = useForm({
        worker_id: '',
        project_id: '',
        currency: defaults?.currency || 'IQD',
        amount: '',
        advanced_on: defaults?.advanced_on || new Date().toISOString().slice(0, 10),
        reason: '',
        repayment_method: defaults?.repayment_method || 'payroll_deduction',
        notes: '',
    });

    const mergedErrors = useMemo(
        () => ({ ...localErrors, ...errors }),
        [localErrors, errors],
    );

    useEffect(() => {
        if (!data.worker_id) {
            return;
        }
        const worker = (workers || []).find((w) => String(w.id) === String(data.worker_id));
        if (worker?.project_id && !data.project_id) {
            setData('project_id', String(worker.project_id));
        }
    }, [data.worker_id]);

    const kindLabel = (kind) => {
        const key = `labor_kind_${kind || 'unclassified'}`;
        const v = t(key);
        return v !== key ? v : kind;
    };

    const validate = () => {
        const next = {};
        if (!data.worker_id) {
            next.worker_id = t('staff_pay_validation_person');
        }
        if (!data.project_id) {
            next.project_id = t('staff_pay_validation_project');
        }
        if (!data.amount || Number(data.amount) <= 0) {
            next.amount = t('staff_pay_validation_amount');
        }
        if (!isValidIsoDate(data.advanced_on)) {
            next.advanced_on = t('staff_pay_validation_date');
        }
        if (!String(data.reason || '').trim()) {
            next.reason = t('staff_pay_validation_reason');
        }
        setLocalErrors(next);
        return Object.keys(next).length === 0;
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('staff_pay_record')}
                    subtitle={t('staff_pay_form_hint')}
                    icon={<NavIcon name="advances" className="text-lg" />}
                    actions={
                        <Link href={route('advances.index')}>
                            <SecondaryButton type="button">
                                <NavIcon name="advances" className="text-sm" />
                                {t('staff_pay_title')}
                            </SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('staff_pay_record')} />
            <PageShell narrow className="!space-y-4">
                <section>
                    <div className="mb-2">
                        <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                            {t('currency')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('staff_pay_currency_hint')}
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
                            amountError={data.currency === 'USD' ? mergedErrors.amount : undefined}
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
                            amountError={data.currency === 'IQD' ? mergedErrors.amount : undefined}
                            t={t}
                            allowDecimals={false}
                        />
                    </div>
                </section>

                <section className="bv-card p-4 sm:p-5">
                    <form
                        noValidate
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (!validate()) {
                                return;
                            }
                            post(route('advances.store'));
                        }}
                    >
                        <FormSection cols={2}>
                            <FormField>
                                <InputLabel value={t('person')} />
                                <select
                                    className={fieldClass}
                                    value={data.worker_id}
                                    onChange={(e) => setData('worker_id', e.target.value)}
                                >
                                    <option value="">—</option>
                                    {(workers || []).map((w) => (
                                        <option key={w.id} value={w.id}>
                                            {w.name}
                                            {w.labor_kind
                                                ? ` (${kindLabel(w.labor_kind)})`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={mergedErrors.worker_id} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('project')} />
                                <select
                                    className={fieldClass}
                                    value={data.project_id}
                                    onChange={(e) => setData('project_id', e.target.value)}
                                >
                                    <option value="">—</option>
                                    {(projects || []).map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={mergedErrors.project_id} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('date')} />
                                <TextInput
                                    className={fieldClass}
                                    value={data.advanced_on}
                                    onChange={(e) => setData('advanced_on', e.target.value)}
                                    placeholder="YYYY-MM-DD"
                                    inputMode="numeric"
                                />
                                <InputError message={mergedErrors.advanced_on} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('repayment_method')} />
                                <select
                                    className={fieldClass}
                                    value={data.repayment_method}
                                    onChange={(e) => setData('repayment_method', e.target.value)}
                                >
                                    {(repaymentMethods || []).map((m) => (
                                        <option key={m} value={m}>
                                            {t(`repay_${m}`) !== `repay_${m}` ? t(`repay_${m}`) : m}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={mergedErrors.repayment_method}
                                    className="mt-1"
                                />
                            </FormField>
                            <FormField className="sm:col-span-2">
                                <InputLabel value={t('reason')} />
                                <TextInput
                                    className={fieldClass}
                                    value={data.reason}
                                    onChange={(e) => setData('reason', e.target.value)}
                                    placeholder={t('staff_pay_reason_placeholder')}
                                />
                                <InputError message={mergedErrors.reason} className="mt-1" />
                            </FormField>
                            <FormField className="sm:col-span-2">
                                <InputLabel value={t('notes')} />
                                <TextInput
                                    className={fieldClass}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                />
                                <InputError message={mergedErrors.notes} className="mt-1" />
                            </FormField>
                        </FormSection>
                        <FormActions className="mt-4">
                            <PrimaryButton
                                disabled={processing}
                                className="!bg-amber-600 hover:!bg-amber-500"
                            >
                                {t('staff_pay_record')}
                            </PrimaryButton>
                        </FormActions>
                    </form>
                </section>
            </PageShell>
        </AuthenticatedLayout>
    );
}
