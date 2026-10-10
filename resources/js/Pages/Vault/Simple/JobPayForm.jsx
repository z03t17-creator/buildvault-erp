import DateInput from '@/Components/DateInput';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import InsuranceHoldToggle from '@/Components/InsuranceHoldToggle';
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
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

const moneyFieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 font-sans text-base font-semibold tabular-nums shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function JobPayForm({
    projects = [],
    currencies = ['USD', 'IQD'],
    staff = [],
    today,
}) {
    const t = useTranslations();
    const [localErrors, setLocalErrors] = useState({});
    const { data, setData, post, processing, errors } = useForm({
        occurred_on: today || todayIsoDate(),
        amount: '',
        currency: 'USD',
        staff_id: '',
        purpose: '',
        project_id: '',
        note: '',
        apply_insurance: true,
    });

    const mergedErrors = useMemo(
        () => ({ ...localErrors, ...errors }),
        [localErrors, errors],
    );

    const amountNum = Number(data.amount) || 0;
    const holdPreview = amountNum > 0
        ? data.apply_insurance
            ? {
                  hold: Math.round(amountNum * 0.1 * 100) / 100,
                  leaves: Math.round(amountNum * 0.9 * 100) / 100,
              }
            : {
                  hold: 0,
                  leaves: amountNum,
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
        if (!data.staff_id) {
            next.staff_id = t('vault_form_validation_time_staff');
        }
        if (!data.project_id) {
            next.project_id = t('validation_project_required');
        }
        setLocalErrors(next);
        return Object.keys(next).length === 0;
    };

    const submit = (e) => {
        e.preventDefault();
        if (!validate()) {
            return;
        }
        post(route('vault.lines.job-pay.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('vault_form_job_pay')}
                    subtitle={t('vault_form_job_pay_hint')}
                    icon={<NavIcon name="workers" className="text-lg text-amber-600 dark:text-amber-300" />}
                />
            }
        >
            <Head title={t('vault_form_job_pay')} />

            <PageShell className="!max-w-3xl !space-y-4">
                {!staff.length ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                        <p className="font-medium">{t('vault_form_no_time_staff')}</p>
                        <Link
                            href={route('staff.create', { return: '/vault/lines/job-pay' })}
                            className="mt-1 inline-flex font-semibold text-teal-700 underline dark:text-teal-300"
                        >
                            {t('vault_form_add_staff')}
                        </Link>
                    </div>
                ) : null}

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
                                                ? 'border-amber-500 bg-amber-50 text-amber-950 ring-2 ring-amber-500/25 dark:border-amber-400 dark:bg-amber-950/40 dark:text-amber-100'
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
                            <InputError message={mergedErrors.amount} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('vault_form_time_staff')} htmlFor="staff_id" />
                            <select
                                id="staff_id"
                                className={fieldClass}
                                value={data.staff_id}
                                onChange={(e) => setData('staff_id', e.target.value)}
                                aria-required="true"
                            >
                                <option value="">{t('vault_form_pick_staff')}</option>
                                {staff.map((person) => (
                                    <option key={person.id} value={person.id}>
                                        {person.name}
                                        {person.trade ? ` — ${person.trade}` : ''}
                                    </option>
                                ))}
                            </select>
                            <InputError message={mergedErrors.staff_id} className="mt-1" />
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

                        <FormField className="sm:col-span-2">
                            <InputLabel value={`${t('note')} (${t('optional')})`} htmlFor="note" />
                            <TextInput
                                id="note"
                                className={fieldClass}
                                value={data.note}
                                onChange={(e) => setData('note', e.target.value)}
                            />
                        </FormField>
                    </FormSection>

                    <InsuranceHoldToggle
                        checked={Boolean(data.apply_insurance)}
                        onChange={(next) => setData('apply_insurance', next)}
                        label={t('vault_form_apply_insurance')}
                        onLabel={t('vault_form_apply_insurance_on')}
                        offLabel={t('vault_form_apply_insurance_off')}
                        hintOn={t('vault_form_apply_insurance_hint_on')}
                        hintOff={t('vault_form_apply_insurance_hint_off')}
                        tone="amber"
                    />

                    {holdPreview ? (
                        <p className="rounded-xl bg-amber-50/80 px-3 py-2 text-xs font-medium text-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                            {data.apply_insurance
                                ? t('vault_form_job_pay_split', {
                                      leaves: holdPreview.leaves,
                                      hold: holdPreview.hold,
                                      currency: data.currency,
                                  })
                                : t('vault_form_job_pay_no_hold', {
                                      amount: holdPreview.leaves,
                                      currency: data.currency,
                                  })}
                        </p>
                    ) : null}

                    <FormActions>
                        <PrimaryButton
                            disabled={processing || !staff.length}
                            className="bg-amber-600 hover:bg-amber-500 focus:bg-amber-500 focus:ring-amber-500"
                        >
                            {t('vault_form_save_job_pay')}
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
