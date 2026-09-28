import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';

export default function Show({ payout }) {
    const canApprove = useCan('payouts.approve');
    const canReject = useCan('payouts.reject');
    const canReconcile = useCan('payouts.reconcile');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Payout #${payout.id}`}
                    subtitle={payout.project?.name}
                    actions={
                        <>
                            <Link href={route('payouts.index')}>
                                <SecondaryButton>Back</SecondaryButton>
                            </Link>
                            {payout.status === 'pending' && canApprove && (
                                <PrimaryButton type="button" onClick={() => router.post(route('payouts.approve', payout.id))}>
                                    Approve
                                </PrimaryButton>
                            )}
                            {payout.status === 'pending' && canReject && (
                                <SecondaryButton type="button" onClick={() => router.post(route('payouts.reject', payout.id))}>
                                    Reject
                                </SecondaryButton>
                            )}
                            {payout.status === 'approved' && canReconcile && (
                                <PrimaryButton type="button" onClick={() => router.post(route('payouts.reconcile', payout.id))}>
                                    Reconcile
                                </PrimaryButton>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={`Payout #${payout.id}`} />
            <div className="py-8">
                <div className="mx-auto max-w-2xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70">
                    <StatusBadge status={payout.status} />
                    <dl className="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                        <div><dt className="text-xs uppercase text-slate-400">Category</dt><dd className="capitalize">{payout.category}</dd></div>
                        <div><dt className="text-xs uppercase text-slate-400">Amount IQD</dt><dd><MoneyAmount value={payout.amount_iqd ?? payout.amount_usd} label={payout.amount_iqd != null ? 'IQD' : 'USD'} size="lg" /></dd></div>
                        <div><dt className="text-xs uppercase text-slate-400">Amount USD</dt><dd dir="ltr" className="font-display text-base font-semibold tabular-nums">{payout.amount_usd}</dd></div>
                        <div><dt className="text-xs uppercase text-slate-400">Holdback</dt><dd dir="ltr" className="font-display text-base font-semibold tabular-nums">{payout.retention_holdback}</dd></div>
                        <div><dt className="text-xs uppercase text-slate-400">Worker</dt><dd>{payout.worker?.name || '—'}</dd></div>
                    </dl>
                    {(payout.retention_holds || []).length > 0 && (
                        <div className="pt-4 border-t border-slate-200 dark:border-slate-700">
                            <h3 className="font-semibold text-sm">Retention holds</h3>
                            <ul className="mt-2 space-y-1 text-sm">
                                {payout.retention_holds.map((h) => (
                                    <li key={h.id}>#{h.id}: {h.amount_usd} USD · {h.status} · matures {h.maturity_date}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {(payout.penalties || []).length > 0 && (
                        <div className="pt-4 border-t border-slate-200 dark:border-slate-700">
                            <h3 className="font-semibold text-sm">Linked penalties</h3>
                            <ul className="mt-2 space-y-1 text-sm">
                                {payout.penalties.map((pen) => (
                                    <li key={pen.id}>
                                        <Link href={route('penalties.show', pen.id)} className="text-emerald-700 underline dark:text-emerald-400">
                                            #{pen.id}
                                        </Link>
                                        : {pen.amount_usd} · {pen.status}
                                        {pen.deducted_from_payout ? ' · deducted' : ''}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
