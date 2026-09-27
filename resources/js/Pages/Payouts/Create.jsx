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

export default function Create({ projects, workers, categories }) {
    const { data, setData, post, processing, errors } = useForm({
        project_id: '',
        worker_id: '',
        category: 'payroll',
        amount_usd: '',
        notes: '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="New payout"
                    actions={
                        <Link href={route('payouts.index')}>
                            <SecondaryButton>Back</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title="New payout" />
            <div className="py-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('payouts.store'));
                    }}
                    className="mx-auto max-w-xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                >
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
                        <InputLabel value="Category" />
                        <select className={selectClass} value={data.category} onChange={(e) => setData('category', e.target.value)}>
                            {(categories || []).map((c) => (
                                <option key={c} value={c}>{c}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Worker (payroll)" />
                        <select className={selectClass} value={data.worker_id} onChange={(e) => setData('worker_id', e.target.value)}>
                            <option value="">—</option>
                            {(workers || []).map((w) => (
                                <option key={w.id} value={w.id}>{w.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Amount USD" />
                        <TextInput type="number" step="0.01" className="mt-1 block w-full" value={data.amount_usd} onChange={(e) => setData('amount_usd', e.target.value)} required />
                        <InputError message={errors.amount_usd} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Notes" />
                        <textarea className={selectClass} rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>
                    <PrimaryButton disabled={processing}>Create pending</PrimaryButton>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
