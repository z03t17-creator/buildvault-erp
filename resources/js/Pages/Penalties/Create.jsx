import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import MoneyInput from '@/Components/MoneyInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Create({ projects, workers, payouts, types, defaults }) {
    const t = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        worker_id: '',
        project_id: '',
        payout_id: '',
        type: defaults?.type || 'other',
        reason: '',
        notes: '',
        occurred_on: defaults?.occurred_on || '',
        amount_iqd: '',
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

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('record_penalty')}
                    subtitle={t('penalties_subtitle')}
                    actions={
                        <Link href={route('penalties.index')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('record_penalty')} />
            <div className="py-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('penalties.store'));
                    }}
                    className="mx-auto max-w-xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                >
                    <div>
                        <InputLabel value={t('worker')} />
                        <select className={selectClass} value={data.worker_id} onChange={(e) => setData('worker_id', e.target.value)} required>
                            <option value="">—</option>
                            {(workers || []).map((w) => (
                                <option key={w.id} value={w.id}>{w.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.worker_id} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('project')} />
                        <select className={selectClass} value={data.project_id} onChange={(e) => setData('project_id', e.target.value)} required>
                            <option value="">—</option>
                            {(projects || []).map((p) => (
                                <option key={p.id} value={p.id}>{p.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.project_id} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('penalty_type')} />
                        <select className={selectClass} value={data.type} onChange={(e) => setData('type', e.target.value)} required>
                            {(types || []).map((type) => (
                                <option key={type} value={type}>{t(`penalty_type_${type}`) || type}</option>
                            ))}
                        </select>
                        <InputError message={errors.type} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('date')} />
                        <TextInput type="date" className="mt-1 block w-full" value={data.occurred_on} onChange={(e) => setData('occurred_on', e.target.value)} required />
                        <InputError message={errors.occurred_on} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('amount_iqd')} />
                        <MoneyInput
                            className="mt-1 block w-full"
                            value={data.amount_iqd}
                            onValueChange={(raw) => setData('amount_iqd', raw)}
                            required
                        />
                        <InputError message={errors.amount_iqd} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('reason')} />
                        <textarea className={selectClass} rows={3} value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
                        <InputError message={errors.reason} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('notes')} />
                        <textarea className={selectClass} rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        <InputError message={errors.notes} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('link_payout_optional')} />
                        <select className={selectClass} value={data.payout_id} onChange={(e) => setData('payout_id', e.target.value)}>
                            <option value="">—</option>
                            {(payouts || []).map((p) => (
                                <option key={p.id} value={p.id}>
                                    #{p.id} {p.worker?.name} · {p.status}
                                </option>
                            ))}
                        </select>
                    </div>
                    <PrimaryButton disabled={processing}>{t('save')}</PrimaryButton>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
