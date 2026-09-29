import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputLabel from '@/Components/InputLabel';
import MoneyAmount from '@/Components/MoneyAmount';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{children}</dd>
        </div>
    );
}

export default function Show({ worker, advances, statements, settlement, laborKinds, canClassify }) {
    const t = useTranslations();
    const canUpdate = useCan('workers.update');
    const canViewAny = useCan('workers.viewAny');
    const classify = useForm({
        labor_kind: worker.labor_kind === 'unclassified' ? 'staff' : worker.labor_kind,
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

    const nameLabel =
        worker.labor_kind === 'staff'
            ? t('staff_name')
            : worker.labor_kind === 'worker'
              ? t('worker_name')
              : t('name');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={worker.name}
                    subtitle={nameLabel}
                    actions={
                        <>
                            {canViewAny && (
                                <Link href={route('workers.index')}>
                                    <SecondaryButton>{t('back')}</SecondaryButton>
                                </Link>
                            )}
                            {canUpdate && (
                                <Link href={route('workers.edit', worker.id)}>
                                    <SecondaryButton>{t('edit')}</SecondaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={worker.name} />
            <PageShell narrow>
                <DataPanel>
                    <div className="flex flex-wrap items-center gap-3">
                        <StatusBadge status={worker.labor_kind || 'unclassified'} />
                        <StatusBadge status={worker.role} />
                    </div>
                    <dl className="mt-5 grid gap-6 sm:grid-cols-2">
                        <Field label={t('project')}>{worker.project?.name || t('unassigned')}</Field>
                        <Field label={t('phone')}>{worker.phone || '—'}</Field>
                        {worker.labor_kind === 'worker' && (
                            <>
                                <Field label={t('monthly_salary_usd')}>
                                    <span className="font-mono tabular-nums">
                                        <MoneyAmount value={worker.monthly_salary_usd} label="USD" size="md" showLabel={false} />
                                    </span>
                                </Field>
                                <Field label={t('monthly_salary_iqd')}>
                                    <span className="font-mono tabular-nums">
                                        <MoneyAmount value={worker.monthly_salary_iqd} label="IQD" size="md" showLabel={false} />
                                    </span>
                                </Field>
                            </>
                        )}
                        {worker.labor_kind === 'staff' && (
                            <>
                                <Field label={t('rate_unit')}>{worker.rate_unit || '—'}</Field>
                                <Field label={t('unit_rate')}>
                                    <span className="font-mono tabular-nums">
                                        {worker.unit_rate} {worker.rate_currency}
                                    </span>
                                </Field>
                            </>
                        )}
                    </dl>
                </DataPanel>

                {canClassify && (
                    <DataPanel title={t('classify_person')}>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                classify.post(route('workers.classify', worker.id));
                            }}
                            className="space-y-4"
                        >
                            <FormSection>
                                <FormField>
                                    <InputLabel value={t('labor_kind')} />
                                    <select
                                        className={selectClass}
                                        value={classify.data.labor_kind}
                                        onChange={(e) => classify.setData('labor_kind', e.target.value)}
                                    >
                                        {(laborKinds || ['staff', 'worker']).map((k) => (
                                            <option key={k} value={k}>
                                                {t(`labor_kind_${k}`)}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                                {classify.data.labor_kind === 'staff' && (
                                    <>
                                        <FormField>
                                            <InputLabel value={t('rate_unit')} />
                                            <TextInput
                                                className="mt-1 block w-full"
                                                value={classify.data.rate_unit}
                                                onChange={(e) => classify.setData('rate_unit', e.target.value)}
                                            />
                                        </FormField>
                                        <FormField>
                                            <InputLabel value={t('currency')} />
                                            <select
                                                className={selectClass}
                                                value={classify.data.rate_currency}
                                                onChange={(e) => classify.setData('rate_currency', e.target.value)}
                                            >
                                                <option value="USD">USD</option>
                                                <option value="IQD">IQD</option>
                                            </select>
                                        </FormField>
                                        <FormField>
                                            <InputLabel value={t('unit_rate')} />
                                            <MoneyInput
                                                className="mt-1 block w-full"
                                                value={classify.data.unit_rate}
                                                onValueChange={(raw) => classify.setData('unit_rate', raw)}
                                            />
                                        </FormField>
                                    </>
                                )}
                                {classify.data.labor_kind === 'worker' && (
                                    <>
                                        <FormField>
                                            <InputLabel value={t('monthly_salary_usd')} />
                                            <MoneyInput
                                                className="mt-1 block w-full"
                                                value={classify.data.monthly_salary_usd}
                                                onValueChange={(raw) => classify.setData('monthly_salary_usd', raw)}
                                            />
                                        </FormField>
                                        <FormField>
                                            <InputLabel value={t('monthly_salary_iqd')} />
                                            <MoneyInput
                                                className="mt-1 block w-full"
                                                value={classify.data.monthly_salary_iqd}
                                                onValueChange={(raw) => classify.setData('monthly_salary_iqd', raw)}
                                            />
                                        </FormField>
                                    </>
                                )}
                            </FormSection>
                            <FormActions>
                                <PrimaryButton disabled={classify.processing}>{t('classify_person')}</PrimaryButton>
                            </FormActions>
                        </form>
                    </DataPanel>
                )}

                {settlement && (
                    <DataPanel
                        title={t('settlement_preview')}
                        subtitle={settlement.note || t('settlement_preview_hint')}
                    >
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Field label="Gross USD">
                                <MoneyAmount value={settlement.gross_usd} label="USD" size="sm" />
                            </Field>
                            <Field label="Gross IQD">
                                <MoneyAmount value={settlement.gross_iqd} label="IQD" size="sm" />
                            </Field>
                            <Field label={`Retention ${settlement.hold_pct || 0}%`}>
                                <span className="font-mono tabular-nums">
                                    USD {Number(settlement.retention_usd || 0).toLocaleString()} / IQD{' '}
                                    {Number(settlement.retention_iqd || 0).toLocaleString()}
                                </span>
                            </Field>
                            <Field label={t('multi_advances')}>
                                <span className="font-mono tabular-nums">
                                    USD {Number(settlement.advances_usd || 0).toLocaleString()} / IQD{' '}
                                    {Number(settlement.advances_iqd || 0).toLocaleString()}
                                </span>
                            </Field>
                            <Field label={t('penalties')}>
                                <span className="font-mono tabular-nums">
                                    USD {Number(settlement.penalties_usd || 0).toLocaleString()} / IQD{' '}
                                    {Number(settlement.penalties_iqd || 0).toLocaleString()}
                                </span>
                            </Field>
                            <Field label={t('net_payable')}>
                                <span className="font-mono tabular-nums text-emerald-700 dark:text-emerald-300">
                                    USD {Number(settlement.net_usd || 0).toLocaleString()} / IQD{' '}
                                    {Number(settlement.net_iqd || 0).toLocaleString()}
                                </span>
                            </Field>
                        </div>
                    </DataPanel>
                )}

                <DataPanel title={t('multi_advances')} padded={false}>
                    {(advances || []).length === 0 ? (
                        <p className="p-4 text-sm text-slate-500">No advances yet.</p>
                    ) : (
                        <DataTable minWidth="36rem">
                            <thead>
                                <tr>
                                    <Th>{t('date')}</Th>
                                    <Th className="text-left">{t('description')}</Th>
                                    <Th align="end">USD</Th>
                                    <Th align="end">IQD</Th>
                                    <Th align="center">{t('status')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {(advances || []).map((a) => (
                                    <tr key={a.id}>
                                        <Td className="font-mono tabular-nums">{a.advanced_on}</Td>
                                        <Td className="text-left">{a.reason}</Td>
                                        <Td align="end" className="font-mono tabular-nums">
                                            <MoneyAmount value={a.amount_usd} label="USD" size="sm" showLabel={false} />
                                        </Td>
                                        <Td align="end" className="font-mono tabular-nums">
                                            <MoneyAmount value={a.amount_iqd} label="IQD" size="sm" showLabel={false} />
                                        </Td>
                                        <Td align="center">
                                            <StatusBadge status={a.status} />
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </DataPanel>

                {worker.labor_kind === 'staff' && (
                    <DataPanel title={t('staff_statements')}>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                statementForm.post(route('workers.statements.store', worker.id), {
                                    onSuccess: () => statementForm.reset('label', 'earned_usd', 'earned_iqd', 'paid_usd', 'paid_iqd'),
                                });
                            }}
                            className="mb-6 space-y-3"
                        >
                            <div className="grid gap-3 sm:grid-cols-2">
                                <TextInput
                                    placeholder="Period (YYYY-MM)"
                                    value={statementForm.data.period}
                                    onChange={(e) => statementForm.setData('period', e.target.value)}
                                />
                                <TextInput
                                    placeholder="Label / Villa No"
                                    value={statementForm.data.label}
                                    onChange={(e) => statementForm.setData('label', e.target.value)}
                                />
                                <MoneyInput
                                    placeholder="Earned USD"
                                    value={statementForm.data.earned_usd}
                                    onValueChange={(raw) => statementForm.setData('earned_usd', raw)}
                                />
                                <MoneyInput
                                    placeholder="Earned IQD"
                                    value={statementForm.data.earned_iqd}
                                    onValueChange={(raw) => statementForm.setData('earned_iqd', raw)}
                                />
                            </div>
                            <PrimaryButton disabled={statementForm.processing}>Save statement</PrimaryButton>
                        </form>

                        {(statements || []).length > 0 && (
                            <DataTable minWidth="40rem">
                                <thead>
                                    <tr>
                                        <Th>{t('villa_no')}</Th>
                                        <Th align="end">Earned USD</Th>
                                        <Th align="end">Earned IQD</Th>
                                        <Th align="end">Remaining USD</Th>
                                        <Th align="end">Remaining IQD</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(statements || []).map((s) => (
                                        <tr key={s.id}>
                                            <Td className="text-left">{s.label || s.period || '—'}</Td>
                                            <Td align="end" className="font-mono tabular-nums">{s.earned_usd}</Td>
                                            <Td align="end" className="font-mono tabular-nums">{s.earned_iqd}</Td>
                                            <Td align="end" className="font-mono tabular-nums">{s.remaining_usd}</Td>
                                            <Td align="end" className="font-mono tabular-nums">{s.remaining_iqd}</Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </DataTable>
                        )}
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
