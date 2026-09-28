import PageHeader from '@/Components/PageHeader';
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
            <div className="py-8">
                <div className="mx-auto max-w-2xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70">
                    <p className="text-xs text-slate-500">{t('production_qty_hint')}</p>
                    <dl className="mt-2 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('worker')}</dt>
                            <dd>{production.worker?.name || '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('project')}</dt>
                            <dd>{production.project?.name || '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('unit_type')}</dt>
                            <dd>{unitDisplay}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('date')}</dt>
                            <dd>{production.recorded_on}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('assigned')}</dt>
                            <dd className="tabular-nums">{formatQty(production.assigned)}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('completed')}</dt>
                            <dd className="tabular-nums">{formatQty(production.completed)}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('received')}</dt>
                            <dd className="tabular-nums">{formatQty(production.received)}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('remaining')}</dt>
                            <dd className="tabular-nums text-amber-700 dark:text-amber-300">
                                {formatQty(production.remaining)}
                            </dd>
                            <p className="mt-1 text-xs text-slate-500">{t('remaining_formula')}</p>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('progress_pct')}</dt>
                            <dd className="tabular-nums">{formatQty(production.progress_pct)}%</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('entered_by')}</dt>
                            <dd>{production.enteredBy?.name || production.entered_by?.name || '—'}</dd>
                        </div>
                        {production.notes && (
                            <div className="sm:col-span-2">
                                <dt className="text-xs uppercase text-slate-400">{t('notes')}</dt>
                                <dd>{production.notes}</dd>
                            </div>
                        )}
                    </dl>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
