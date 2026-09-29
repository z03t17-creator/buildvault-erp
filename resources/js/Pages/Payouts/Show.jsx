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

export default function Show({ payout }) {
    const t = useTranslations();
    const iqd = t('IQD');
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
            <PageShell narrow>
                <DataPanel>
                    <StatusBadge status={payout.status} />
                    <dl className="mt-5 grid gap-6 sm:grid-cols-2">
                        <Field label="Category">
                            <span className="capitalize">{payout.category}</span>
                        </Field>
                        <Field label={`Amount (${iqd})`}>
                            <MoneyAmount value={payout.amount_iqd ?? payout.amount_usd} label={payout.amount_iqd != null ? iqd : 'USD'} size="lg" />
                        </Field>
                        <Field label="Amount USD">
                            <span dir="ltr" className="font-sans text-base font-semibold tracking-normal tabular-nums">{payout.amount_usd}</span>
                        </Field>
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
