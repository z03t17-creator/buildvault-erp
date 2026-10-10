import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MobileCardList, { MobileCard } from '@/Components/MobileCardList';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { DeskRowActions } from '@/Components/StaffDesk';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function JobPayIndex({
    lines = [],
    canCreate = false,
    canEditRows = false,
    canConfirmHold = false,
}) {
    const t = useTranslations();
    // Anyone who can open the yellow record button, or the boss, can edit/delete rows.
    const canChange = Boolean(canCreate || canEditRows);
    const list = Array.isArray(lines) ? lines : [];
    const [confirmingId, setConfirmingId] = useState(null);

    const removeLine = (id) => {
        if (!window.confirm(t('confirm_delete'))) return;
        router.delete(route('vault.lines.staff-pay.destroy', id), { preserveScroll: true });
    };

    const confirmHold = (id) => {
        if (!canConfirmHold || confirmingId) {
            return;
        }
        setConfirmingId(id);
        router.post(
            route('vault.job-pay.confirm-hold', id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setConfirmingId(null),
            },
        );
    };

    const rowActions = (row) =>
        canChange ? (
            <DeskRowActions
                editHref={route('vault.lines.staff-pay.edit', row.id)}
                onDelete={() => removeLine(row.id)}
                t={t}
            />
        ) : null;

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('vault_form_job_pay')}
                    subtitle={t('job_pay_list_hint')}
                    icon={<NavIcon name="advances" className="text-lg text-amber-600 dark:text-amber-300" />}
                    actions={
                        canCreate ? (
                            <Link href={route('vault.lines.staff-pay.create')}>
                                <PrimaryButton
                                    type="button"
                                    className="!bg-amber-600 hover:!bg-amber-500 dark:!bg-amber-400 dark:!text-amber-950"
                                >
                                    <NavIcon name="advances" className="text-sm" />
                                    {t('job_pay_record')}
                                </PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('vault_form_job_pay')} />
            <PageShell className="!space-y-6">
                {list.length === 0 ? (
                    <EmptyState
                        icon="advances"
                        title={t('job_pay_empty_title')}
                        description={t('job_pay_empty_hint')}
                        action={
                            canCreate ? (
                                <Link href={route('vault.lines.staff-pay.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-amber-600 hover:!bg-amber-500"
                                    >
                                        <NavIcon name="advances" className="text-sm" />
                                        {t('job_pay_record')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                                <NavIcon name="advances" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('vault_form_job_pay')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('job_pay_table_hint', { count: list.length })}
                                </p>
                            </div>
                        </div>
                        <MobileCardList>
                            {list.map((row) => (
                                <MobileCard
                                    key={row.id}
                                    title={row.staff?.name || '—'}
                                    subtitle={row.purpose || row.occurred_on || ''}
                                    badge={
                                        <span dir="ltr" className="font-semibold tabular-nums text-slate-900 dark:text-slate-100">
                                            {row.amount} {row.currency}
                                        </span>
                                    }
                                    rows={[
                                        { label: t('date'), value: row.occurred_on || '—' },
                                        { label: t('project'), value: row.project?.name || '—' },
                                        {
                                            label: t('job_pay_hold_10'),
                                            value: `${row.hold_amount ?? 0} ${row.currency || ''}`,
                                        },
                                        { label: t('job_pay_payout_date'), value: row.unlock_date || '—' },
                                    ]}
                                    footer={rowActions(row)}
                                />
                            ))}
                        </MobileCardList>
                        <DataTable
                            minWidth="64rem"
                            caption={t('vault_form_job_pay')}
                            stickyFirstColumn
                            hideOnMobile
                        >
                            <thead>
                                <tr>
                                    <Th>{t('date')}</Th>
                                    <Th align="end">{t('amount')}</Th>
                                    <Th>{t('currency')}</Th>
                                    <Th>{t('staff')}</Th>
                                    <Th>{t('purpose')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th align="end">{t('job_pay_hold_10')}</Th>
                                    <Th>{t('job_pay_payout_date')}</Th>
                                    <Th>{t('job_pay_hold_status')}</Th>
                                    {canChange ? <Th>{t('actions')}</Th> : null}
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((row) => (
                                    <tr key={row.id}>
                                        <Td>
                                            <span dir="ltr" className="font-sans tabular-nums">
                                                {row.occurred_on || '—'}
                                            </span>
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={row.amount}
                                                label={row.currency}
                                                size="sm"
                                                showLabel={false}
                                            />
                                        </Td>
                                        <Td muted>{row.currency}</Td>
                                        <Td>
                                            <div className="space-y-2">
                                                {row.staff ? (
                                                    <Link
                                                        href={route('staff.show', row.staff.id)}
                                                        className="font-semibold text-teal-700 underline-offset-2 hover:underline dark:text-teal-300"
                                                    >
                                                        {row.staff.name}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                                {rowActions(row)}
                                            </div>
                                        </Td>
                                        <Td>{row.purpose || '—'}</Td>
                                        <Td muted>{row.project?.name || '—'}</Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={row.hold_amount}
                                                label={row.currency}
                                                size="sm"
                                                showLabel={false}
                                            />
                                        </Td>
                                        <Td>
                                            <span dir="ltr" className="font-sans tabular-nums">
                                                {row.unlock_date || '—'}
                                            </span>
                                        </Td>
                                        <Td>
                                            {row.hold_open ? (
                                                canConfirmHold ? (
                                                    <SecondaryButton
                                                        type="button"
                                                        disabled={confirmingId === row.id}
                                                        onClick={() => confirmHold(row.id)}
                                                        className="!min-h-0 !px-2.5 !py-1.5 text-xs !bg-amber-50 !text-amber-950 hover:!bg-amber-100 dark:!bg-amber-950/40 dark:!text-amber-100"
                                                    >
                                                        {t('job_pay_hold_confirm')}
                                                    </SecondaryButton>
                                                ) : (
                                                    <span className="text-xs font-semibold text-amber-800 dark:text-amber-200">
                                                        {t('job_pay_hold_owed')}
                                                    </span>
                                                )
                                            ) : row.hold_amount > 0 ? (
                                                <span className="inline-flex rounded-lg bg-teal-500/15 px-2 py-1 text-xs font-semibold text-teal-900 dark:text-teal-200">
                                                    {t('job_pay_hold_paid')}
                                                </span>
                                            ) : (
                                                '—'
                                            )}
                                        </Td>
                                        {canChange ? <Td>{rowActions(row)}</Td> : null}
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
