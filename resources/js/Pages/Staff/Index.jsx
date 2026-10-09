import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link } from '@inertiajs/react';

function KindChip({ payModel, kind, t }) {
    const model =
        payModel ||
        (kind === 'salary' ? 'monthly' : kind === 'unit' ? 'unit' : 'daily');
    const label = t(`staff_pay_${model}`);
    const tone =
        model === 'monthly'
            ? 'bg-teal-500/15 text-teal-900 dark:text-teal-200'
            : model === 'unit'
              ? 'bg-sky-500/15 text-sky-900 dark:text-sky-200'
              : 'bg-amber-500/15 text-amber-950 dark:text-amber-200';

    return (
        <span className={`inline-flex rounded-lg px-2 py-1 text-xs font-semibold ${tone}`}>
            {label}
        </span>
    );
}

export default function Index({ staff = [], canCreate = false }) {
    const t = useTranslations();
    const list = Array.isArray(staff) ? staff : [];

    return (
        <AuthenticatedLayout
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
                        <DataTable minWidth="40rem" caption={t('staff_roster_title')} stickyFirstColumn>
                            <thead>
                                <tr>
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
                                        <Td>
                                            <Link
                                                href={route('staff.show', person.id)}
                                                className="font-semibold text-teal-700 underline-offset-2 hover:underline dark:text-teal-300"
                                            >
                                                {person.name}
                                            </Link>
                                        </Td>
                                        <Td muted>
                                            <span dir="ltr">{person.phone || '—'}</span>
                                        </Td>
                                        <Td>
                                            <KindChip
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
