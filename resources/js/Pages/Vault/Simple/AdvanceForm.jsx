import DateInput from '@/Components/DateInput';
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
import { isValidIsoDate, todayIsoDate } from '@/lib/isoDate';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

const moneyFieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 font-sans text-base font-semibold tabular-nums shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function AdvanceForm({
    projects = [],
    currencies = ['USD', 'IQD'],
    today,
    line = null,
}) {
    const t = useTranslations();
    const editing = Boolean(line?.id);
    const [localErrors, setLocalErrors] = useState({});
    const { data, setData, post, put, processing, errors } = useForm({
        occurred_on: line?.occurred_on || today || todayIsoDate(),
        amount: line?.amount != null ? String(line.amount) : '',
        currency: line?.currency || 'USD',
        project_id: line?.project_id ? String(line.project_id) : '',
        unlock_date: line?.unlock_date || '',
        note: line?.note || '',
    });

    const mergedErrors = useMemo(
        () => ({ ...localErrors, ...errors }),
        [localErrors, errors],
    );

    const holdPreview = Number(data.amount) > 0
        ? {
              hold: Math.round(Number(data.amount) * 0.1 * 100) / 100,
              available: Math.round(Number(data.amount) * 0.9 * 100) / 100,
          }
        : null;

    const validate = () => {
        const next = {};
        if (!isValidIsoDate(data.occurred_on)) {
            next.occurred_on = t('validation_date_required');
        }
        if (!data.amount || Number(data.amount) <= 0) {
            next.amount = t('validation_amount_required');
        }
        if (!currencies.includes(data.currency)) {
            next.currency = t('validation_currency_required');
        }
        if (!data.project_id) {
            next.project_id = t('validation_project_required');
        }
        if (editing && data.unlock_date && !isValidIsoDate(data.unlock_date)) {
            next.unlock_date = t('validation_date_required');
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
            put(route('vault.lines.advance.update', line.id));
            return;
        }
        post(route('vault.lines.advance.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={editing ? t('vault_form_advance_edit') : t('vault_form_advance')}
                    subtitle={t('vault_form_advance_hint')}
                    icon={<NavIcon name="vault" className="text-lg text-teal-600 dark:text-teal-300" />}
                />
            }
        >
            <Head title={editing ? t('vault_form_advance_edit') : t('vault_form_advance')} />

            <PageShell className="!max-w-3xl !space-y-4">
                <form noValidate onSubmit={submit} className="bv-card space-y-4 p-4 sm:p-5">
                    <FormSection cols={2}>
                        <FormField>
                            <InputLabel value={t('date')} htmlFor="occurred_on" />
                            <DateInput
                                id="occurred_on"
                                className="mt-1"
                                value={data.occurred_on}
                                onValueChange={(next) => setData('occurred_on', next)}
                                required
                            />
                            <InputError message={mergedErrors.occurred_on} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('currency')} htmlFor="currency" />
                            <div className="mt-1 flex gap-2">
                                {currencies.map((code) => (
                                    <button
                                        key={code}
                                        type="button"
                                        onClick={() => setData('currency', code)}
                                        className={
                                            'min-h-[2.5rem] flex-1 rounded-xl border text-sm font-semibold transition ' +
                                            (data.currency === code
                                                ? 'border-teal-500 bg-teal-50 text-teal-900 ring-2 ring-teal-500/25 dark:border-teal-400 dark:bg-teal-950/40 dark:text-teal-100'
                                                : 'border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300')
                                        }
                                        aria-pressed={data.currency === code}
                                    >
                                        {code}
                                    </button>
                                ))}
                            </div>
                            <InputError message={mergedErrors.currency} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('amount')} htmlFor="amount" />
                            <MoneyInput
                                id="amount"
                                className={moneyFieldClass}
                                value={data.amount}
                                onValueChange={(next) => setData('amount', next)}
                                allowDecimals={data.currency === 'USD'}
                                placeholder="0"
                            />
                            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {t('vault_form_advance_amount_hint')}
                            </p>
                            <InputError message={mergedErrors.amount} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('project')} htmlFor="project_id" />
                            <select
                                id="project_id"
                                className={fieldClass}
                                value={data.project_id}
                                onChange={(e) => setData('project_id', e.target.value)}
                                aria-required="true"
                            >
                                <option value="">{t('vault_form_pick_project')}</option>
                                {projects.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={mergedErrors.project_id} className="mt-1" />
                        </FormField>

                        {editing ? (
                            <FormField>
                                <InputLabel value={t('vault_unlock_date')} htmlFor="unlock_date" />
                                <DateInput
                                    id="unlock_date"
                                    className="mt-1"
                                    value={data.unlock_date}
                                    onValueChange={(next) => setData('unlock_date', next)}
                                />
                                <InputError message={mergedErrors.unlock_date} className="mt-1" />
                            </FormField>
                        ) : null}

                        <FormField className={editing ? '' : 'sm:col-span-2'}>
                            <InputLabel value={t('note')} htmlFor="note" />
                            <TextInput
                                id="note"
                                className={fieldClass}
                                value={data.note}
                                onChange={(e) => setData('note', e.target.value)}
                                placeholder={t('vault_form_note_placeholder')}
                            />
                            <InputError message={mergedErrors.note} className="mt-1" />
                        </FormField>
                    </FormSection>

                    {holdPreview ? (
                        <p className="rounded-xl bg-teal-50/80 px-3 py-2 text-xs font-medium text-teal-900 dark:bg-teal-950/40 dark:text-teal-100">
                            {t('vault_form_advance_split')
                                .replace(':hold', String(holdPreview.hold))
                                .replace(':available', String(holdPreview.available))
                                .replace(':currency', data.currency)}
                        </p>
                    ) : null}

                    <FormActions>
                        <PrimaryButton disabled={processing}>
                            {editing ? t('save') : t('vault_form_save_advance')}
                        </PrimaryButton>
                        <Link href={route('dashboards.vault')}>
                            <SecondaryButton type="button">{t('cancel')}</SecondaryButton>
                        </Link>
                    </FormActions>
                </form>
            </PageShell>
        </AuthenticatedLayout>
    );
}
