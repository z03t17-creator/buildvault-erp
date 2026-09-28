import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Create({ projects, workers, repaymentMethods, defaults }) {
    const t = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        worker_id: '',
        project_id: '',
        amount_iqd: '',
        remaining_iqd: '',
        advanced_on: defaults?.advanced_on || '',
        reason: '',
        repayment_method: defaults?.repayment_method || 'payroll_deduction',
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

    useEffect(() => {
        if (data.amount_iqd !== '' && data.remaining_iqd === '') {
            setData('remaining_iqd', data.amount_iqd);
        }
    }, [data.amount_iqd]);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('record_advance')}
                    subtitle={t('advances_subtitle')}
                    actions={
                        <Link href={route('advances.index')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('record_advance')} />
            <div className="py-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('advances.store'));
                    }}
                    className="mx-auto max-w-xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                >
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
                        <InputLabel value={t('amount_iqd')} />
                        <TextInput
                            type="number"
                            step="1"
                            min="1"
                            className="mt-1 block w-full"
                            value={data.amount_iqd}
                            onChange={(e) => setData('amount_iqd', e.target.value)}
                            required
                        />
                        <InputError message={errors.amount_iqd} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('remaining_iqd')} />
                        <TextInput
                            type="number"
                            step="1"
                            min="0"
                            className="mt-1 block w-full"
                            value={data.remaining_iqd}
                            onChange={(e) => setData('remaining_iqd', e.target.value)}
                        />
                        <p className="mt-1 text-xs text-slate-500">{t('remaining_iqd_hint')}</p>
                        <InputError message={errors.remaining_iqd} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('date')} />
                        <TextInput
                            type="date"
                            className="mt-1 block w-full"
                            value={data.advanced_on}
                            onChange={(e) => setData('advanced_on', e.target.value)}
                            required
                        />
                        <InputError message={errors.advanced_on} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('repayment_method')} />
                        <select
                            className={selectClass}
                            value={data.repayment_method}
                            onChange={(e) => setData('repayment_method', e.target.value)}
                            required
                        >
                            {(repaymentMethods || []).map((m) => (
                                <option key={m} value={m}>
                                    {t(`repay_${m}`) || m}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.repayment_method} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('reason')} />
                        <textarea
                            className={selectClass}
                            rows={3}
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                            required
                        />
                        <InputError message={errors.reason} className="mt-1" />
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
