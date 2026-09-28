import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Show({ penalty, linkablePayouts }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const linkForm = useForm({ payout_id: linkablePayouts?.[0]?.id || '' });
    const canWaive = useCan('penalties.waive');
    const canApply = useCan('penalties.apply');
    const canLink = useCan('penalties.link');
    const canViewAny = useCan('penalties.viewAny');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`${t('penalties')} #${penalty.id}`}
                    subtitle={penalty.worker?.name}
                    actions={
                        <>
                            {canViewAny && (
                                <Link href={route('penalties.index')}>
                                    <SecondaryButton>{t('back')}</SecondaryButton>
                                </Link>
                            )}
                            {penalty.status === 'pending' && canApply && (
                                <PrimaryButton type="button" onClick={() => router.post(route('penalties.apply', penalty.id))}>
                                    {t('apply_penalty')}
                                </PrimaryButton>
                            )}
                            {penalty.status === 'pending' && canWaive && (
                                <SecondaryButton type="button" onClick={() => router.post(route('penalties.waive', penalty.id))}>
                                    {t('waive_penalty')}
                                </SecondaryButton>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={`${t('penalties')} #${penalty.id}`} />
            <div className="py-8">
                <div className="mx-auto max-w-2xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70">
                    <StatusBadge status={penalty.status} />
                    <dl className="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('amount_iqd')}</dt>
                            <dd>
                                <MoneyAmount
                                    value={penalty.amount_iqd_display ?? penalty.amount_iqd}
                                    label={iqd}
                                    size="lg"
                                    className="text-rose-700 dark:text-rose-300"
                                />
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('penalty_type')}</dt>
                            <dd>{t(`penalty_type_${penalty.type}`) || penalty.type}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('date')}</dt>
                            <dd>{penalty.occurred_on || '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('project')}</dt>
                            <dd>{penalty.project?.name}</dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-xs uppercase text-slate-400">{t('reason')}</dt>
                            <dd>{penalty.reason}</dd>
                        </div>
                        {penalty.notes && (
                            <div className="sm:col-span-2">
                                <dt className="text-xs uppercase text-slate-400">{t('notes')}</dt>
                                <dd>{penalty.notes}</dd>
                            </div>
                        )}
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('created_by')}</dt>
                            <dd>{penalty.creator?.name || '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('Status')}</dt>
                            <dd>{penalty.status}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('payout_link')}</dt>
                            <dd>
                                {penalty.payout_id ? (
                                    <Link href={route('payouts.show', penalty.payout_id)} className="text-emerald-700 underline dark:text-emerald-400">
                                        #{penalty.payout_id}
                                    </Link>
                                ) : '—'}
                            </dd>
                        </div>
                    </dl>

                    {canLink && penalty.status === 'pending' && (linkablePayouts || []).length > 0 && !penalty.payout_id && (
                        <form
                            className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700"
                            onSubmit={(e) => {
                                e.preventDefault();
                                linkForm.post(route('penalties.link', penalty.id));
                            }}
                        >
                            <div>
                                <label className="text-xs uppercase text-slate-400">{t('link_payout_optional')}</label>
                                <select
                                    className="mt-1 block rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-950"
                                    value={linkForm.data.payout_id}
                                    onChange={(e) => linkForm.setData('payout_id', e.target.value)}
                                >
                                    {linkablePayouts.map((p) => (
                                        <option key={p.id} value={p.id}>#{p.id} · {p.status}</option>
                                    ))}
                                </select>
                            </div>
                            <PrimaryButton disabled={linkForm.processing}>{t('link_for_reconcile')}</PrimaryButton>
                        </form>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
