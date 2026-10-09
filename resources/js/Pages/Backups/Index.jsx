import DataPanel from '@/Components/DataPanel';
import DataRecordsTabs from '@/Components/DataRecordsTabs';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function CountStat({ label, value, hint, active = false }) {
    return (
        <div
            className={
                'bv-card px-4 py-3.5 ' +
                (active ? 'ring-2 ring-slate-500/40 dark:ring-slate-400/40' : '')
            }
        >
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

function StatusChip({ status, t }) {
    const key = `status_${status}`;
    const label = t(key) !== key ? t(key) : status || '—';
    const tones = {
        completed: 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300',
        failed: 'bg-rose-500/15 text-rose-900 dark:text-rose-300',
        running: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        pending: 'bg-slate-500/15 text-slate-800 dark:text-slate-200',
    };

    return (
        <span
            className={
                'inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold capitalize ' +
                (tones[status] || tones.pending)
            }
        >
            {label}
        </span>
    );
}

function formatBytes(n) {
    const bytes = Number(n) || 0;
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function typeLabel(type, t) {
    const key = `backup_type_${type}`;
    return t(key) !== key ? t(key) : type;
}

export default function Index({ backups, types, schedule, overview }) {
    const t = useTranslations();
    const canAudit = useCan('vault.audit');
    const list = backups || [];
    const typeOptions = types || ['full', 'database', 'files'];
    const [confirming, setConfirming] = useState(false);
    const totals = overview || {
        count: list.length,
        completed: 0,
        failed: 0,
        running: 0,
    };

    const form = useForm({
        type: 'full',
    });

    const submit = (e) => {
        e.preventDefault();
        if (!confirming) {
            setConfirming(true);
            return;
        }
        form.post(route('backups.store'), {
            onFinish: () => setConfirming(false),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('nav_backups')}
                    subtitle={t('backups_page_hint')}
                    icon={<NavIcon name="backups" className="text-lg" />}
                    actions={
                        canAudit ? (
                            <Link href={route('audit.index')}>
                                <SecondaryButton type="button">
                                    <NavIcon name="audit" className="text-sm" />
                                    {t('audit')}
                                </SecondaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('nav_backups')} />
            <PageShell className="!space-y-6">
                <DataRecordsTabs />
                <section>
                    <div className="mb-3">
                        <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('backups_overview')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('backups_overview_hint', { count: totals.count })}
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <CountStat
                            label={t('backups_stat_shown')}
                            value={totals.count}
                            hint={t('backups_stat_shown_hint')}
                            active
                        />
                        <CountStat
                            label={t('status_completed')}
                            value={totals.completed}
                            hint={t('backups_stat_completed_hint')}
                        />
                        <CountStat
                            label={t('status_failed')}
                            value={totals.failed}
                            hint={t('backups_stat_failed_hint')}
                        />
                        <CountStat
                            label={t('backups_stat_running')}
                            value={totals.running}
                            hint={t('backups_stat_running_hint')}
                        />
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="bv-card p-4 sm:p-5">
                        <div className="mb-4 flex items-start gap-3">
                            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-500/15 text-slate-800 dark:bg-slate-400/15 dark:text-slate-200">
                                <NavIcon name="backups" className="text-base" />
                            </span>
                            <div>
                                <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                    {t('backups_run_title')}
                                </h2>
                                <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                    {t('backups_run_hint')}
                                </p>
                            </div>
                        </div>
                        <form noValidate onSubmit={submit}>
                            <FormSection cols={1}>
                                <FormField>
                                    <InputLabel value={t('backups_type')} />
                                    <select
                                        className={fieldClass}
                                        value={form.data.type}
                                        onChange={(e) => {
                                            form.setData('type', e.target.value);
                                            setConfirming(false);
                                        }}
                                    >
                                        {typeOptions.map((type) => (
                                            <option key={type} value={type}>
                                                {typeLabel(type, t)}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                            </FormSection>
                            <FormActions className="mt-4">
                                <PrimaryButton
                                    type="submit"
                                    disabled={form.processing}
                                    className="!bg-slate-800 hover:!bg-slate-700 dark:!bg-slate-200 dark:!text-slate-900"
                                >
                                    <NavIcon name="backups" className="text-sm" />
                                    {form.processing
                                        ? t('backups_running')
                                        : confirming
                                          ? t('backups_confirm')
                                          : t('backups_trigger')}
                                </PrimaryButton>
                            </FormActions>
                        </form>
                    </section>

                    <section className="bv-card p-4 sm:p-5">
                        <div className="mb-4 flex items-start gap-3">
                            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-500/15 text-slate-800 dark:bg-slate-400/15 dark:text-slate-200">
                                <NavIcon name="calendar" className="text-base" />
                            </span>
                            <div>
                                <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                    {t('backups_schedule_title')}
                                </h2>
                                <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                    {schedule?.note || t('backups_schedule_hint')}
                                </p>
                            </div>
                        </div>
                        <pre
                            dir="ltr"
                            className="overflow-x-auto rounded-xl bg-slate-900 px-3 py-2.5 font-sans text-xs text-emerald-200"
                        >
                            {schedule?.cron ||
                                '* * * * * php artisan schedule:run'}
                        </pre>
                        <p
                            dir="ltr"
                            className="mt-2 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {t('backups_schedule_direct')}:{' '}
                            <code>{schedule?.direct || schedule?.command}</code>
                        </p>
                    </section>
                </div>

                <DataPanel padded={false}>
                    <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                        <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-500/15 text-slate-800 dark:bg-slate-400/15 dark:text-slate-200">
                            <NavIcon name="backups" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('backups_table_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('backups_table_hint')}
                            </p>
                        </div>
                    </div>
                    {list.length === 0 ? (
                        <div className="p-4">
                            <EmptyState
                                icon="backups"
                                title={t('backups_empty_title')}
                                description={t('backups_empty_hint')}
                            />
                        </div>
                    ) : (
                        <DataTable minWidth="52rem" caption={t('backups')}>
                            <thead>
                                <tr>
                                    <Th>{t('backups_col_id')}</Th>
                                    <Th>{t('backups_type')}</Th>
                                    <Th>{t('status')}</Th>
                                    <Th>{t('backups_col_file')}</Th>
                                    <Th>{t('backups_col_size')}</Th>
                                    <Th>{t('backups_col_by')}</Th>
                                    <Th>{t('backups_col_finished')}</Th>
                                    <Th align="end">{t('actions')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((b) => (
                                    <tr key={b.id}>
                                        <Td muted className="font-sans tabular-nums">
                                            #{b.id}
                                        </Td>
                                        <Td>{typeLabel(b.type, t)}</Td>
                                        <Td>
                                            <StatusChip status={b.status} t={t} />
                                        </Td>
                                        <Td
                                            className="max-w-xs truncate"
                                            title={b.filename || ''}
                                        >
                                            {b.filename || '—'}
                                        </Td>
                                        <Td muted className="font-sans tabular-nums">
                                            {formatBytes(b.size_bytes)}
                                        </Td>
                                        <Td muted>
                                            {b.created_by || t('backups_cron')}
                                        </Td>
                                        <Td
                                            muted
                                            className="font-sans text-xs tabular-nums"
                                        >
                                            {b.finished_at
                                                ? new Date(
                                                      b.finished_at,
                                                  ).toLocaleString()
                                                : '—'}
                                        </Td>
                                        <Td align="end">
                                            {b.downloadable ? (
                                                <a
                                                    href={route(
                                                        'backups.download',
                                                        b.id,
                                                    )}
                                                >
                                                    <SecondaryButton type="button">
                                                        {t('download')}
                                                    </SecondaryButton>
                                                </a>
                                            ) : (
                                                <span className="text-xs text-slate-400">
                                                    —
                                                </span>
                                            )}
                                        </Td>
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
