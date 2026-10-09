import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                {label}
            </dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                {children}
            </dd>
        </div>
    );
}

export default function Show({ payout, availableCash, payAbility }) {
    const t = useTranslations();
    const canApprove = useCan('payouts.approve');
    const canHold = useCan('payouts.hold');
    const canReject = useCan('payouts.reject');
    const canReconcile = useCan('payouts.reconcile');
    const awaiting = payout.status === 'pending' || payout.status === 'held';
    const currency = payout.currency || 'USD';
    const amount = currency === 'USD' ? payout.amount_usd : payout.amount_iqd;

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
                            {awaiting && canApprove && (
                                <PrimaryButton type="button" onClick={() => router.post(route('payouts.approve', payout.id))}>
                                    {t('approve')}
                                </PrimaryButton>
                            )}
                            {awaiting && canHold && (
                                <SecondaryButton type="button" onClick={() => router.post(route('payouts.hold', payout.id))}>
                                    {t('hold')}
                                </SecondaryButton>
                            )}
                            {awaiting && canReject && (
                                <SecondaryButton type="button" onClick={() => router.post(route('payouts.reject', payout.id))}>
                                    {t('reject')}
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
            <PageShell narrow>
                <div className="mb-4 grid gap-3 sm:grid-cols-2">
                    <div className="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                        <p className="text-xs uppercase text-slate-500">{t('available_cash')} USD</p>
                        <MoneyAmount value={availableCash?.available_usd} label="USD" size="lg" />
                    </div>
                    <div className="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                        <p className="text-xs uppercase text-slate-500">{t('available_cash')} IQD</p>
                        <MoneyAmount value={availableCash?.available_iqd} label="IQD" size="lg" />
                    </div>
                </div>
                {payAbility && (
                    <DataPanel title={t('ability_to_pay')}>
                        <p className={`text-sm font-medium ${payAbility.allowed ? 'text-emerald-700' : 'text-rose-700'}`}>
                            {payAbility.allowed
                                ? 'OK to approve — Available Cash covers this amount.'
                                : (payAbility.reasons || []).join(' ')}
                        </p>
                    </DataPanel>
                )}
                <DataPanel>
                    <StatusBadge status={payout.status} />
                    <dl className="mt-5 grid gap-6 sm:grid-cols-2">
                        <Field label="Category">
                            <span className="capitalize">{payout.category}</span>
                        </Field>
                        <Field label={`${t('amount')} (${currency})`}>
                            <span className="font-mono tabular-nums">
                                <MoneyAmount value={amount} label={currency} size="lg" showLabel={false} />
                            </span>
                        </Field>
                        <Field label={t('currency')}>{currency}</Field>
                        <Field label="Holdback">
                            <span dir="ltr" className="font-sans text-base font-semibold tracking-normal tabular-nums">{payout.retention_holdback}</span>
                        </Field>
                        <Field label="Worker">{payout.worker?.name || '—'}</Field>
                    </dl>
                </DataPanel>

                {(payout.retention_holds || []).length > 0 && (
                    <DataPanel title="Retention holds" padded={false}>
                        <DataTable caption="Retention holds" minWidth="28rem">
                            <thead>
                                <tr>
                                    <Th>#</Th>
                                    <Th align="end">Amount USD</Th>
                                    <Th>Status</Th>
                                    <Th>Matures</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {payout.retention_holds.map((h) => (
                                    <tr key={h.id}>
                                        <Td>{h.id}</Td>
                                        <Td align="end">
                                            <span dir="ltr" className="tabular-nums">{h.amount_usd}</span>
                                        </Td>
                                        <Td>{h.status}</Td>
                                        <Td muted>{h.maturity_date}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                )}

                {(payout.penalties || []).length > 0 && (
                    <DataPanel title="Linked penalties" padded={false}>
                        <DataTable caption="Linked penalties" minWidth="28rem">
                            <thead>
                                <tr>
                                    <Th>#</Th>
                                    <Th align="end">Amount</Th>
                                    <Th>Status</Th>
                                    <Th>Note</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {payout.penalties.map((pen) => (
                                    <tr key={pen.id}>
                                        <Td>
                                            <Link href={route('penalties.show', pen.id)} className="text-emerald-700 underline dark:text-emerald-400">
                                                #{pen.id}
                                            </Link>
                                        </Td>
                                        <Td align="end">
                                            <span dir="ltr" className="tabular-nums">{pen.amount_usd}</span>
                                        </Td>
                                        <Td>{pen.status}</Td>
                                        <Td muted>{pen.deducted_from_payout ? 'deducted' : '—'}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
