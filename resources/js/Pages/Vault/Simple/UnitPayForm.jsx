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
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { isValidIsoDate, todayIsoDate } from '@/lib/isoDate';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

const moneyFieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 font-sans text-base font-semibold tabular-nums shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function UnitPayForm({
    projects = [],
    staff = [],
    today,
}) {
    const t = useTranslations();
    const [localErrors, setLocalErrors] = useState({});
    const { data, setData, post, processing, errors } = useForm({
        occurred_on: today || todayIsoDate(),
        quantity: '',
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

    const selected = staff.find((s) => String(s.id) === String(data.staff_id));
    const currency = selected?.currency || 'USD';
    const rate = Number(selected?.unit_rate || 0);
    const rateUnit = selected?.rate_unit || 'm²';
    const quantity = Number(data.quantity || 0);
    const amount = quantity > 0 && rate > 0 ? Math.round(quantity * rate * 100) / 100 : 0;
    const holdPreview = amount > 0
        ? data.apply_insurance
            ? {
                  hold: Math.round(amount * 0.1 * 100) / 100,
                  leaves: Math.round(amount * 0.9 * 100) / 100,
              }
            : {
                  hold: 0,
                  leaves: amount,
              }
        : null;

    const validate = () => {
        const next = {};
        if (!isValidIsoDate(data.occurred_on)) {
            next.occurred_on = t('validation_date_required');
        }
        if (!data.staff_id) {
            next.staff_id = t('vault_form_validation_unit_staff');
        }
        if (!data.quantity || Number(data.quantity) <= 0) {
            next.quantity = t('vault_form_validation_quantity');
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
        post(route('vault.lines.unit-pay.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('vault_form_unit_pay')}
                    subtitle={t('vault_form_unit_pay_hint')}
                    icon={<NavIcon name="productions" className="text-lg text-sky-600 dark:text-sky-300" />}
                />
            }
        >
            <Head title={t('vault_form_unit_pay')} />

            <PageShell className="!max-w-3xl !space-y-4">
                {!staff.length ? (
                    <div className="rounded-xl border border-sky-200 bg-sky-50/80 px-4 py-3 text-sm text-sky-950 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-100">
                        <p className="font-semibold">{t('vault_form_unit_pay_no_staff')}</p>
                        <p className="mt-1 text-xs opacity-90">{t('vault_form_unit_pay_no_staff_hint')}</p>
                        <Link
                            href={route('staff.create', { return: '/vault/lines/unit-pay' })}
                            className="mt-2 inline-flex text-sm font-semibold underline"
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
                            />
                            <InputError message={mergedErrors.occurred_on} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('staff')} htmlFor="staff_id" />
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
                                        {person.trade ? ` · ${person.trade}` : ''}
                                        {` · ${person.unit_rate} ${person.currency}/${person.rate_unit}`}
                                    </option>
                                ))}
                            </select>
                            <InputError message={mergedErrors.staff_id} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel
                                value={t('vault_form_quantity_label', { unit: rateUnit })}
                                htmlFor="quantity"
                            />
                            <MoneyInput
                                id="quantity"
                                className={moneyFieldClass}
                                value={data.quantity}
                                onValueChange={(next) => setData('quantity', next)}
                                allowDecimals
                                placeholder="0"
                            />
                            <InputError message={mergedErrors.quantity} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('project')} htmlFor="project_id" />
                            <select
                                id="project_id"
                                className={fieldClass}
                                value={data.project_id}
                                onChange={(e) => setData('project_id', e.target.value)}
                            >
                                <option value="">{t('vault_form_pick_project')}</option>
                                {projects.map((project) => (
                                    <option key={project.id} value={project.id}>
                                        {project.name}
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

                    {selected ? (
                        <div className="rounded-xl border border-sky-200 bg-sky-50/80 px-4 py-3 dark:border-sky-900 dark:bg-sky-950/30">
                            <p className="text-xs font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-200">
                                {t('vault_form_unit_calc')}
                            </p>
                            <p className="mt-1 text-sm text-slate-700 dark:text-slate-200">
                                {t('vault_form_unit_calc_line', {
                                    qty: quantity || '—',
                                    unit: rateUnit,
                                    rate,
                                    currency,
                                })}
                            </p>
                            <p
                                dir="ltr"
                                className="mt-2 font-sans text-2xl font-semibold tabular-nums text-slate-900 dark:text-white"
                            >
                                <MoneyAmount
                                    value={amount}
                                    label={currency}
                                    size="lg"
                                    showLabel={false}
                                />
                                <span className="ms-2 text-sm font-medium text-slate-500">
                                    {currency}
                                </span>
                            </p>
                            {holdPreview ? (
                                <p className="mt-2 text-xs text-slate-600 dark:text-slate-300">
                                    {data.apply_insurance
                                        ? t('vault_form_job_pay_split', {
                                              leaves: holdPreview.leaves,
                                              hold: holdPreview.hold,
                                              currency,
                                          })
                                        : t('vault_form_job_pay_no_hold', {
                                              amount: holdPreview.leaves,
                                              currency,
                                          })}
                                </p>
                            ) : null}
                        </div>
                    ) : null}

                    <InsuranceHoldToggle
                        checked={Boolean(data.apply_insurance)}
                        onChange={(next) => setData('apply_insurance', next)}
                        label={t('vault_form_apply_insurance')}
                        onLabel={t('vault_form_apply_insurance_on')}
                        offLabel={t('vault_form_apply_insurance_off')}
                        hintOn={t('vault_form_apply_insurance_hint_on')}
                        hintOff={t('vault_form_apply_insurance_hint_off')}
                        tone="sky"
                    />

                    <FormActions>
                        <PrimaryButton
                            disabled={processing || !staff.length || amount <= 0}
                            className="!bg-sky-600 hover:!bg-sky-500 dark:!bg-sky-400 dark:!text-sky-950"
                        >
                            {t('vault_form_save_unit_pay')}
                        </PrimaryButton>
                        <Link href={route('vault.job-pay.index')}>
                            <SecondaryButton type="button">{t('cancel')}</SecondaryButton>
                        </Link>
                    </FormActions>
                </form>
            </PageShell>
        </AuthenticatedLayout>
    );
}
