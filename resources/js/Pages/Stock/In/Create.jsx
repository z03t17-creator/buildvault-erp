import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Create({ items, suppliers, projects, defaults }) {
    const t = useTranslations();
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
                    subtitle={t('stock_in_hint')}
                    actions={
                        <Link href={route('stock.dashboard')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('stock_in')} />
            <div className="py-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('stock.in.store'));
                    }}
                    className="mx-auto max-w-xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                >
                    <div>
                        <InputLabel value={t('product')} />
                        <select
                            className={selectClass}
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
                    </div>
                    <div>
                        <InputLabel value={t('quantity')} />
                        <TextInput
                            className="mt-1 block w-full"
                            type="number"
                            step="0.001"
                            min="0.001"
                            value={data.quantity}
                            onChange={(e) => setData('quantity', e.target.value)}
                            required
                        />
                        <InputError message={errors.quantity} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('date')} />
                        <TextInput
                            className="mt-1 block w-full"
                            type="date"
                            value={data.moved_on}
                            onChange={(e) => setData('moved_on', e.target.value)}
                            required
                        />
                    </div>
                    <div>
                        <InputLabel value={t('supplier')} />
                        <select
                            className={selectClass}
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
                    </div>
                    <div>
                        <InputLabel value={t('purchase_price_iqd')} />
                        <TextInput
                            className="mt-1 block w-full"
                            type="number"
                            step="1"
                            min="0"
                            value={data.purchase_price_iqd}
                            onChange={(e) => setData('purchase_price_iqd', e.target.value)}
                        />
                    </div>
                    <div>
                        <InputLabel value={t('project')} />
                        <select
                            className={selectClass}
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
                    </div>
                    <div>
                        <InputLabel value={t('invoice_ref')} />
                        <TextInput
                            className="mt-1 block w-full"
                            value={data.invoice_ref}
                            onChange={(e) => setData('invoice_ref', e.target.value)}
                        />
                    </div>
                    <div>
                        <InputLabel value={t('notes')} />
                        <textarea
                            className={selectClass}
                            rows={3}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                    </div>
                    <PrimaryButton disabled={processing}>{t('record_stock_in')}</PrimaryButton>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
