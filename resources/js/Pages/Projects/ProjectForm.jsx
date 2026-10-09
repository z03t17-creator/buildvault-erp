import DateInput from '@/Components/DateInput';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { isValidIsoDate } from '@/lib/isoDate';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function ProjectForm({ mode = 'create', project }) {
    const t = useTranslations();
    const editing = mode === 'edit';
    const [localErrors, setLocalErrors] = useState({});

    // Agreement totals removed from UI — keep DB columns at 0 on create so save never 500s.
    // On edit, preserve existing stored values without showing the block.
    const { data, setData, post, put, processing, errors } = useForm({
        name: project?.name || '',
        client: project?.client || '',
        description: project?.description || '',
        location: project?.location || '',
        status: project?.status || 'planning',
        start_date: project?.start_date ? String(project.start_date).slice(0, 10) : '',
        end_date: project?.end_date ? String(project.end_date).slice(0, 10) : '',
        total_budget_usd:
            editing && project?.total_budget_usd != null
                ? String(Number(project.total_budget_usd))
                : '0',
        contract_value_iqd:
            editing && project?.contract_value_iqd != null
                ? String(Math.round(Number(project.contract_value_iqd)))
                : '0',
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
