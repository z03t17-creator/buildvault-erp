import DateInput from '@/Components/DateInput';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyAmount from '@/Components/MoneyAmount';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { stockFieldClass, stockMoneyClass } from '@/Components/StockDesk';
import TextInput from '@/Components/TextInput';
import StockTabs from '@/Components/StockTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

export default function Create({ items = [], suppliers = [], projects = [], defaults = {} }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const { data, setData, post, processing, errors } = useForm({
        stock_item_id: '',
        quantity: '',
        moved_on: defaults.moved_on || new Date().toISOString().slice(0, 10),
        supplier_id: '',
        purchase_price_iqd: '',
        project_id: '',
        invoice_ref: '',
        shelf_zone: '',
        notes: '',
    });

    const selected = items.find((i) => String(i.id) === String(data.stock_item_id));
    const qty = Number(data.quantity) || 0;
    const unitCost = Number(data.purchase_price_iqd) || 0;
    const total = useMemo(() => Math.round(qty * unitCost * 100) / 100, [qty, unitCost]);

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('warehouse_receive')}
                    subtitle={t('warehouse_receive_hint')}
                    icon={<NavIcon name="stockIn" className="text-lg text-emerald-700 dark:text-emerald-300" />}
                    actions={
                        <Link href={route('stock.dashboard')}>
                            <SecondaryButton type="button">{t('cancel')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('warehouse_receive')} />
            <PageShell className="!max-w-4xl !space-y-4">
                <StockTabs />
                <form
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('stock.in.store'));
                    }}
                    className="bv-card space-y-4 p-4 sm:p-5"
                >
                    <FormSection cols={2}>
                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('product')} htmlFor="stock_item_id" />
                            <select
                                id="stock_item_id"
                                className={stockFieldClass}
                                value={data.stock_item_id}
                                onChange={(e) => {
                                    const id = e.target.value;
                                    const item = items.find((i) => String(i.id) === String(id));
                                    setData({
                                        ...data,
                                        stock_item_id: id,
                                        purchase_price_iqd:
                                            item?.purchase_price_iqd != null
                                                ? String(item.purchase_price_iqd)
                                                : data.purchase_price_iqd,
                                        supplier_id: item?.supplier_id
                                            ? String(item.supplier_id)
                                            : data.supplier_id,
                                        shelf_zone: item?.location || data.shelf_zone,
                                    });
                                }}
                            >
                                <option value="">{t('warehouse_pick_item')}</option>
                                {items.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name} · {item.sku || item.barcode || '—'} · {item.quantity}{' '}
                                        {item.unit}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.stock_item_id} className="mt-1" />
                            {selected ? (
                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {t('on_hand')}: {selected.quantity} {selected.unit}
                                </p>
                            ) : null}
                        </FormField>
                        <FormField>
                            <InputLabel value={t('quantity')} htmlFor="quantity" />
                            <MoneyInput
                                id="quantity"
                                className={stockMoneyClass}
                                value={data.quantity}
                                onValueChange={(next) => setData('quantity', next)}
                                allowDecimals
                            />
                            <InputError message={errors.quantity} className="mt-1" />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('date')} htmlFor="moved_on" />
                            <DateInput
                                id="moved_on"
                                className={stockFieldClass}
                                value={data.moved_on}
                                onChange={(e) => setData('moved_on', e.target.value)}
                            />
                            <InputError message={errors.moved_on} className="mt-1" />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('supplier')} htmlFor="supplier_id" />
                            <select
                                id="supplier_id"
                                className={stockFieldClass}
                                value={data.supplier_id}
                                onChange={(e) => setData('supplier_id', e.target.value)}
                            >
                                <option value="">—</option>
                                {suppliers.map((row) => (
                                    <option key={row.id} value={row.id}>
                                        {row.name}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField>
                            <InputLabel value={t('warehouse_invoice')} htmlFor="invoice_ref" />
                            <TextInput
                                id="invoice_ref"
                                className={stockFieldClass}
                                value={data.invoice_ref}
                                onChange={(e) => setData('invoice_ref', e.target.value)}
                            />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('warehouse_unit_cost')} htmlFor="purchase_price_iqd" />
                            <MoneyInput
                                id="purchase_price_iqd"
                                className={stockMoneyClass}
                                value={data.purchase_price_iqd}
                                onValueChange={(next) => setData('purchase_price_iqd', next)}
                                allowDecimals={false}
                            />
                            <InputError message={errors.purchase_price_iqd} className="mt-1" />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('warehouse_total_cost')} />
                            <p dir="ltr" className="mt-2 text-lg font-semibold tabular-nums text-emerald-700 dark:text-emerald-200">
                                <MoneyAmount value={total} label={iqd} size="lg" showLabel={false} /> {iqd}
                            </p>
                        </FormField>
                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('warehouse_shelf')} htmlFor="shelf_zone" />
                            <TextInput
                                id="shelf_zone"
                                className={stockFieldClass}
                                value={data.shelf_zone}
                                onChange={(e) => setData('shelf_zone', e.target.value)}
                                placeholder={t('warehouse_shelf_placeholder')}
                            />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('project')} htmlFor="project_id" />
                            <select
                                id="project_id"
                                className={stockFieldClass}
                                value={data.project_id}
                                onChange={(e) => setData('project_id', e.target.value)}
                            >
                                <option value="">—</option>
                                {projects.map((row) => (
                                    <option key={row.id} value={row.id}>
                                        {row.name}
                                    </option>
                                ))}
                            </select>
                            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{t('stock_in_project_optional_hint')}</p>
                        </FormField>
                        <FormField>
                            <InputLabel value={t('note')} htmlFor="notes" />
                            <TextInput
                                id="notes"
                                className={stockFieldClass}
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                            />
                        </FormField>
                    </FormSection>
                    <FormActions>
                        <PrimaryButton disabled={processing} className="!bg-emerald-600 hover:!bg-emerald-500">
                            {t('warehouse_receive_save')}
                        </PrimaryButton>
                        <Link href={route('stock.movements.index', { type: 'in' })}>
                            <SecondaryButton type="button">{t('cancel')}</SecondaryButton>
                        </Link>
                    </FormActions>
                </form>
            </PageShell>
        </AuthenticatedLayout>
    );
}
