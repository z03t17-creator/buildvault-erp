import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyAmount from '@/Components/MoneyAmount';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function MoneyStat({ label, value, currency, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900 dark:text-white',
        locked: 'text-amber-800 dark:text-amber-200',
        free: 'text-emerald-700 dark:text-emerald-300',
        muted: 'text-slate-400',
    };

    return (
        <div className="bv-card px-4 py-3.5">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div
                dir="ltr"
                className={
                    'mt-1.5 font-sans text-2xl font-semibold tracking-normal tabular-nums sm:text-3xl ' +
                    tones[tone]
                }
            >
                {value == null || value === '' ? (
                    '—'
                ) : (
                    <MoneyAmount
                        value={value}
                        label={currency}
                        size="xl"
                        showLabel={false}
                        accent={tone === 'free'}
                    />
                )}
            </div>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{currency}</p>
        </div>
    );
}

function Meta({ label, children }) {
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

function KindChip({ kind, t }) {
    const key = `labor_kind_${kind || 'unclassified'}`;
    const label = t(key) !== key ? t(key) : kind;
    const tones = {
        staff: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        worker: 'bg-indigo-500/15 text-indigo-900 dark:text-indigo-300',
        unclassified: 'bg-slate-500/15 text-slate-800 dark:text-slate-200',
    };

    return (
        <span
            className={
                'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold ' +
                (tones[kind] || tones.unclassified)
            }
        >
            <NavIcon name="workers" className="text-xs" />
            {label}
        </span>
    );
}

function RoleChip({ role, t }) {
    const key = `worker_role_${role}`;
    const label = t(key) !== key ? t(key) : role?.replace(/_/g, ' ') || '—';
    const tones = {
        engineer: 'bg-sky-500/15 text-sky-900 dark:text-sky-300',
        supervisor: 'bg-teal-500/15 text-teal-900 dark:text-teal-300',
        subcontractor: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        laborer: 'bg-slate-500/15 text-slate-700 dark:text-slate-300',
    };

    return (
        <span
            className={
                'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold capitalize ' +
                (tones[role] || tones.laborer)
            }
        >
            {label}
        </span>
    );
}

function AdvanceStatusChip({ status, t }) {
    const key = `status_${status}`;
    const label = t(key) !== key ? t(key) : status?.replace(/_/g, ' ') || '—';
    const tones = {
        open: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        repaid: 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300',
        cancelled: 'bg-slate-500/15 text-slate-600 dark:text-slate-400',
    };

    return (
        <span
            className={
                'inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold capitalize ' +
                (tones[status] || tones.open)
            }
        >
            {label}
        </span>
    );
}

function formatUnitRate(worker, t) {
    const rate = Number(worker.unit_rate);
    if (!worker.unit_rate || Number.isNaN(rate) || rate <= 0) {
        return '—';
    }
    const unit = worker.rate_unit || '';
    const currency = worker.rate_currency || t('USD');
    return `${rate} ${currency}${unit ? ` / ${unit}` : ''}`;
}

export default function Show({
    worker,
    advances,
    statements,
    settlement,
    laborKinds,
    canClassify,
}) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const canUpdate = useCan('workers.update');
    const canViewAny = useCan('workers.viewAny');
    const isStaff = worker.labor_kind === 'staff';
    const isWorker = worker.labor_kind === 'worker';
    const isUnclassified = !isStaff && !isWorker;

    const classify = useForm({
        labor_kind: isUnclassified ? 'staff' : worker.labor_kind,
        rate_unit: worker.rate_unit || 'm2',
        rate_currency: worker.rate_currency || 'USD',
        unit_rate: worker.unit_rate || '',
        monthly_salary_usd: worker.monthly_salary_usd || '',
        monthly_salary_iqd: worker.monthly_salary_iqd || '',
    });

    const statementForm = useForm({
        period: new Date().toISOString().slice(0, 7),
        label: '',
        earned_usd: '',
        earned_iqd: '',
        paid_usd: '',
        paid_iqd: '',
        retention_held_usd: '',
        retention_held_iqd: '',
        advances_usd: '',
        advances_iqd: '',
        penalties_usd: '',
        penalties_iqd: '',
    });

    const pageHint = isStaff
        ? t('staff_profile_hint')
        : isWorker
          ? t('worker_profile_hint')
          : t('person_profile_hint');

    const backHref = isStaff
        ? route('workers.index', { labor_kind: 'staff' })
        : isWorker
          ? route('workers.index', { labor_kind: 'worker' })
          : route('workers.index');
    const backLabel = isStaff
        ? t('staff_directory')
        : isWorker
          ? t('labor_kind_worker')
          : t('people');

    const advanceList = advances || [];
    const statementList = statements || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={worker.name}
                    subtitle={pageHint}
                    icon={<NavIcon name="workers" className="text-lg" />}
                    actions={
                        <>
                            {canViewAny && (
                                <Link href={backHref}>
                                    <SecondaryButton type="button">
                                        <NavIcon name="workers" className="text-sm" />
                                        {backLabel}
                                    </SecondaryButton>
                                </Link>
                            )}
                            {canUpdate && (
                                <Link href={route('workers.edit', worker.id)}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-amber-600 hover:!bg-amber-500 dark:!bg-amber-400 dark:!text-amber-950 dark:hover:!bg-amber-300"
                                    >
                                        <NavIcon name="edit" className="text-sm" />
                                        {t('edit')}
                                    </PrimaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={worker.name} />
            <PageShell narrow className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="flex flex-wrap items-start gap-3">
                        <span className="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-500 text-amber-950 dark:bg-amber-400">
                            <NavIcon name="workers" className="text-lg" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <KindChip kind={worker.labor_kind || 'unclassified'} t={t} />
                                <RoleChip role={worker.role} t={t} />
                            </div>
                            <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                {isStaff
                                    ? t('staff_profile_card_hint')
                                    : isWorker
                                      ? t('worker_profile_card_hint')
                                      : t('person_profile_card_hint')}
                            </p>
                        </div>
                    </div>

                    <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                        <Meta label={t('project')}>
                            {worker.project?.name || t('unassigned')}
                        </Meta>
                        <Meta label={t('phone')}>{worker.phone || '—'}</Meta>
                        {isStaff && (
                            <>
                                <Meta label={t('unit_rate')}>
                                    <span className="font-sans tabular-nums">
                                        {formatUnitRate(worker, t)}
                                    </span>
                                </Meta>
                                <Meta label={t('rate_unit')}>
                                    {worker.rate_unit || '—'}
                                </Meta>
                            </>
                        )}
                        {isWorker && (
                            <>
                                <Meta label={t('monthly_salary_usd')}>
                                    <span dir="ltr" className="inline-block font-sans tabular-nums">
                                        <MoneyAmount
                                            value={worker.monthly_salary_usd}
                                            label={usd}
                                            size="md"
                                            showLabel={false}
                                        />{' '}
                                        {usd}
                                    </span>
                                </Meta>
                                <Meta label={t('monthly_salary_iqd')}>
                                    <span dir="ltr" className="inline-block font-sans tabular-nums">
                                        <MoneyAmount
                                            value={worker.monthly_salary_iqd}
                                            label={iqd}
                                            size="md"
                                            showLabel={false}
                                        />{' '}
                                        {iqd}
                                    </span>
                                </Meta>
                            </>
                        )}
                    </dl>
                </section>

                {canClassify && (
                    <section className="bv-card p-4 sm:p-5">
                        <div className="mb-3 flex items-start gap-3">
                            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                                <NavIcon name="edit" className="text-base" />
                            </span>
                            <div>
                                <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                    {t('classify_person')}
                                </h2>
                                <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                    {t('staff_classify_hint')}
                                </p>
                            </div>
                        </div>
                        <form
                            noValidate
                            onSubmit={(e) => {
                                e.preventDefault();
                                classify.post(route('workers.classify', worker.id));
                            }}
                        >
                            <FormSection cols={2}>
                                <FormField>
                                    <InputLabel value={t('labor_kind')} />
                                    <select
                                        className={fieldClass}
                                        value={classify.data.labor_kind}
                                        onChange={(e) =>
                                            classify.setData('labor_kind', e.target.value)
                                        }
                                    >
                                        {(laborKinds || ['staff', 'worker']).map((k) => (
                                            <option key={k} value={k}>
                                                {t(`labor_kind_${k}`)}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                                {classify.data.labor_kind === 'staff' ? (
                                    <>
                                        <FormField>
                                            <InputLabel value={t('rate_unit')} />
                                            <TextInput
                                                className={fieldClass}
                                                value={classify.data.rate_unit}
                                                onChange={(e) =>
                                                    classify.setData('rate_unit', e.target.value)
                                                }
                                                placeholder={t('staff_rate_unit_placeholder')}
                                            />
                                        </FormField>
                                        <FormField>
                                            <InputLabel value={t('currency')} />
                                            <select
                                                className={fieldClass}
                                                value={classify.data.rate_currency}
                                                onChange={(e) =>
                                                    classify.setData(
                                                        'rate_currency',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="USD">{usd}</option>
                                                <option value="IQD">{iqd}</option>
                                            </select>
                                        </FormField>
                                        <FormField>
                                            <InputLabel value={t('unit_rate')} />
                                            <MoneyInput
                                                className={fieldClass}
                                                value={classify.data.unit_rate}
                                                onValueChange={(raw) =>
                                                    classify.setData('unit_rate', raw)
                                                }
                                            />
                                        </FormField>
                                    </>
                                ) : (
                                    <>
                                        <FormField>
                                            <InputLabel value={t('monthly_salary_usd')} />
                                            <MoneyInput
                                                className={fieldClass}
                                                value={classify.data.monthly_salary_usd}
                                                onValueChange={(raw) =>
                                                    classify.setData('monthly_salary_usd', raw)
                                                }
                                            />
                                        </FormField>
                                        <FormField>
                                            <InputLabel value={t('monthly_salary_iqd')} />
                                            <MoneyInput
                                                className={fieldClass}
                                                value={classify.data.monthly_salary_iqd}
                                                onValueChange={(raw) =>
                                                    classify.setData('monthly_salary_iqd', raw)
                                                }
                                            />
                                        </FormField>
                                    </>
                                )}
                            </FormSection>
                            <FormActions className="mt-4">
                                <PrimaryButton
                                    disabled={classify.processing}
                                    className="!bg-amber-600 hover:!bg-amber-500"
                                >
                                    {t('classify_person')}
                                </PrimaryButton>
                            </FormActions>
                        </form>
                    </section>
                )}

                {settlement && (
                    <section>
                        <div className="mb-3 flex items-start gap-3">
                            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                                <NavIcon name="settlements" className="text-base" />
                            </span>
                            <div>
                                <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                    {t('settlement_preview')}
                                </h2>
                                <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                    {settlement.note || t('settlement_preview_hint')}
                                </p>
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <MoneyStat
                                label={t('staff_settlement_gross_usd')}
                                value={settlement.gross_usd}
                                currency={usd}
                            />
                            <MoneyStat
                                label={t('staff_settlement_gross_iqd')}
                                value={settlement.gross_iqd}
                                currency={iqd}
                            />
                            <MoneyStat
                                label={t('staff_settlement_retention', {
                                    pct: settlement.hold_pct || 0,
                                })}
                                value={settlement.retention_usd}
                                currency={usd}
                                tone="locked"
                            />
                            <MoneyStat
                                label={t('staff_settlement_retention_iqd', {
                                    pct: settlement.hold_pct || 0,
                                })}
                                value={settlement.retention_iqd}
                                currency={iqd}
                                tone="locked"
                            />
                            <MoneyStat
                                label={`${t('multi_advances')} ${usd}`}
                                value={settlement.advances_usd}
                                currency={usd}
                            />
                            <MoneyStat
                                label={`${t('multi_advances')} ${iqd}`}
                                value={settlement.advances_iqd}
                                currency={iqd}
                            />
                            <MoneyStat
                                label={t('net_payable') + ` ${usd}`}
                                value={settlement.net_usd}
                                currency={usd}
                                tone="free"
                            />
                            <MoneyStat
                                label={t('net_payable') + ` ${iqd}`}
                                value={settlement.net_iqd}
                                currency={iqd}
                                tone="free"
                            />
                        </div>
                    </section>
                )}

                <DataPanel padded={false}>
                    <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                        <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                            <NavIcon name="advances" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('staff_advances_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('staff_advances_hint')}
                            </p>
                        </div>
                    </div>
                    {advanceList.length === 0 ? (
                        <div className="p-4">
                            <EmptyState
                                icon="advances"
                                title={t('staff_advances_empty_title')}
                                description={t('staff_advances_empty_hint')}
                            />
                        </div>
                    ) : (
                        <DataTable minWidth="40rem" caption={t('multi_advances')}>
                            <thead>
                                <tr>
                                    <Th>{t('date')}</Th>
                                    <Th>{t('description')}</Th>
                                    <Th align="end" className="text-amber-800 dark:text-amber-300">
                                        {usd}
                                    </Th>
                                    <Th align="end" className="text-amber-800 dark:text-amber-300">
                                        {iqd}
                                    </Th>
                                    <Th>{t('status')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {advanceList.map((a) => (
                                    <tr key={a.id}>
                                        <Td className="font-sans tabular-nums">
                                            {a.advanced_on
                                                ? String(a.advanced_on).slice(0, 10)
                                                : '—'}
                                        </Td>
                                        <Td muted>{a.reason || '—'}</Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={a.amount_usd}
                                                label={usd}
                                                size="sm"
                                                showLabel={false}
                                            />
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={a.amount_iqd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                            />
                                        </Td>
                                        <Td>
                                            <AdvanceStatusChip status={a.status} t={t} />
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </DataPanel>

                {isStaff && (
                    <section className="bv-card p-4 sm:p-5">
                        <div className="mb-3 flex items-start gap-3">
                            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                                <NavIcon name="docs" className="text-base" />
                            </span>
                            <div>
                                <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                    {t('staff_statements')}
                                </h2>
                                <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                    {t('staff_statements_hint')}
                                </p>
                            </div>
                        </div>

                        <form
                            noValidate
                            onSubmit={(e) => {
                                e.preventDefault();
                                statementForm.post(
                                    route('workers.statements.store', worker.id),
                                    {
                                        onSuccess: () =>
                                            statementForm.reset(
                                                'label',
                                                'earned_usd',
                                                'earned_iqd',
                                                'paid_usd',
                                                'paid_iqd',
                                            ),
                                    },
                                );
                            }}
                        >
                            <FormSection cols={2}>
                                <FormField>
                                    <InputLabel value={t('staff_statement_period')} />
                                    <TextInput
                                        className={fieldClass}
                                        value={statementForm.data.period}
                                        onChange={(e) =>
                                            statementForm.setData('period', e.target.value)
                                        }
                                        placeholder="YYYY-MM"
                                        inputMode="numeric"
                                    />
                                    <InputError
                                        message={statementForm.errors.period}
                                        className="mt-1"
                                    />
                                </FormField>
                                <FormField>
                                    <InputLabel value={t('villa_no')} />
                                    <TextInput
                                        className={fieldClass}
                                        value={statementForm.data.label}
                                        onChange={(e) =>
                                            statementForm.setData('label', e.target.value)
                                        }
                                        placeholder={t('staff_statement_label_placeholder')}
                                    />
                                </FormField>
                                <FormField>
                                    <InputLabel value={t('staff_statement_earned_usd')} />
                                    <MoneyInput
                                        className={fieldClass}
                                        value={statementForm.data.earned_usd}
                                        onValueChange={(raw) =>
                                            statementForm.setData('earned_usd', raw)
                                        }
                                    />
                                </FormField>
                                <FormField>
                                    <InputLabel value={t('staff_statement_earned_iqd')} />
                                    <MoneyInput
                                        className={fieldClass}
                                        value={statementForm.data.earned_iqd}
                                        onValueChange={(raw) =>
                                            statementForm.setData('earned_iqd', raw)
                                        }
                                    />
                                </FormField>
                            </FormSection>
                            <FormActions className="mt-4">
                                <PrimaryButton
                                    disabled={statementForm.processing}
                                    className="!bg-amber-600 hover:!bg-amber-500"
                                >
                                    {t('staff_statement_save')}
                                </PrimaryButton>
                            </FormActions>
                        </form>

                        {statementList.length > 0 ? (
                            <div className="mt-5 border-t border-slate-100 pt-4 dark:border-slate-800">
                                <DataTable minWidth="40rem" caption={t('staff_statements')}>
                                    <thead>
                                        <tr>
                                            <Th>{t('villa_no')}</Th>
                                            <Th align="end">{t('staff_statement_earned_usd')}</Th>
                                            <Th align="end">{t('staff_statement_earned_iqd')}</Th>
                                            <Th align="end">
                                                {t('staff_statement_remaining_usd')}
                                            </Th>
                                            <Th align="end">
                                                {t('staff_statement_remaining_iqd')}
                                            </Th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {statementList.map((s) => (
                                            <tr key={s.id}>
                                                <Td>{s.label || s.period || '—'}</Td>
                                                <Td align="end" money>
                                                    <MoneyAmount
                                                        value={s.earned_usd}
                                                        label={usd}
                                                        size="sm"
                                                        showLabel={false}
                                                    />
                                                </Td>
                                                <Td align="end" money>
                                                    <MoneyAmount
                                                        value={s.earned_iqd}
                                                        label={iqd}
                                                        size="sm"
                                                        showLabel={false}
                                                    />
                                                </Td>
                                                <Td align="end" money>
                                                    <MoneyAmount
                                                        value={s.remaining_usd}
                                                        label={usd}
                                                        size="sm"
                                                        showLabel={false}
                                                    />
                                                </Td>
                                                <Td align="end" money>
                                                    <MoneyAmount
                                                        value={s.remaining_iqd}
                                                        label={iqd}
                                                        size="sm"
                                                        showLabel={false}
                                                    />
                                                </Td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </DataTable>
                            </div>
                        ) : (
                            <p className="mt-4 text-sm text-slate-500 dark:text-slate-400">
                                {t('staff_statements_empty')}
                            </p>
                        )}
                    </section>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
