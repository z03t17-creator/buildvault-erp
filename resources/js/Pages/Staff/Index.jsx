import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MobileCardList, { MobileCard } from '@/Components/MobileCardList';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import { DeskRowActions, PayModelChip, payModelOf } from '@/Components/StaffDesk';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ staff = [], canCreate = false, canEditRows = false }) {
    const t = useTranslations();
    const canChange = canCreate || canEditRows;
    const list = Array.isArray(staff) ? staff : [];
    const counts = {
        monthly: list.filter((person) => payModelOf(person) === 'monthly').length,
        daily: list.filter((person) => payModelOf(person) === 'daily').length,
        unit: list.filter((person) => payModelOf(person) === 'unit').length,
    };

    const removeStaff = (id) => {
        if (!window.confirm(t('confirm_delete'))) return;
        router.delete(route('staff.destroy', id), { preserveScroll: true });
    };

    const paySummary = (person) => {
        const model = payModelOf(person);
        if (model === 'monthly' && person.monthly_salary != null) {
            return `${person.monthly_salary} ${person.currency || ''}`;
        }
        if (model === 'daily' && person.day_rate != null) {
            return `${person.day_rate} ${person.currency || ''}/${t('staff_pay_day_short')}`;
        }
        if (model === 'unit') {
            return person.rates_count > 0
                ? t('staff_rates_count', { count: person.rates_count })
                : '—';
        }
        return '—';
    };

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('staff_roster_title')}
                    subtitle={t('staff_roster_hint')}
                    icon={<NavIcon name="workers" className="text-lg text-teal-600 dark:text-teal-300" />}
                    actions={
                        canCreate ? (
                            <Link href={route('staff.create')}>
                                <PrimaryButton type="button">
                                    <NavIcon name="workers" className="text-sm" />
                                    {t('vault_form_add_staff')}
                                </PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('staff_roster_title')} />
            <PageShell className="!space-y-6">
                <div className="grid gap-3 sm:grid-cols-3">
                    {[
                        ['monthly', counts.monthly, 'bg-teal-400/15 text-teal-100'],
                        ['daily', counts.daily, 'bg-amber-400/15 text-amber-100'],
                        ['unit', counts.unit, 'bg-sky-400/15 text-sky-100'],
                    ].map(([model, count, tone]) => (
                        <div key={model} className="bv-card px-4 py-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                {t(`staff_pay_${model}`)}
                            </p>
                            <p className={`mt-2 inline-flex rounded-lg px-2 py-1 text-lg font-semibold tabular-nums ${tone}`}>
                                {count}
                            </p>
                        </div>
                    ))}
                </div>
                {list.length === 0 ? (
                    <EmptyState
                        icon="workers"
                        title={t('staff_roster_empty_title')}
                        description={t('staff_roster_empty_hint')}
                        action={
                            canCreate ? (
                                <Link href={route('staff.create')}>
                                    <PrimaryButton type="button">
                                        <NavIcon name="workers" className="text-sm" />
                                        {t('vault_form_add_staff')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-teal-500/15 text-teal-900 dark:bg-teal-400/15 dark:text-teal-200">
                                <NavIcon name="workers" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('staff_roster_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('staff_roster_table_hint', { count: list.length })}
                                </p>
                            </div>
                        </div>
                        <MobileCardList>
                            {list.map((person) => (
                                <MobileCard
                                    key={person.id}
                                    title={person.name}
                                    subtitle={person.phone || person.role || person.trade || ''}
                                    badge={
                                        <PayModelChip
                                            payModel={person.pay_model}
                                            kind={person.kind}
                                            t={t}
                                        />
                                    }
                                    rows={[
                                        { label: t('staff_role'), value: person.role || person.trade || '—' },
                                        { label: t('staff_rates_title'), value: paySummary(person) },
                                    ]}
                                    footer={
                                        <div className="flex flex-wrap items-center gap-3">
                                            <Link
                                                href={route('staff.show', person.id)}
                                                className="text-xs font-semibold text-teal-200"
                                            >
                                                {t('view')}
                                            </Link>
                                            {canChange ? (
                                                <DeskRowActions
                                                    editHref={route('staff.edit', person.id)}
                                                    onDelete={() => removeStaff(person.id)}
                                                    t={t}
                                                />
                                            ) : null}
                                        </div>
                                    }
                                />
                            ))}
                        </MobileCardList>
                        <DataTable minWidth="40rem" caption={t('staff_roster_title')} stickyFirstColumn hideOnMobile>
                            <thead>
                                <tr>
                                    {canChange ? <Th>{t('actions')}</Th> : null}
                                    <Th>{t('name')}</Th>
                                    <Th>{t('phone')}</Th>
                                    <Th>{t('staff_pay_model')}</Th>
                                    <Th>{t('staff_role')}</Th>
                                    <Th align="end">{t('monthly_salary')}</Th>
                                    <Th align="end">{t('staff_rates_title')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((person) => (
                                    <tr key={person.id}>
                                        {canChange ? (
                                            <Td>
                                                <DeskRowActions
                                                    editHref={route('staff.edit', person.id)}
                                                    onDelete={() => removeStaff(person.id)}
                                                    t={t}
                                                />
                                            </Td>
                                        ) : null}
                                        <Td>
                                            <div className="space-y-2">
                                                <Link
                                                    href={route('staff.show', person.id)}
                                                    className="font-semibold text-teal-700 underline-offset-2 hover:underline dark:text-teal-300"
                                                >
                                                    {person.name}
                                                </Link>
                                                {canChange ? (
                                                    <DeskRowActions
                                                        editHref={route('staff.edit', person.id)}
                                                        onDelete={() => removeStaff(person.id)}
                                                        t={t}
                                                    />
                                                ) : null}
                                            </div>
                                        </Td>
                                        <Td muted>
                                            <span dir="ltr">{person.phone || '—'}</span>
                                        </Td>
                                        <Td>
                                            <PayModelChip
                                                payModel={person.pay_model}
                                                kind={person.kind}
                                                t={t}
                                            />
                                        </Td>
                                        <Td muted>{person.role || person.trade || '—'}</Td>
                                        <Td align="end" money>
                                            {(person.pay_model === 'monthly' ||
                                                person.kind === 'salary') &&
                                            person.monthly_salary != null ? (
                                                <span className="inline-flex items-baseline gap-1">
                                                    <MoneyAmount
                                                        value={person.monthly_salary}
                                                        label={person.currency}
                                                        size="sm"
                                                        showLabel={false}
                                                    />
                                                    <span className="text-xs text-slate-500">
                                                        {person.currency}
                                                    </span>
                                                </span>
                                            ) : (person.pay_model === 'daily' ||
                                                  person.kind === 'time') &&
                                              person.day_rate != null ? (
                                                <span className="inline-flex items-baseline gap-1">
                                                    <MoneyAmount
                                                        value={person.day_rate}
                                                        label={person.currency}
                                                        size="sm"
                                                        showLabel={false}
                                                    />
                                                    <span className="text-xs text-slate-500">
                                                        {person.currency}/{t('staff_pay_day_short')}
                                                    </span>
                                                </span>
                                            ) : (
                                                '—'
                                            )}
                                        </Td>
                                        <Td align="end" money>
                                            {person.pay_model === 'unit' || person.kind === 'unit'
                                                ? person.rates_count > 0
                                                    ? t('staff_rates_count', {
                                                          count: person.rates_count,
                                                      })
                                                    : person.unit_rate != null
                                                      ? `${person.unit_rate} ${person.currency}/${person.rate_unit || '—'}`
                                                      : '—'
                                                : '—'}
                                        </Td>
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
