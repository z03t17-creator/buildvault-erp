import MoneyAmount from '@/Components/MoneyAmount';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { stockStatusOf } from '@/Components/StockDesk';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

function Row({ label, children }) {
    return (
        <tr className="border-b border-slate-200">
            <th className="w-40 py-2 pe-4 text-start text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </th>
            <td className="py-2 text-sm font-medium text-slate-900">{children}</td>
        </tr>
    );
}

function hasText(value) {
    return String(value ?? '').trim() !== '';
}

export default function Print({ item, printedAt }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const usd = t('USD');
    const movements = item.movements || [];
    const categoryLabel =
        item.category_label || item.stock_category?.name || item.category || '';
    const costCurrency = item.cost_currency || item.currency || 'IQD';
    const costLabel = costCurrency === 'USD' ? usd : iqd;
    const unitCost = Number(item.average_unit_cost ?? 0);
    const stockValue = Number(
        item.stock_value ??
            (costCurrency === 'USD' ? item.stock_value_usd : item.stock_value_iqd) ??
            0,
    );
    const qty = Number(item.quantity ?? 0);

    const statusLabel = useMemo(() => {
        const status = stockStatusOf(item);
        if (status === 'out') return t('stock_out');
        if (status === 'low') return t('stock_low');
        return t('stock_ok');
    }, [item, t]);

    const typeLabel = (type) => {
        if (type === 'in') return t('warehouse_tab_receive');
        if (type === 'out') return t('warehouse_tab_dispatch');
        return type || '';
    };

    // Only real data — skip empty fields the user cleared from the forms.
    const detailRows = [
        { key: 'name', label: t('name'), value: item.name, node: item.name },
        hasText(item.sku)
            ? {
                  key: 'sku',
                  label: t('sku'),
                  value: item.sku,
                  node: <span dir="ltr">{item.sku}</span>,
              }
            : null,
        hasText(item.barcode) && item.barcode !== item.sku
            ? {
                  key: 'barcode',
                  label: t('barcode'),
                  value: item.barcode,
                  node: <span dir="ltr">{item.barcode}</span>,
              }
            : null,
        hasText(categoryLabel)
            ? { key: 'category', label: t('category'), value: categoryLabel, node: categoryLabel }
            : null,
        hasText(item.unit)
            ? { key: 'unit', label: t('unit'), value: item.unit, node: item.unit }
            : null,
        {
            key: 'quantity',
            label: t('quantity'),
            value: qty,
            node: (
                <span dir="ltr">
                    {item.quantity} {item.unit || ''}
                </span>
            ),
        },
        {
            key: 'currency',
            label: t('currency'),
            value: costLabel,
            node: costLabel,
        },
        unitCost > 0
            ? {
                  key: 'avg',
                  label: t('warehouse_avg_cost'),
                  value: unitCost,
                  node: (
                      <span dir="ltr" className="inline-flex items-baseline gap-1">
                          <MoneyAmount
                              value={unitCost}
                              label={costLabel}
                              size="sm"
                              showLabel={false}
                          />
                          {costLabel}
                      </span>
                  ),
              }
            : null,
        qty > 0 && stockValue > 0
            ? {
                  key: 'value',
                  label: t('stock_value'),
                  value: stockValue,
                  node: (
                      <span dir="ltr" className="inline-flex items-baseline gap-1">
                          <MoneyAmount
                              value={stockValue}
                              label={costLabel}
                              size="sm"
                              showLabel={false}
                          />
                          {costLabel}
                      </span>
                  ),
              }
            : null,
        {
            key: 'status',
            label: t('stock_status'),
            value: statusLabel,
            node: statusLabel,
        },
        hasText(item.notes)
            ? { key: 'notes', label: t('notes'), value: item.notes, node: item.notes }
            : null,
    ].filter(Boolean);

    useEffect(() => {
        const timer = window.setTimeout(() => window.print(), 350);
        return () => window.clearTimeout(timer);
    }, []);

    return (
        <div className="min-h-screen bg-slate-100 text-slate-900 print:bg-white">
            <Head title={`${t('print')} · ${item.name}`} />

            <div className="mx-auto max-w-3xl px-4 py-6 print:max-w-none print:px-0 print:py-0">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-2 print:hidden">
                    <div className="flex flex-wrap gap-2">
                        <Link href={route('stock.items.show', item.id)}>
                            <SecondaryButton type="button">{t('back')}</SecondaryButton>
                        </Link>
                        <Link href={route('stock.dashboard')}>
                            <SecondaryButton type="button">{t('warehouse_tab_balance')}</SecondaryButton>
                        </Link>
                    </div>
                    <PrimaryButton type="button" onClick={() => window.print()}>
                        {t('print')}
                    </PrimaryButton>
                </div>

                <article className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none">
                    <header className="mb-6 border-b border-slate-300 pb-4">
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            BuildVault · {t('warehouse_title')}
                        </p>
                        <h1 className="mt-1 text-2xl font-bold text-slate-900">{item.name}</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            {t('warehouse_item_print_subtitle')}
                            {printedAt ? ` · ${printedAt}` : ''}
                        </p>
                    </header>

                    <section className={movements.length > 0 ? 'mb-6' : ''}>
                        <h2 className="mb-2 text-sm font-bold text-slate-800">
                            {t('warehouse_item_details')}
                        </h2>
                        <table className="w-full">
                            <tbody>
                                {detailRows.map((row) => (
                                    <Row key={row.key} label={row.label}>
                                        {row.node}
                                    </Row>
                                ))}
                            </tbody>
                        </table>
                    </section>

                    {movements.length > 0 ? (
                        <section>
                            <h2 className="mb-2 text-sm font-bold text-slate-800">
                                {t('stock_movements')}
                            </h2>
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr className="border-b-2 border-slate-300 text-start text-xs uppercase tracking-wide text-slate-500">
                                        <th className="py-2 pe-2 font-semibold">{t('date')}</th>
                                        <th className="py-2 pe-2 font-semibold">{t('type')}</th>
                                        <th className="py-2 pe-2 font-semibold">{t('quantity')}</th>
                                        <th className="py-2 pe-2 font-semibold">{t('project')}</th>
                                        <th className="py-2 pe-2 font-semibold">
                                            {t('warehouse_unit_cost')}
                                        </th>
                                        <th className="py-2 font-semibold">{t('warehouse_total_cost')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {movements.map((m) => {
                                        const mCurrency =
                                            (m.currency || costCurrency) === 'USD' ? usd : iqd;
                                        const unit =
                                            mCurrency === usd
                                                ? m.purchase_price_usd
                                                : m.purchase_price_iqd;
                                        const total =
                                            mCurrency === usd ? m.total_cost_usd : m.total_cost_iqd;
                                        const place = [
                                            m.project?.name,
                                            m.receiver || m.staff?.name,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ');
                                        return (
                                            <tr key={m.id} className="border-b border-slate-200">
                                                <td className="py-1.5 pe-2" dir="ltr">
                                                    {m.moved_on || ''}
                                                </td>
                                                <td className="py-1.5 pe-2">{typeLabel(m.type)}</td>
                                                <td className="py-1.5 pe-2" dir="ltr">
                                                    {m.quantity} {item.unit || ''}
                                                </td>
                                                <td className="py-1.5 pe-2">{place}</td>
                                                <td className="py-1.5 pe-2" dir="ltr">
                                                    {Number(unit || 0) > 0
                                                        ? `${Number(unit || 0).toLocaleString()} ${mCurrency}`
                                                        : ''}
                                                </td>
                                                <td className="py-1.5" dir="ltr">
                                                    {Number(total || 0) > 0
                                                        ? `${Number(total || 0).toLocaleString()} ${mCurrency}`
                                                        : ''}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </section>
                    ) : null}
                </article>
            </div>
        </div>
    );
}
