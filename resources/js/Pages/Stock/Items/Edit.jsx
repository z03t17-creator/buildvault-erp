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
import StockTabs from '@/Components/StockTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

const moneyFieldClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 font-sans text-lg font-semibold tabular-nums shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function Edit({ item, categories, currencies = ['USD', 'IQD'] }) {
    const t = useTranslations();
    const initialCurrency = item.currency || item.cost_currency || 'IQD';
    const initialPrice =
        initialCurrency === 'USD'
            ? item.purchase_price_usd ?? item.average_unit_cost ?? 0
            : item.purchase_price_iqd ?? item.average_unit_cost ?? 0;
    const { data, setData, put, processing, errors } = useForm({
        name: item.name || '',
        sku: item.sku || '',
        stock_category_id: item.stock_category_id || '',
        unit: item.unit || 'pcs',
        currency: initialCurrency,
        purchase_price: initialPrice,
        notes: item.notes || '',
    });
    const priceLabel = data.currency === 'USD' ? t('USD') : t('IQD');
    const currencyLocked = Number(item.quantity) > 0;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('edit_product')}
                    subtitle={t('stock_product_form_hint')}
                    icon={<NavIcon name="edit" className="text-lg" />}
                    actions={
                        <Link href={route('stock.items.show', item.id)}>
                            <SecondaryButton type="button">{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('edit_product')} />
            <PageShell narrow className="!space-y-6">
                <StockTabs />
                <p className="rounded-xl border border-rose-200/70 bg-rose-50/70 px-4 py-3 text-sm text-rose-950 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-100">
                    {t('quantity')}:{' '}
                    <span className="font-sans font-semibold tabular-nums" dir="ltr">
                        {item.quantity} {item.unit}
                    </span>{' '}
                    — {t('qty_via_movements')}
                </p>
                <DataPanel>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            put(route('stock.items.update', item.id));
                        }}
                        className="space-y-5"
                    >
                        <FormSection cols={2}>
                            <FormField>
                                <InputLabel value={t('name')} />
                                <TextInput
                                    className={fieldClass}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                <InputError message={errors.name} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('sku')} />
                                <TextInput
                                    className={fieldClass}
                                    value={data.sku}
                                    onChange={(e) => setData('sku', e.target.value)}
                                />
                                <InputError message={errors.sku} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('category')} />
                                <select
                                    className={fieldClass}
                                    value={data.stock_category_id}
                                    onChange={(e) => setData('stock_category_id', e.target.value)}
                                >
                                    <option value="">{t('uncategorized')}</option>
                                    {(categories || []).map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.stock_category_id} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('unit')} />
                                <TextInput
                                    className={fieldClass}
                                    value={data.unit}
                                    onChange={(e) => setData('unit', e.target.value)}
                                    required
                                />
                                <InputError message={errors.unit} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('currency')} />
                                <div className="mt-1 flex gap-2">
                                    {currencies.map((code) => (
                                        <button
                                            key={code}
                                            type="button"
                                            disabled={currencyLocked}
                                            onClick={() => setData('currency', code)}
                                            className={
                                                'min-h-[2.5rem] flex-1 rounded-xl border text-sm font-semibold transition ' +
                                                (data.currency === code
                                                    ? 'border-rose-500 bg-rose-50 text-rose-900 ring-2 ring-rose-500/25 dark:border-rose-400 dark:bg-rose-950/40 dark:text-rose-100'
                                                    : 'border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300') +
                                                (currencyLocked ? ' opacity-60' : '')
                                            }
                                            aria-pressed={data.currency === code}
                                        >
                                            {code}
                                        </button>
                                    ))}
                                </div>
                                <InputError message={errors.currency} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={`${t('warehouse_avg_cost')} (${priceLabel})`} />
                                <MoneyInput
                                    className={moneyFieldClass}
                                    value={data.purchase_price}
                                    onValueChange={(raw) => setData('purchase_price', raw)}
                                    allowDecimals={data.currency === 'USD'}
                                />
                                <InputError message={errors.purchase_price} className="mt-1" />
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
                                className="!bg-rose-600 hover:!bg-rose-500"
                            >
                                {t('update')}
                            </PrimaryButton>
                        </FormActions>
                    </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
