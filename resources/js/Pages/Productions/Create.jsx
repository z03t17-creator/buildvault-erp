import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

function previewRemaining(assigned, completed) {
    const a = Number(assigned) || 0;
    const c = Number(completed) || 0;
    return Math.max(0, Math.round((a - c) * 100) / 100);
}

function previewProgress(assigned, completed) {
    const a = Number(assigned) || 0;
    const c = Number(completed) || 0;
    if (a <= 0) return 0;
    return Math.round((c / a) * 10000) / 100;
}

export default function Create({ projects, workers, unitTypes, defaults }) {
    const t = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        worker_id: '',
        project_id: '',
        unit_type: defaults?.unit_type || 'apartment',
        unit_label: '',
        assigned: defaults?.assigned ?? '',
        completed: defaults?.completed ?? '',
        received: defaults?.received ?? '',
        recorded_on: defaults?.recorded_on || '',
        notes: '',
    });

    useEffect(() => {
        if (!data.worker_id) {
            return;
        }
        const worker = (workers || []).find((w) => String(w.id) === String(data.worker_id));
        if (worker?.project_id && !data.project_id) {
            setData('project_id', String(worker.project_id));
        }
    }, [data.worker_id]);

    const remaining = useMemo(
        () => previewRemaining(data.assigned, data.completed),
        [data.assigned, data.completed],
    );
    const progress = useMemo(
        () => previewProgress(data.assigned, data.completed),
        [data.assigned, data.completed],
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('record_production')}
                    subtitle={t('productions_subtitle')}
                    actions={
                        <Link href={route('productions.index')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('record_production')} />
            <div className="py-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('productions.store'));
                    }}
                    className="mx-auto max-w-xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                >
                    <p className="text-xs text-slate-500">{t('production_qty_hint')}</p>
                    <div>
                        <InputLabel value={t('worker')} />
                        <select
                            className={selectClass}
                            value={data.worker_id}
                            onChange={(e) => setData('worker_id', e.target.value)}
                            required
                        >
                            <option value="">—</option>
                            {(workers || []).map((w) => (
                                <option key={w.id} value={w.id}>
                                    {w.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.worker_id} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('project')} />
                        <select
                            className={selectClass}
                            value={data.project_id}
                            onChange={(e) => setData('project_id', e.target.value)}
                            required
                        >
                            <option value="">—</option>
                            {(projects || []).map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.project_id} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('unit_type')} />
                        <select
                            className={selectClass}
                            value={data.unit_type}
                            onChange={(e) => setData('unit_type', e.target.value)}
                            required
                        >
                            {(unitTypes || []).map((u) => (
                                <option key={u} value={u}>
                                    {t(`unit_type_${u}`) !== `unit_type_${u}` ? t(`unit_type_${u}`) : u}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.unit_type} className="mt-1" />
                    </div>
                    {data.unit_type === 'other' && (
                        <div>
                            <InputLabel value={t('unit_label_custom')} />
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.unit_label}
                                onChange={(e) => setData('unit_label', e.target.value)}
                                required
                            />
                            <InputError message={errors.unit_label} className="mt-1" />
                        </div>
                    )}
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel value={t('assigned')} />
                            <TextInput
                                type="number"
                                step="0.01"
                                min="0"
                                className="mt-1 block w-full"
                                value={data.assigned}
                                onChange={(e) => setData('assigned', e.target.value)}
                                required
                            />
                            <InputError message={errors.assigned} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value={t('completed')} />
                            <TextInput
                                type="number"
                                step="0.01"
                                min="0"
                                className="mt-1 block w-full"
                                value={data.completed}
                                onChange={(e) => setData('completed', e.target.value)}
                                required
                            />
                            <InputError message={errors.completed} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value={t('received')} />
                            <TextInput
                                type="number"
                                step="0.01"
                                min="0"
                                className="mt-1 block w-full"
                                value={data.received}
                                onChange={(e) => setData('received', e.target.value)}
                            />
                            <InputError message={errors.received} className="mt-1" />
                        </div>
                    </div>
                    <div className="grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-950/50 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('remaining')}</dt>
                            <dd className="tabular-nums text-amber-700 dark:text-amber-300">{remaining}</dd>
                            <p className="mt-1 text-xs text-slate-500">{t('remaining_formula')}</p>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('progress_pct')}</dt>
                            <dd className="tabular-nums">{progress}%</dd>
                        </div>
                    </div>
                    <div>
                        <InputLabel value={t('date')} />
                        <TextInput
                            type="date"
                            className="mt-1 block w-full"
                            value={data.recorded_on}
                            onChange={(e) => setData('recorded_on', e.target.value)}
                            required
                        />
                        <InputError message={errors.recorded_on} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('notes')} />
                        <textarea
                            className={selectClass}
                            rows={2}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                        <InputError message={errors.notes} className="mt-1" />
                    </div>
                    <PrimaryButton disabled={processing}>{t('save')}</PrimaryButton>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
