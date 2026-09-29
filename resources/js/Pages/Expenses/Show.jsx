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
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                {label}
            </dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                {children}
            </dd>
        </div>
    );
}

export default function Show({ expense }) {
    const canApprove = useCan('expenses.approve');
    const canReject = useCan('expenses.reject');
    const canUpdate = useCan('expenses.update');
    const t = useTranslations();
    const iqd = t('currency_iqd') === 'currency_iqd' ? 'IQD' : t('currency_iqd');
    const pending = expense.approval_status === 'pending';

    const categoryLabel =
        t(`expense_category_${expense.category}`) !== `expense_category_${expense.category}`
            ? t(`expense_category_${expense.category}`)
            : expense.category;
    const paymentLabel = expense.payment_method
        ? t(`payment_${expense.payment_method}`) !== `payment_${expense.payment_method}`
            ? t(`payment_${expense.payment_method}`)
            : expense.payment_method
        : '—';

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
                            {pending && canUpdate && (
                                <Link href={route('expenses.edit', expense.id)}>
                                    <SecondaryButton type="button">{t('edit')}</SecondaryButton>
                                </Link>
                            )}
                            {pending && canApprove && (
                                <PrimaryButton
                                    type="button"
                                    onClick={() => router.post(route('expenses.approve', expense.id))}
                                >
                                    {t('approve')}
                                </PrimaryButton>
                            )}
                            {pending && canReject && (
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
                <DataPanel>
                    <StatusBadge status={expense.approval_status} />
                    <dl className="mt-5 grid gap-6 sm:grid-cols-2">
                        <Field label={t('category')}>
                            <span className="capitalize">{categoryLabel}</span>
                        </Field>
                        <Field label={`${t('amount_iqd')} (${iqd})`}>
                            <MoneyAmount value={expense.amount_iqd} label={iqd} size="lg" showLabel={false} />
                        </Field>
                        <Field label={t('expense_date')}>
                            <span className="tabular-nums" dir="ltr">{expense.expense_date}</span>
                        </Field>
                        <Field label={t('supplier_person')}>{expense.supplier || '—'}</Field>
                        <Field label={t('payment_method')}>
                            <span className="capitalize">{paymentLabel}</span>
                        </Field>
                        <Field label={t('created_by')}>{expense.creator?.name || '—'}</Field>
                        {expense.approver && (
                            <Field label={t('approved_by')}>{expense.approver.name}</Field>
                        )}
                        {expense.document && (
                            <div className="sm:col-span-2">
                                <Field label={t('receipt_file')}>
                                    <a
                                        href={route('documents.file', expense.document.id)}
                                        className="text-emerald-700 underline dark:text-emerald-400"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        {expense.document.original_name}
                                    </a>
                                </Field>
                            </div>
                        )}
                        {expense.description && (
                            <div className="sm:col-span-2">
                                <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                                    {t('description')}
                                </dt>
                                <dd className="mt-1.5 whitespace-pre-wrap text-sm font-medium text-slate-800 dark:text-slate-100">
                                    {expense.description}
                                </dd>
                            </div>
                        )}
                    </dl>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
