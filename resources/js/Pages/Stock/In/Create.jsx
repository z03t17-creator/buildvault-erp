import DataPanel from '@/Components/DataPanel';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

const moneyFieldClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 font-sans text-lg font-semibold tabular-nums shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function Create({ items, suppliers, projects, defaults }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const { data, setData, post, processing, errors } = useForm({
        stock_item_id: '',
        quantity: '',
        moved_on: defaults?.moved_on || new Date().toISOString().slice(0, 10),
        supplier_id: '',
        purchase_price_iqd: '',
        project_id: '',
        invoice_ref: '',
        notes: '',
    });

    const selected = (items || []).find((i) => String(i.id) === String(data.stock_item_id));

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('stock_in')}
                    subtitle={t('stock_in_form_hint')}
                    icon={<NavIcon name="stockIn" className="text-lg" />}
                    actions={
                        <Link href={route('stock.movements.index', { type: 'in' })}>
                            <SecondaryButton type="button">{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('stock_in')} />
            <PageShell narrow className="!space-y-6">
                <p className="rounded-xl border border-emerald-200/70 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-950 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
                    {t('stock_in_no_expense_hint')}
                </p>
                <DataPanel>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(route('stock.in.store'));
                        }}
                        className="space-y-5"
                    >
                        <FormSection cols={2}>
                            <FormField className="sm:col-span-2">
                                <InputLabel value={t('product')} />
                                <select
                                    className={fieldClass}
                                    value={data.stock_item_id}
                                    onChange={(e) => {
                                        const id = e.target.value;
                                        const item = (items || []).find((i) => String(i.id) === String(id));
                                        setData('stock_item_id', id);
                                        if (item) {
                                            setData('purchase_price_iqd', item.purchase_price_iqd ?? '');
                                            setData('supplier_id', item.supplier_id ?? '');
                                        }
                                    }}
                                    required
                                >
                                    <option value="">—</option>
                                    {(items || []).map((i) => (
                                        <option key={i.id} value={i.id}>
                                            {i.name} ({i.quantity} {i.unit})
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.stock_item_id} className="mt-1" />
                                {selected && (
                                    <p className="mt-1 text-xs text-slate-500">
                                        {t('on_hand')}: {selected.quantity} {selected.unit}
                                    </p>
                                )}
                            </FormField>
                            <FormField>
                                <InputLabel value={t('quantity')} />
                                <TextInput
                                    className={fieldClass}
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    value={data.quantity}
                                    onChange={(e) => setData('quantity', e.target.value)}
                                    required
                                />
                                <InputError message={errors.quantity} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('date')} />
                                <TextInput
                                    className={fieldClass}
                                    type="date"
                                    value={data.moved_on}
                                    onChange={(e) => setData('moved_on', e.target.value)}
                                    required
                                />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('supplier')} />
                                <select
                                    className={fieldClass}
                                    value={data.supplier_id}
                                    onChange={(e) => setData('supplier_id', e.target.value)}
                                >
                                    <option value="">—</option>
                                    {(suppliers || []).map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField>
                                <InputLabel value={`${t('purchase_price_iqd')} (${iqd})`} />
                                <MoneyInput
                                    className={moneyFieldClass}
                                    value={data.purchase_price_iqd}
                                    onValueChange={(raw) => setData('purchase_price_iqd', raw)}
                                />
                                <InputError message={errors.purchase_price_iqd} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={`${t('project')} (${t('optional')})`} />
                                <select
                                    className={fieldClass}
                                    value={data.project_id}
                                    onChange={(e) => setData('project_id', e.target.value)}
                                >
                                    <option value="">—</option>
                                    {(projects || []).map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                                <p className="mt-1 text-xs text-slate-500">{t('stock_in_project_optional_hint')}</p>
                            </FormField>
                            <FormField>
                                <InputLabel value={t('invoice_ref')} />
                                <TextInput
                                    className={fieldClass}
                                    value={data.invoice_ref}
                                    onChange={(e) => setData('invoice_ref', e.target.value)}
                                />
                            </FormField>
                            <FormField className="sm:col-span-2">
                                <InputLabel value={t('notes')} />
                                <textarea
                                    className={fieldClass + ' py-2'}
                                    rows={3}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                />
                            </FormField>
                        </FormSection>
                        <FormActions>
                            <PrimaryButton
                                disabled={processing}
                                className="!bg-emerald-600 hover:!bg-emerald-500"
                            >
                                {t('record_stock_in')}
                            </PrimaryButton>
                        </FormActions>
                    </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
