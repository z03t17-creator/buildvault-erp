import DataPanel from '@/Components/DataPanel';
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
import StatusBadge from '@/Components/StatusBadge';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';

function Meta({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{value ?? '—'}</dd>
        </div>
    );
}

function FinancialRow({ label, value, iqd, hint, emphasize = false, muted = false }) {
    return (
        <div className={`flex items-start justify-between gap-4 border-b border-slate-100 py-3 last:border-0 dark:border-slate-800 ${emphasize ? 'pt-4' : ''}`}>
            <div>
                <p className={`text-sm ${emphasize ? 'font-semibold text-slate-900 dark:text-white' : 'text-slate-600 dark:text-slate-300'}`}>
                    {label}
                </p>
                {hint ? <p className="mt-0.5 text-xs text-slate-400">{hint}</p> : null}
            </div>
            <MoneyAmount
                value={value}
                label={iqd}
                size={emphasize ? 'lg' : 'md'}
                className={`shrink-0 ${muted ? 'text-slate-400' : ''}`}
            />
        </div>
    );
}

export default function Show({ project, financialSummary, recentMaterials, canViewFinancials, canRecordReceipt }) {
    const t = useTranslations();
    const canUpdate = useCan('projects.update');
    const canCreateTower = useCan('towers.create');
    const canDocs = useCan('documents.viewAny');
    const iqd = t('IQD');
    const towers = project.towers || [];
    const workers = project.workers || [];
    const receipts = project.receipts || [];
    const materials = recentMaterials || [];
    const summary = financialSummary;

    const receiptForm = useForm({
        amount_iqd: '',
        received_on: new Date().toISOString().slice(0, 10),
        source: '',
        reference: '',
        notes: '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={project.name}
                    subtitle={project.client || project.location || t('project')}
                    actions={
                        <>
                            <Link href={route('projects.index')}>
                                <SecondaryButton>{t('back')}</SecondaryButton>
                            </Link>
                            {canDocs && (
                                <Link href={route('documents.index', { project_id: project.id })}>
                                    <SecondaryButton>Documents</SecondaryButton>
                                </Link>
                            )}
                            {canUpdate && (
                                <Link href={route('projects.edit', project.id)}>
                                    <SecondaryButton>{t('edit')}</SecondaryButton>
                                </Link>
                            )}
                            {canCreateTower && (
                                <Link href={route('projects.towers.create', project.id)}>
                                    <PrimaryButton type="button">{t('create_tower')}</PrimaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={project.name} />

            <PageShell>
                <DataPanel>
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <StatusBadge status={project.status} />
                    </div>
                    <p className="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                        {project.description || '—'}
                    </p>
                    <dl className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <Meta label={t('client')} value={project.client} />
                        <Meta label={t('location')} value={project.location} />
                        <Meta label={t('contract_number')} value={project.contract_number} />
                        <Meta label={t('start_date')} value={project.start_date ? String(project.start_date).slice(0, 10) : null} />
                        <Meta label={t('end_date')} value={project.end_date ? String(project.end_date).slice(0, 10) : null} />
                        <Meta label={`${t('budget_iqd')} (${iqd})`} value={<MoneyAmount value={project.budget_iqd} label={iqd} size="md" showLabel={false} />} />
                        <Meta label={t('towers_count')} value={towers.length} />
                        <Meta label={t('workers_count')} value={workers.length} />
                    </dl>
                </DataPanel>

                {canViewFinancials && summary && (
                    <DataPanel title={t('financial_summary')} subtitle={t('financial_summary_hint')}>
                        <FinancialRow label={t('contract_value')} value={summary.contract_value_iqd} iqd={iqd} />
                        <FinancialRow label={t('money_received')} value={summary.money_received_iqd} iqd={iqd} />
                        <FinancialRow
                            label={t('project_expenses')}
                            value={summary.project_expenses_iqd}
                            iqd={iqd}
                        />
                        <FinancialRow
                            label={t('material_cost')}
                            value={summary.material_cost_iqd}
                            iqd={iqd}
                            hint={t('material_cost_hint')}
                        />
                        <FinancialRow label={t('payroll_cost')} value={summary.payroll_cost_iqd} iqd={iqd} />
                        <FinancialRow label={t('other_expenses')} value={summary.other_expenses_iqd} iqd={iqd} />
                        <FinancialRow
                            label={t('remaining_vs_contract')}
                            value={summary.remaining_vs_contract_iqd}
                            iqd={iqd}
                            hint={t('remaining_vs_contract_hint')}
                            emphasize
                        />
                        <FinancialRow
                            label={t('net_position')}
                            value={summary.net_position_iqd}
                            iqd={iqd}
                            hint={t('net_position_hint')}
                            emphasize
                        />
                    </DataPanel>
                )}

                {canViewFinancials && (
                    <DataPanel title={t('recent_materials_used')} subtitle={t('recent_materials_used_hint')}>
                        {materials.length === 0 ? (
                            <EmptyState title={t('no_materials_used')} description={t('no_materials_used_hint')} />
                        ) : (
                            <ul className="divide-y divide-slate-200 border border-slate-200/80 dark:divide-slate-800 dark:border-slate-700">
                                {materials.map((m) => (
                                    <li
                                        key={m.id}
                                        className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <p className="text-sm font-medium text-slate-900 dark:text-white">
                                                {m.item_name || '—'}
                                                {m.sku ? (
                                                    <span className="ms-2 text-xs font-normal text-slate-400">
                                                        {m.sku}
                                                    </span>
                                                ) : null}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {m.moved_on || '—'}
                                                {' · '}
                                                {m.quantity} {m.unit || ''}
                                                {m.tower ? ` · ${m.tower}` : ''}
                                                {m.floor ? ` · ${m.floor}` : ''}
                                                {m.purpose ? ` · ${m.purpose}` : ''}
                                            </p>
                                            <p className="mt-0.5 text-xs text-slate-400">
                                                {t('previous_qty')}: {m.previous_qty}
                                                {' → '}
                                                {t('remaining_qty')}: {m.new_qty}
                                            </p>
                                        </div>
                                        <MoneyAmount value={m.line_value_iqd} label={iqd} size="md" />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </DataPanel>
                )}

                {canViewFinancials && (
                    <DataPanel title={t('money_received')} subtitle={t('money_received_hint')}>
                        {canRecordReceipt && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    receiptForm.post(route('projects.receipts.store', project.id), {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            receiptForm.reset(
                                                'amount_iqd',
                                                'source',
                                                'reference',
                                                'notes',
                                            ),
                                    });
                                }}
                                className="mb-6 space-y-5 border border-emerald-200/60 bg-emerald-50/40 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20"
                            >
                                <FormSection>
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <FormField>
                                            <InputLabel htmlFor="amount_iqd" value={`${t('amount_iqd')} (${iqd})`} />
                                            <MoneyInput
                                                id="amount_iqd"
                                                className="mt-1 block w-full"
                                                value={receiptForm.data.amount_iqd}
                                                onValueChange={(raw) => receiptForm.setData('amount_iqd', raw)}
                                                required
                                            />
                                            <InputError message={receiptForm.errors.amount_iqd} className="mt-1" />
                                        </FormField>
                                        <FormField>
                                            <InputLabel htmlFor="received_on" value={t('received_on')} />
                                            <TextInput
                                                id="received_on"
                                                type="date"
                                                className="mt-1 block w-full"
                                                value={receiptForm.data.received_on}
                                                onChange={(e) => receiptForm.setData('received_on', e.target.value)}
                                                required
                                            />
                                            <InputError message={receiptForm.errors.received_on} className="mt-1" />
                                        </FormField>
                                        <FormField>
                                            <InputLabel htmlFor="source" value={t('receipt_source')} />
                                            <TextInput
                                                id="source"
                                                className="mt-1 block w-full"
                                                value={receiptForm.data.source}
                                                onChange={(e) => receiptForm.setData('source', e.target.value)}
                                            />
                                        </FormField>
                                        <FormField>
                                            <InputLabel htmlFor="reference" value={t('receipt_reference')} />
                                            <TextInput
                                                id="reference"
                                                className="mt-1 block w-full"
                                                value={receiptForm.data.reference}
                                                onChange={(e) => receiptForm.setData('reference', e.target.value)}
                                            />
                                        </FormField>
                                        <FormField className="sm:col-span-2">
                                            <InputLabel htmlFor="notes" value={t('notes')} />
                                            <textarea
                                                id="notes"
                                                rows={2}
                                                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                                                value={receiptForm.data.notes}
                                                onChange={(e) => receiptForm.setData('notes', e.target.value)}
                                            />
                                        </FormField>
                                    </div>
                                </FormSection>
                                <FormActions className="border-emerald-200/60 dark:border-emerald-900/40">
                                    <PrimaryButton disabled={receiptForm.processing}>
                                        {t('record_money_received')}
                                    </PrimaryButton>
                                </FormActions>
                            </form>
                        )}

                        {receipts.length === 0 ? (
                            <EmptyState title={t('no_receipts')} description={t('no_receipts_hint')} />
                        ) : (
                            <ul className="divide-y divide-slate-200 border border-slate-200/80 dark:divide-slate-800 dark:border-slate-700">
                                {receipts.map((r) => (
                                    <li key={r.id} className="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <MoneyAmount value={r.amount_iqd} label={iqd} size="md" />
                                            <p className="text-xs text-slate-500">
                                                {r.received_on ? String(r.received_on).slice(0, 10) : '—'}
                                                {r.source ? ` · ${r.source}` : ''}
                                                {r.reference ? ` · ${r.reference}` : ''}
                                            </p>
                                        </div>
                                        <p className="text-xs text-slate-400">
                                            {r.entered_by?.name || r.entered_by_id || '—'}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </DataPanel>
                )}

                <DataPanel
                    title={t('towers')}
                    actions={
                        <Link
                            href={route('projects.towers.index', project.id)}
                            className="text-sm font-medium text-emerald-700 underline dark:text-emerald-400"
                        >
                            {t('towers')}
                        </Link>
                    }
                    padded={towers.length === 0}
                >
                    {towers.length === 0 ? (
                        <EmptyState title={t('no_towers')} />
                    ) : (
                        <ul className="divide-y divide-slate-200 dark:divide-slate-800">
                            {towers.map((tower) => (
                                <li key={tower.id}>
                                    <Link
                                        href={route('towers.show', tower.id)}
                                        className="flex items-center justify-between px-5 py-3 transition hover:bg-emerald-50/60 dark:hover:bg-emerald-950/20"
                                    >
                                        <span className="font-medium text-slate-900 dark:text-white">
                                            {tower.name}
                                        </span>
                                        <span className="text-sm text-slate-500">
                                            {(tower.floors || []).length} {t('floors_count').toLowerCase()}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
