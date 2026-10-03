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
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

const moneyFieldClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 font-sans text-lg font-semibold tabular-nums shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function isValidIsoDate(value) {
    if (!value) {
        return true; // optional
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return false;
    }
    const d = new Date(`${value}T00:00:00`);
    return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === value;
}

function AgreementCard({
    code,
    label,
    active,
    amount,
    onSelect,
    onAmountChange,
    amountError,
    hint,
    t,
    allowDecimals,
}) {
    return (
        <div
            className={
                'w-full rounded-2xl border p-4 transition ' +
                (active
                    ? 'border-slate-600 bg-slate-50 ring-2 ring-slate-500/20 dark:border-slate-400 dark:bg-slate-900'
                    : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-950')
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
                    <p className="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">
                        {code === 'USD'
                            ? t('project_form_agreement_usd')
                            : t('project_form_agreement_iqd')}
                    </p>
                    <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {active ? t('project_form_amount_active') : hint}
                    </p>
                </div>
                <span
                    className={
                        'inline-flex h-9 w-9 items-center justify-center rounded-xl text-sm font-bold ' +
                        (active
                            ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800')
                    }
                >
                    {code === 'USD' ? '$' : 'د.ع'}
                </span>
            </button>

            {active && (
                <div className="mt-4 border-t border-slate-200/80 pt-4 dark:border-slate-700">
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

function PreviewStat({ label, value, currency }) {
    return (
        <div className="bv-card px-4 py-3.5">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div
                dir="ltr"
                className="mt-1.5 font-sans text-2xl font-semibold tracking-normal tabular-nums text-slate-900 dark:text-white"
            >
                {value == null || value === '' || Number(value) <= 0 ? (
                    '—'
                ) : (
                    <MoneyAmount
                        value={value}
                        label={currency}
                        size="xl"
                        showLabel={false}
                    />
                )}
            </div>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{currency}</p>
        </div>
    );
}

export default function ProjectForm({ mode = 'create', project }) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const editing = mode === 'edit';
    const [localErrors, setLocalErrors] = useState({});
    const [activeMoney, setActiveMoney] = useState('IQD');

    const { data, setData, post, put, processing, errors } = useForm({
        name: project?.name || '',
        client: project?.client || '',
        description: project?.description || '',
        location: project?.location || '',
        // Contract number is not collected on the form; column stays nullable.
        // Status is not shown on the form; create defaults to planning,
        // edit preserves the existing value when saving other fields.
        status: project?.status || 'planning',
        start_date: project?.start_date ? String(project.start_date).slice(0, 10) : '',
        end_date: project?.end_date ? String(project.end_date).slice(0, 10) : '',
        total_budget_usd:
            project?.total_budget_usd != null && Number(project.total_budget_usd) > 0
                ? String(Number(project.total_budget_usd))
                : '',
        contract_value_iqd:
            project?.contract_value_iqd != null && Number(project.contract_value_iqd) > 0
                ? String(Math.round(Number(project.contract_value_iqd)))
                : '',
        budget_iqd:
            project?.budget_iqd != null && Number(project.budget_iqd) > 0
                ? String(Math.round(Number(project.budget_iqd)))
                : '',
    });

    const mergedErrors = useMemo(
        () => ({ ...localErrors, ...errors }),
        [localErrors, errors],
    );

    const title = editing
        ? t('project_form_edit_title', { name: project?.name || '' })
        : t('create_project');

    const backHref = editing
        ? route('projects.show', project.id)
        : route('projects.index');

    const validate = () => {
        const next = {};
        if (!String(data.name || '').trim()) {
            next.name = t('validation_project_name_required');
        }
        if (data.start_date && !isValidIsoDate(data.start_date)) {
            next.start_date = t('validation_date_required');
        }
        if (data.end_date && !isValidIsoDate(data.end_date)) {
            next.end_date = t('validation_date_required');
        }
        if (
            isValidIsoDate(data.start_date) &&
            isValidIsoDate(data.end_date) &&
            data.start_date &&
            data.end_date &&
            data.end_date < data.start_date
        ) {
            next.end_date = t('validation_end_after_start');
        }
        if (data.total_budget_usd !== '' && Number(data.total_budget_usd) < 0) {
            next.total_budget_usd = t('validation_amount_required');
        }
        if (data.contract_value_iqd !== '' && Number(data.contract_value_iqd) < 0) {
            next.contract_value_iqd = t('validation_amount_required');
        }
        if (data.budget_iqd !== '' && Number(data.budget_iqd) < 0) {
            next.budget_iqd = t('validation_amount_required');
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
            put(route('projects.update', project.id));
        } else {
            post(route('projects.store'));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={title}
                    subtitle={t('project_form_hint')}
                    icon={<NavIcon name="projects" className="text-lg" />}
                    actions={
                        <Link href={backHref}>
                            <SecondaryButton type="button">
                                <NavIcon name="projects" className="text-sm" />
                                {editing ? t('project') : t('projects')}
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
                            {t('project_form_agreement_title')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('project_form_agreement_hint')}
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <AgreementCard
                            code="USD"
                            label={usd}
                            active={activeMoney === 'USD'}
                            amount={data.total_budget_usd}
                            onSelect={() => setActiveMoney('USD')}
                            onAmountChange={(raw) => setData('total_budget_usd', raw)}
                            amountError={
                                activeMoney === 'USD'
                                    ? mergedErrors.total_budget_usd
                                    : undefined
                            }
                            hint={t('project_form_tap_usd')}
                            t={t}
                            allowDecimals
                        />
                        <AgreementCard
                            code="IQD"
                            label={iqd}
                            active={activeMoney === 'IQD'}
                            amount={data.contract_value_iqd}
                            onSelect={() => setActiveMoney('IQD')}
                            onAmountChange={(raw) => setData('contract_value_iqd', raw)}
                            amountError={
                                activeMoney === 'IQD'
                                    ? mergedErrors.contract_value_iqd
                                    : undefined
                            }
                            hint={t('project_form_tap_iqd')}
                            t={t}
                            allowDecimals={false}
                        />
                    </div>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <PreviewStat
                            label={t('projects_budget_usd')}
                            value={data.total_budget_usd}
                            currency={usd}
                        />
                        <PreviewStat
                            label={t('contract_value')}
                            value={data.contract_value_iqd}
                            currency={iqd}
                        />
                    </div>
                    <InputError
                        message={
                            activeMoney === 'USD'
                                ? undefined
                                : mergedErrors.total_budget_usd
                        }
                        className="mt-2"
                    />
                    <InputError
                        message={
                            activeMoney === 'IQD'
                                ? undefined
                                : mergedErrors.contract_value_iqd
                        }
                        className="mt-1"
                    />
                </section>

                <form noValidate onSubmit={submit} className="bv-card space-y-4 p-4 sm:p-5">
                    <FormSection cols={3}>
                        <FormField>
                            <InputLabel value={t('name')} htmlFor="name" />
                            <TextInput
                                id="name"
                                className={fieldClass}
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder={t('project_form_name_placeholder')}
                                autoComplete="off"
                            />
                            <InputError message={mergedErrors.name} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('client')} htmlFor="client" />
                            <TextInput
                                id="client"
                                className={fieldClass}
                                value={data.client}
                                onChange={(e) => setData('client', e.target.value)}
                                placeholder={t('project_form_client_placeholder')}
                            />
                            <InputError message={mergedErrors.client} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('location')} htmlFor="location" />
                            <TextInput
                                id="location"
                                className={fieldClass}
                                value={data.location}
                                onChange={(e) => setData('location', e.target.value)}
                                placeholder={t('project_form_location_placeholder')}
                            />
                            <InputError message={mergedErrors.location} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('start_date')} htmlFor="start_date" />
                            <DateInput
                                id="start_date"
                                className="mt-1"
                                value={data.start_date}
                                onValueChange={(next) => setData('start_date', next)}
                            />
                            <InputError message={mergedErrors.start_date} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel value={t('end_date')} htmlFor="end_date" />
                            <DateInput
                                id="end_date"
                                className="mt-1"
                                value={data.end_date}
                                onValueChange={(next) => setData('end_date', next)}
                            />
                            <p className="mt-1 text-xs text-slate-400">{t('date_format_hint')}</p>
                            <InputError message={mergedErrors.end_date} className="mt-1" />
                        </FormField>

                        <FormField>
                            <InputLabel
                                value={t('projects_budget_iqd')}
                                htmlFor="budget_iqd"
                            />
                            <MoneyInput
                                id="budget_iqd"
                                className={moneyFieldClass}
                                value={data.budget_iqd}
                                onValueChange={(raw) => setData('budget_iqd', raw)}
                                allowDecimals={false}
                                placeholder="0"
                            />
                            <p className="mt-1 text-xs text-slate-400">
                                {t('project_form_budget_iqd_hint')}
                            </p>
                            <InputError message={mergedErrors.budget_iqd} className="mt-1" />
                        </FormField>

                        <FormField className="sm:col-span-2 lg:col-span-2">
                            <InputLabel value={t('description')} htmlFor="description" />
                            <textarea
                                id="description"
                                className={`${fieldClass} min-h-[4.5rem] py-2`}
                                rows={2}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder={t('project_form_description_placeholder')}
                            />
                            <InputError message={mergedErrors.description} className="mt-1" />
                        </FormField>
                    </FormSection>

                    <FormActions>
                        <PrimaryButton
                            disabled={processing}
                            className="!bg-slate-800 hover:!bg-slate-700 dark:!bg-slate-200 dark:!text-slate-900 dark:hover:!bg-white"
                        >
                            <NavIcon name="projects" className="text-sm" />
                            {editing ? t('update') : t('create_project')}
                        </PrimaryButton>
                        <Link href={backHref}>
                            <SecondaryButton type="button">{t('cancel')}</SecondaryButton>
                        </Link>
                    </FormActions>
                </form>
            </PageShell>
        </AuthenticatedLayout>
    );
}
