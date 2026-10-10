import DateInput from '@/Components/DateInput';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyAmount from '@/Components/MoneyAmount';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import { stockFieldClass, stockMoneyClass, stockSegmentClass } from '@/Components/StockDesk';
import StockTabs from '@/Components/StockTabs';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

const PAYMENT_PROJECT_ADVANCE = 'project_advance';
const PAYMENT_MAIN_VAULT = 'main_vault';
const PAYMENT_SUPPLIER_CREDIT = 'supplier_credit';

export default function Create({
    items = [],
    projects = [],
    paymentSources = [
        PAYMENT_SUPPLIER_CREDIT,
        PAYMENT_PROJECT_ADVANCE,
        PAYMENT_MAIN_VAULT,
    ],
    defaults = {},
}) {
    const t = useTranslations();
    const iqd = t('IQD');
    const usd = t('USD');
    const { data, setData, post, processing, errors } = useForm({
        stock_item_id: '',
        quantity: '',
        moved_on: defaults.moved_on || new Date().toISOString().slice(0, 10),
        currency: 'IQD',
        purchase_price: '',
        payment_source: defaults.payment_source || PAYMENT_SUPPLIER_CREDIT,
        project_id: '',
        shelf_zone: '',
        notes: '',
    });

    const selected = items.find((i) => String(i.id) === String(data.stock_item_id));
    const costCurrency =
        selected?.cost_currency || selected?.currency || data.currency || 'IQD';
    const costLabel = costCurrency === 'USD' ? usd : iqd;
    const qty = Number(data.quantity) || 0;
    const unitCost = Number(data.purchase_price) || 0;
    const total = useMemo(() => Math.round(qty * unitCost * 100) / 100, [qty, unitCost]);

    const project = projects.find((p) => String(p.id) === String(data.project_id));
    const advanceAvailable = project
        ? costCurrency === 'USD'
            ? Number(project.advance_usd) || 0
            : Number(project.advance_iqd) || 0
        : null;

    const paymentLabels = {
        [PAYMENT_PROJECT_ADVANCE]: t('warehouse_pay_sulfa'),
        [PAYMENT_MAIN_VAULT]: t('warehouse_pay_vault'),
        [PAYMENT_SUPPLIER_CREDIT]: t('warehouse_pay_credit'),
    };

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('warehouse_tab_receive')}
                    subtitle={t('warehouse_receive_sulfa_hint')}
                    icon={<NavIcon name="stockIn" className="text-lg text-emerald-700 dark:text-emerald-300" />}
                />
            }
        >
            <Head title={t('warehouse_tab_receive')} />
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
                                    const currency =
                                        item?.cost_currency || item?.currency || 'IQD';
                                    const unit =
                                        item?.unit_cost != null
                                            ? item.unit_cost
                                            : currency === 'USD'
                                              ? item?.purchase_price_usd
                                              : item?.purchase_price_iqd;
                                    setData({
                                        ...data,
                                        stock_item_id: id,
                                        currency,
                                        purchase_price:
                                            unit != null && unit !== ''
                                                ? String(unit)
                                                : data.purchase_price,
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
                            <InputLabel
                                value={`${t('warehouse_unit_cost')} (${costLabel})`}
                                htmlFor="purchase_price"
                            />
                            <MoneyInput
                                id="purchase_price"
                                className={stockMoneyClass}
                                value={data.purchase_price}
                                onValueChange={(next) => setData('purchase_price', next)}
                                allowDecimals={costCurrency === 'USD'}
                            />
                            <InputError
                                message={errors.purchase_price || errors.purchase_price_iqd}
                                className="mt-1"
                            />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('warehouse_total_cost')} />
                            <p
                                dir="ltr"
                                className="mt-2 text-lg font-semibold tabular-nums text-emerald-300"
                            >
                                <MoneyAmount value={total} label={costLabel} size="lg" showLabel={false} />{' '}
                                {costLabel}
                            </p>
                        </FormField>
                    </FormSection>

                    <div className="space-y-3 rounded-2xl border border-slate-700 bg-slate-950/50 p-4">
                        <p className="text-sm font-semibold text-slate-100">
                            {t('warehouse_payment_source')}
                        </p>
                        <div className="flex flex-col gap-2 sm:flex-row">
                            {paymentSources.map((source) => (
                                <button
                                    key={source}
                                    type="button"
                                    className={stockSegmentClass(
                                        data.payment_source === source,
                                        source === PAYMENT_PROJECT_ADVANCE ? 'amber' : 'emerald',
                                    )}
                                    onClick={() => setData('payment_source', source)}
                                >
                                    {paymentLabels[source] || source}
                                </button>
                            ))}
                        </div>
                        <InputError message={errors.payment_source} className="mt-1" />

                        {data.payment_source === PAYMENT_PROJECT_ADVANCE ||
                        data.payment_source === PAYMENT_MAIN_VAULT ? (
                            <FormField>
                                <InputLabel value={t('project')} htmlFor="project_id" />
                                <select
                                    id="project_id"
                                    className={stockFieldClass}
                                    value={data.project_id}
                                    onChange={(e) => setData('project_id', e.target.value)}
                                >
                                    <option value="">{t('warehouse_pick_project')}</option>
                                    {projects.map((row) => (
                                        <option key={row.id} value={row.id}>
                                            {row.name}
                                            {data.payment_source === PAYMENT_PROJECT_ADVANCE
                                                ? ` · ${costLabel} ${(
                                                      costCurrency === 'USD'
                                                          ? row.advance_usd
                                                          : row.advance_iqd
                                                  ).toLocaleString()}`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.project_id} className="mt-1" />
                                {data.payment_source === PAYMENT_PROJECT_ADVANCE &&
                                advanceAvailable != null ? (
                                    <p className="mt-1 text-xs text-amber-200/90">
                                        {t('warehouse_sulfa_available', {
                                            amount: advanceAvailable.toLocaleString(),
                                            currency: costLabel,
                                        })}
                                    </p>
                                ) : null}
                            </FormField>
                        ) : null}
                    </div>

                    <FormSection cols={1}>
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
                        <PrimaryButton
                            disabled={processing}
                            className="min-h-[3rem] min-w-[12rem] !bg-emerald-600 hover:!bg-emerald-500"
                        >
                            {t('warehouse_receive_save')}
                        </PrimaryButton>
                    </FormActions>
                </form>
            </PageShell>
        </AuthenticatedLayout>
    );
}
