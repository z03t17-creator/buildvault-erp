import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Create({ projects, workers, payouts }) {
    const { data, setData, post, processing, errors } = useForm({
        worker_id: '',
        project_id: '',
        payout_id: '',
        reason: '',
        amount_usd: '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Record penalty"
                    actions={
                        <Link href={route('penalties.index')}>
                            <SecondaryButton>Back</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title="Record penalty" />
            <div className="py-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('penalties.store'));
                    }}
                    className="mx-auto max-w-xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                >
                    <div>
                        <InputLabel value="Worker" />
                        <select className={selectClass} value={data.worker_id} onChange={(e) => setData('worker_id', e.target.value)} required>
                            <option value="">—</option>
                            {(workers || []).map((w) => (
                                <option key={w.id} value={w.id}>{w.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.worker_id} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Project" />
                        <select className={selectClass} value={data.project_id} onChange={(e) => setData('project_id', e.target.value)} required>
                            <option value="">—</option>
                            {(projects || []).map((p) => (
                                <option key={p.id} value={p.id}>{p.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.project_id} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Link payout (optional — deduct on reconcile)" />
                        <select className={selectClass} value={data.payout_id} onChange={(e) => setData('payout_id', e.target.value)}>
                            <option value="">— none —</option>
                            {(payouts || []).map((p) => (
                                <option key={p.id} value={p.id}>
                                    #{p.id} {p.worker?.name} · {p.amount_usd} · {p.status}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Amount USD" />
                        <TextInput type="number" step="0.01" className="mt-1 block w-full" value={data.amount_usd} onChange={(e) => setData('amount_usd', e.target.value)} required />
                        <InputError message={errors.amount_usd} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Reason" />
                        <textarea className={selectClass} rows={3} value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
                        <InputError message={errors.reason} className="mt-1" />
                    </div>
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
