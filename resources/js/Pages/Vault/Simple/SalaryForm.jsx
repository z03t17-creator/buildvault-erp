import DateInput from '@/Components/DateInput';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyAmount from '@/Components/MoneyAmount';
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

export default function SalaryForm({
    projects = [],
    staff = [],
    salaryDues = {},
    estimates = {},
    availableCash = {},
    today,
    preselectStaffId = null,
}) {
    const t = useTranslations();
    const [localErrors, setLocalErrors] = useState({});
    const { data, setData, post, processing, errors } = useForm({
        staff_id: preselectStaffId ? String(preselectStaffId) : '',
        occurred_on: today || todayIsoDate(),
        project_id: '',
        note: '',
        apply_insurance: false,
    });

    const mergedErrors = useMemo(
        () => ({ ...localErrors, ...errors }),
        [localErrors, errors],
    );

    const selected = staff.find((s) => String(s.id) === String(data.staff_id));
    const due = selected ? Number(salaryDues[selected.id] ?? selected.monthly_salary ?? 0) : 0;
    const currency = selected?.currency || 'USD';
    const estimate = estimates?.[currency] || null;
    const cash = Number(availableCash?.[currency] ?? estimate?.available_cash ?? 0);
    const expensesOpen = Number(estimate?.expenses_to_pay ?? 0);
    const afterPay = Math.round((cash - due) * 100) / 100;
    const coversThis = cash >= due + expensesOpen;

    const validate = () => {
        const next = {};
        if (!data.staff_id) {
            next.staff_id = t('vault_form_validation_salary_staff');
        }
        if (!isValidIsoDate(data.occurred_on)) {
            next.occurred_on = t('validation_date_required');
        }
        if (selected && due <= 0) {
            next.staff_id = t('vault_form_salary_zero');
        }
        setLocalErrors(next);
        return Object.keys(next).length === 0;
    };

    const submit = (e) => {
        e.preventDefault();
        if (!validate()) {
            return;
        }
        post(route('vault.lines.salary.store'));
    };

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('vault_form_salary')}
                    subtitle={t('vault_form_salary_hint')}
                    icon={<NavIcon name="payroll" className="text-lg text-teal-600 dark:text-teal-300" />}
                />
            }
        >
            <Head title={t('vault_form_salary')} />

            <PageShell className="!max-w-3xl !space-y-4">
                {!staff.length ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                        <p className="font-medium">{t('vault_form_no_salary_staff')}</p>
                        <Link
                            href={route('staff.create', { return: '/vault/lines/salary' })}
                            className="mt-1 inline-flex font-semibold text-teal-700 underline dark:text-teal-300"
                        >
                            {t('vault_form_add_staff')}
                        </Link>
                    </div>
                ) : null}

                <form noValidate onSubmit={submit} className="bv-card space-y-4 p-4 sm:p-5">
                    <FormSection cols={2}>
                        <FormField>
                            <InputLabel value={t('vault_form_salary_staff')} htmlFor="staff_id" />
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
                            <InputLabel value={`${t('project')} (${t('optional')})`} htmlFor="project_id" />
                            <select
                                id="project_id"
                                className={fieldClass}
                                value={data.project_id}
                                onChange={(e) => setData('project_id', e.target.value)}
                            >
                                <option value="">{t('vault_form_pick_project')}</option>
                                {projects.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField>
                            <InputLabel value={t('vault_form_salary_amount')} htmlFor="salary_amount" />
                            <div
                                id="salary_amount"
                                dir="ltr"
                                className="mt-1 flex min-h-[2.5rem] items-center rounded-xl border border-slate-200 bg-slate-50 px-3 font-sans text-lg font-semibold tabular-nums text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            >
                                {selected ? (
                                    <>
                                        <MoneyAmount
                                            value={due}
                                            label={currency}
                                            size="lg"
                                            showLabel={false}
                                        />
                                        <span className="ms-2 text-xs font-medium text-slate-400">
                                            {currency}
                                        </span>
                                    </>
                                ) : (
                                    '—'
                                )}
                            </div>
                            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {t('vault_form_salary_amount_hint')}
                            </p>
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

                    {selected ? (
                        <div
                            className={
                                'rounded-xl border px-4 py-3 ' +
                                (coversThis
                                    ? 'border-teal-200 bg-teal-50/80 dark:border-teal-800 dark:bg-teal-950/30'
                                    : 'border-rose-200 bg-rose-50/80 dark:border-rose-900 dark:bg-rose-950/30')
                            }
                        >
                            <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                {coversThis
                                    ? t('vault_form_salary_covers')
                                    : t('vault_form_salary_shortfall')}
                            </p>
                            <dl className="mt-2 grid gap-2 sm:grid-cols-3">
                                <div>
                                    <dt className="text-xs text-slate-500">{t('available_cash')}</dt>
                                    <dd dir="ltr" className="font-sans text-sm font-semibold tabular-nums">
                                        <MoneyAmount value={cash} label={currency} size="sm" showLabel={false} />
                                        <span className="ms-1 text-xs text-slate-500 dark:text-slate-400">{currency}</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-500">{t('vault_expenses_to_pay')}</dt>
                                    <dd dir="ltr" className="font-sans text-sm font-semibold tabular-nums">
                                        <MoneyAmount value={expensesOpen} label={currency} size="sm" showLabel={false} />
                                        <span className="ms-1 text-xs text-slate-500 dark:text-slate-400">{currency}</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-500">{t('vault_form_after_salary')}</dt>
                                    <dd
                                        dir="ltr"
                                        className={
                                            'font-sans text-sm font-semibold tabular-nums ' +
                                            (afterPay >= expensesOpen
                                                ? 'text-teal-800 dark:text-teal-200'
                                                : 'text-rose-700 dark:text-rose-300')
                                        }
                                    >
                                        <MoneyAmount value={afterPay} label={currency} size="sm" showLabel={false} />
                                        <span className="ms-1 text-xs text-slate-500 dark:text-slate-400">{currency}</span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    ) : null}

                    <FormActions>
                        <PrimaryButton disabled={processing || !staff.length || !selected || due <= 0}>
                            {t('vault_form_save_salary')}
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
