import DataPanel from '@/Components/DataPanel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

function formatQty(n) {
    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
    }).format(Number(n) || 0);
}

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{children}</dd>
        </div>
    );
}

export default function Show({ production }) {
    const t = useTranslations();
    const canUpdate = useCan('productions.update');
    const canViewAny = useCan('productions.viewAny');

    const unitKey = `unit_type_${production.unit_type}`;
    const unitBase = t(unitKey) !== unitKey ? t(unitKey) : production.unit_type;
    const unitDisplay =
        production.unit_type === 'other' && production.unit_label
            ? `${unitBase}: ${production.unit_label}`
            : unitBase;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`${t('production')} #${production.id}`}
                    subtitle={production.worker?.name}
                    actions={
                        <>
                            {canViewAny && (
                                <Link href={route('productions.index')}>
                                    <SecondaryButton>{t('back')}</SecondaryButton>
                                </Link>
                            )}
                            {canUpdate && (
                                <Link href={route('productions.edit', production.id)}>
                                    <SecondaryButton>{t('edit')}</SecondaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={`${t('production')} #${production.id}`} />
            <PageShell narrow>
                <DataPanel>
                    <p className="text-xs text-slate-500">{t('production_qty_hint')}</p>
                    <dl className="mt-4 grid gap-6 sm:grid-cols-2">
                        <Field label={t('worker')}>{production.worker?.name || '—'}</Field>
                        <Field label={t('project')}>{production.project?.name || '—'}</Field>
                        <Field label={t('unit_type')}>{unitDisplay}</Field>
                        <Field label={t('date')}>{production.recorded_on}</Field>
                        <Field label={t('assigned')}>
                            <span className="tabular-nums" dir="ltr">{formatQty(production.assigned)}</span>
                        </Field>
                        <Field label={t('completed')}>
                            <span className="tabular-nums" dir="ltr">{formatQty(production.completed)}</span>
                        </Field>
                        <Field label={t('received')}>
                            <span className="tabular-nums" dir="ltr">{formatQty(production.received)}</span>
                        </Field>
                        <Field label={t('remaining')}>
                            <span className="tabular-nums text-amber-700 dark:text-amber-300" dir="ltr">
                                {formatQty(production.remaining)}
                            </span>
                            <p className="mt-1 text-xs font-normal text-slate-500">{t('remaining_formula')}</p>
                        </Field>
                        <Field label={t('progress_pct')}>
                            <span className="tabular-nums" dir="ltr">{formatQty(production.progress_pct)}%</span>
                        </Field>
                        <Field label={t('entered_by')}>
                            {production.enteredBy?.name || production.entered_by?.name || '—'}
                        </Field>
                        {production.notes && (
                            <div className="sm:col-span-2">
                                <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{t('notes')}</dt>
                                <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{production.notes}</dd>
                            </div>
                        )}
                    </dl>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
