import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import DateInput from '@/Components/DateInput';
import EmptyState from '@/Components/EmptyState';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function CountStat({ label, value, hint }) {
    return (
        <div className="bv-card px-4 py-3.5 ring-2 ring-slate-500/30 dark:ring-slate-400/30">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div className="mt-1.5 font-sans text-2xl font-semibold tabular-nums text-slate-900 dark:text-white sm:text-3xl">
                {value ?? 0}
            </div>
            {hint ? (
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
            ) : null}
        </div>
    );
}

function ActionChip({ label }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-lg bg-slate-500/15 px-2 py-1 text-xs font-semibold text-slate-800 dark:bg-slate-400/15 dark:text-slate-200">
            <NavIcon name="audit" className="text-xs" />
            {label}
        </span>
    );
}

export default function Log({ entries, filters, actions, users, overview }) {
    const t = useTranslations();
    const canBackups = useCan('vault.backups');
    const canUsers = useCan('users.viewAny');
    const [action, setAction] = useState(filters?.action || '');
    const [userId, setUserId] = useState(
        filters?.user_id ? String(filters.user_id) : '',
    );
    const [from, setFrom] = useState(filters?.from || '');
    const [to, setTo] = useState(filters?.to || '');
    const rows = entries || [];
    const totals = overview || { count: rows.length, limit: 200 };

    const apply = (e) => {
        e.preventDefault();
        router.get(
            route('audit.index'),
            {
                action: action || undefined,
                user_id: userId || undefined,
                from: from || undefined,
                to: to || undefined,
                subject_user_id: filters?.subject_user_id || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const clear = () => {
        setAction('');
        setUserId('');
        setFrom('');
        setTo('');
        router.get(route('audit.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('audit')}
                    subtitle={t('audit_page_hint')}
                    icon={<NavIcon name="audit" className="text-lg" />}
                    actions={
                        <>
                            {canBackups && (
                                <Link href={route('backups.index')}>
                                    <SecondaryButton type="button">
                                        <NavIcon name="backups" className="text-sm" />
                                        {t('backups')}
                                    </SecondaryButton>
                                </Link>
                            )}
                            {canUsers && (
                                <Link href={route('users.index')}>
                                    <SecondaryButton type="button">
                                        <NavIcon name="users" className="text-sm" />
                                        {t('users')}
                                    </SecondaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={t('audit')} />
            <PageShell className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-4 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-500/15 text-slate-800 dark:bg-slate-400/15 dark:text-slate-200">
                            <NavIcon name="filter" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('audit_filters_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('audit_filters_hint')}
                            </p>
                        </div>
                    </div>
                    <form noValidate onSubmit={apply}>
                        <FormSection cols={2}>
                            <FormField>
                                <InputLabel value={t('audit_action')} />
                                <select
                                    className={fieldClass}
                                    value={action}
                                    onChange={(e) => setAction(e.target.value)}
                                >
                                    <option value="">{t('audit_all_actions')}</option>
                                    {(actions || []).map((a) => (
                                        <option key={a.value} value={a.value}>
                                            {a.label}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField>
                                <InputLabel value={t('user')} />
                                <select
                                    className={fieldClass}
                                    value={userId}
                                    onChange={(e) => setUserId(e.target.value)}
                                >
                                    <option value="">{t('audit_all_users')}</option>
                                    {(users || []).map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField>
                                <InputLabel value={t('audit_from')} />
                                <DateInput
                                    className="mt-1"
                                    value={from}
                                    onValueChange={setFrom}
                                    openOnFocus={false}
                                />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('audit_to')} />
                                <DateInput
                                    className="mt-1"
                                    value={to}
                                    onValueChange={setTo}
                                    openOnFocus={false}
                                />
                            </FormField>
                        </FormSection>
                        <FormActions className="mt-4">
                            <PrimaryButton
                                type="submit"
                                className="!bg-slate-800 hover:!bg-slate-700 dark:!bg-slate-200 dark:!text-slate-900"
                            >
                                <NavIcon name="filter" className="text-sm" />
                                {t('filter')}
                            </PrimaryButton>
                            <SecondaryButton type="button" onClick={clear}>
                                {t('audit_clear')}
                            </SecondaryButton>
                        </FormActions>
                    </form>
                </section>

                <section>
                    <div className="mb-3">
                        <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('audit_overview')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('audit_overview_hint', { count: totals.count })}
                        </p>
                    </div>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <CountStat
                            label={t('audit_stat_shown')}
                            value={totals.count}
                            hint={t('audit_stat_shown_hint', {
                                limit: totals.limit || 200,
                            })}
                        />
                    </div>
                </section>

                <DataPanel padded={false}>
                    <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                        <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-500/15 text-slate-800 dark:bg-slate-400/15 dark:text-slate-200">
                            <NavIcon name="audit" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('audit_table_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('audit_table_hint')}
                            </p>
                        </div>
                    </div>
                    {rows.length === 0 ? (
                        <div className="p-4">
                            <EmptyState
                                icon="audit"
                                title={t('audit_empty_title')}
                                description={t('audit_empty_hint')}
                            />
                        </div>
                    ) : (
                        <DataTable minWidth="48rem" caption={t('audit')}>
                            <thead>
                                <tr>
                                    <Th>{t('audit_when')}</Th>
                                    <Th>{t('audit_action')}</Th>
                                    <Th>{t('user')}</Th>
                                    <Th>{t('description')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.id}>
                                        <Td
                                            muted
                                            className="whitespace-nowrap font-sans text-xs tabular-nums"
                                        >
                                            {row.created_at
                                                ? new Date(
                                                      row.created_at,
                                                  ).toLocaleString()
                                                : '—'}
                                        </Td>
                                        <Td>
                                            <ActionChip
                                                label={
                                                    row.action_label ||
                                                    row.action ||
                                                    '—'
                                                }
                                            />
                                        </Td>
                                        <Td muted>
                                            {row.user?.name || t('audit_system')}
                                        </Td>
                                        <Td>{row.description || '—'}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
