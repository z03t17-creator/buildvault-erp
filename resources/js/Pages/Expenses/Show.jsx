import DataPanel from '@/Components/DataPanel';
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
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{children}</dd>
        </div>
    );
}

export default function Show({ expense, availableCash, payAbility }) {
    const canApprove = useCan('expenses.approve');
    const canHold = useCan('expenses.hold');
    const canReject = useCan('expenses.reject');
    const canUpdate = useCan('expenses.update');
    const t = useTranslations();
    const awaiting = expense.approval_status === 'pending' || expense.approval_status === 'held';
    const currency = expense.currency || 'IQD';
    const amount = currency === 'USD' ? expense.amount_usd : expense.amount_iqd;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`${t('expense')} #${expense.id}`}
                    subtitle={expense.project?.name}
                    actions={
                        <>
                            <Link href={route('expenses.index')}>
                                <SecondaryButton>{t('back')}</SecondaryButton>
                            </Link>
                            {awaiting && canUpdate && (
                                <Link href={route('expenses.edit', expense.id)}>
                                    <SecondaryButton type="button">{t('edit')}</SecondaryButton>
                                </Link>
                            )}
                            {awaiting && canApprove && (
                                <PrimaryButton
                                    type="button"
                                    onClick={() => router.post(route('expenses.approve', expense.id))}
                                >
                                    {t('approve')}
                                </PrimaryButton>
                            )}
                            {awaiting && canHold && (
                                <SecondaryButton
                                    type="button"
                                    onClick={() => router.post(route('expenses.hold', expense.id))}
                                >
                                    {t('hold')}
                                </SecondaryButton>
                            )}
                            {awaiting && canReject && (
                                <SecondaryButton
                                    type="button"
                                    onClick={() => router.post(route('expenses.reject', expense.id))}
                                >
                                    {t('reject')}
                                </SecondaryButton>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={`${t('expense')} #${expense.id}`} />
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
                    <StatusBadge status={expense.approval_status} />
                    <dl className="mt-5 grid gap-6 sm:grid-cols-2">
                        <Field label={t('category')}>
                            <span className="capitalize">{expense.category}</span>
                        </Field>
                        <Field label={`${t('amount')} (${currency})`}>
                            <span className="font-mono tabular-nums">
                                <MoneyAmount value={amount} label={currency} size="lg" showLabel={false} />
                            </span>
                        </Field>
                        <Field label={t('currency')}>{currency}</Field>
                        <Field label={t('expense_date')}>
                            <span className="tabular-nums font-mono" dir="ltr">{expense.expense_date}</span>
                        </Field>
                        <Field label={t('supplier_person')}>{expense.supplier || '—'}</Field>
                        <Field label={t('created_by')}>{expense.creator?.name || '—'}</Field>
                        {expense.description && (
                            <div className="sm:col-span-2">
                                <Field label={t('description')}>{expense.description}</Field>
                            </div>
                        )}
                    </dl>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
