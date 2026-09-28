import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';

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
            <div className="py-8">
                <div className="mx-auto max-w-2xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70">
                    <StatusBadge status={expense.approval_status} />
                    <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('category')}</dt>
                            <dd className="capitalize">{categoryLabel}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('amount_iqd')}</dt>
                            <dd>
                                <MoneyAmount value={expense.amount_iqd} label={iqd} size="lg" />
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('expense_date')}</dt>
                            <dd className="tabular-nums">{expense.expense_date}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('supplier_person')}</dt>
                            <dd>{expense.supplier || '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('payment_method')}</dt>
                            <dd className="capitalize">{paymentLabel}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('created_by')}</dt>
                            <dd>{expense.creator?.name || '—'}</dd>
                        </div>
                        {expense.approver && (
                            <div>
                                <dt className="text-xs uppercase text-slate-400">{t('approved_by')}</dt>
                                <dd>{expense.approver.name}</dd>
                            </div>
                        )}
                        {expense.document && (
                            <div className="sm:col-span-2">
                                <dt className="text-xs uppercase text-slate-400">{t('receipt_file')}</dt>
                                <dd>
                                    <a
                                        href={route('documents.file', expense.document.id)}
                                        className="text-emerald-700 underline dark:text-emerald-400"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        {expense.document.original_name}
                                    </a>
                                </dd>
                            </div>
                        )}
                        {expense.description && (
                            <div className="sm:col-span-2">
                                <dt className="text-xs uppercase text-slate-400">{t('description')}</dt>
                                <dd className="whitespace-pre-wrap">{expense.description}</dd>
                            </div>
                        )}
                    </dl>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
