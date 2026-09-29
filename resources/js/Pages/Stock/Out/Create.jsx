import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';
import PageShell from '@/Components/PageShell';
import DataPanel from '@/Components/DataPanel';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Create({ items, projects, towers, floors, defaults }) {
    const t = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        stock_item_id: '',
        quantity: '',
        moved_on: defaults?.moved_on || new Date().toISOString().slice(0, 10),
        project_id: '',
        tower_id: '',
        floor_id: '',
        receiver: '',
        issuer: defaults?.issuer || '',
        purpose: '',
        reference: '',
        notes: '',
    });

    const selected = (items || []).find((i) => String(i.id) === String(data.stock_item_id));
    const projectTowers = (towers || []).filter((tw) => !data.project_id || String(tw.project_id) === String(data.project_id));
    const towerFloors = (floors || []).filter((f) => !data.tower_id || String(f.tower_id) === String(data.tower_id));

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('stock_out_action')}
                    subtitle={t('stock_out_hint')}
                    actions={
                        <Link href={route('stock.dashboard')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('stock_out_action')} />
            <PageShell narrow>
                <DataPanel>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('stock.out.store'));
                    }}
                    className="space-y-5 dark:"
                >
                    <div>
                        <InputLabel value={t('product')} />
                        <select
                            className={selectClass}
                            value={data.stock_item_id}
                            onChange={(e) => setData('stock_item_id', e.target.value)}
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
                        <InputLabel value={`${t('project')} *`} />
                        <select
                            className={selectClass}
                            value={data.project_id}
                            onChange={(e) => {
                                setData('project_id', e.target.value);
                                setData('tower_id', '');
                                setData('floor_id', '');
                            }}
                            required
                        >
                            <option value="">—</option>
                            {(projects || []).map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-xs text-slate-500">{t('stock_out_project_required')}</p>
                        <InputError message={errors.project_id} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={`${t('tower')} (${t('optional')})`} />
                        <select
                            className={selectClass}
                            value={data.tower_id}
                            onChange={(e) => {
                                setData('tower_id', e.target.value);
                                setData('floor_id', '');
                            }}
                            disabled={!data.project_id}
                        >
                            <option value="">—</option>
                            {projectTowers.map((tw) => (
                                <option key={tw.id} value={tw.id}>
                                    {tw.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.tower_id} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={`${t('floor')} (${t('optional')})`} />
                        <select
                            className={selectClass}
                            value={data.floor_id}
                            onChange={(e) => setData('floor_id', e.target.value)}
                            disabled={!data.tower_id}
                        >
                            <option value="">—</option>
                            {towerFloors.map((f) => (
                                <option key={f.id} value={f.id}>
                                    {f.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.floor_id} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('receiver')} />
                        <TextInput
                            className="mt-1 block w-full"
                            value={data.receiver}
                            onChange={(e) => setData('receiver', e.target.value)}
                        />
                    </div>
                    <div>
                        <InputLabel value={t('issuer')} />
                        <TextInput
                            className="mt-1 block w-full"
                            value={data.issuer}
                            onChange={(e) => setData('issuer', e.target.value)}
                        />
                    </div>
                    <div>
                        <InputLabel value={t('purpose')} />
                        <TextInput
                            className="mt-1 block w-full"
                            value={data.purpose}
                            onChange={(e) => setData('purpose', e.target.value)}
                        />
                    </div>
                    <div>
                        <InputLabel value={t('reference')} />
                        <TextInput
                            className="mt-1 block w-full"
                            value={data.reference}
                            onChange={(e) => setData('reference', e.target.value)}
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
                    <PrimaryButton disabled={processing}>{t('record_stock_out')}</PrimaryButton>
                </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
