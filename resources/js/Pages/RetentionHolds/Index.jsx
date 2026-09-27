import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

export default function Index({ holds, matured }) {
    const list = holds || [];
    const ready = matured || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Insurance holds"
                    subtitle="Shared 10% reserve · returns to payroll after 6 months"
                />
            }
        >
            <Head title="Insurance holds" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    {ready.length > 0 && (
                        <section className="border border-amber-300/80 bg-amber-50/90 p-4 dark:border-amber-700/60 dark:bg-amber-950/40">
                            <h3 className="font-semibold text-amber-950 dark:text-amber-100">
                                Matured — release to staff payroll
                            </h3>
                            <ul className="mt-3 space-y-2 text-sm">
                                {ready.map((h) => (
                                    <li key={h.id} className="flex flex-wrap items-center justify-between gap-2">
                                        <span>
                                            #{h.id} {h.worker?.name} · {h.amount_usd} USD
                                        </span>
                                        <PrimaryButton
                                            type="button"
                                            onClick={() => router.post(route('retention-holds.release', h.id))}
                                        >
                                            Release
                                        </PrimaryButton>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <div className="overflow-x-auto border border-slate-200/80 bg-white/80 dark:border-slate-700 dark:bg-slate-900/70">
                        <table className="min-w-full text-sm">
                            <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                                <tr>
                                    <th className="px-3 py-2 text-start">ID</th>
                                    <th className="px-3 py-2 text-start">Worker</th>
                                    <th className="px-3 py-2 text-start">Project</th>
                                    <th className="px-3 py-2 text-start">Amount</th>
                                    <th className="px-3 py-2 text-start">Hold start</th>
                                    <th className="px-3 py-2 text-start">Matures</th>
                                    <th className="px-3 py-2 text-start">Status</th>
                                    <th className="px-3 py-2 text-start">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {list.map((h) => (
                                    <tr key={h.id}>
                                        <td className="px-3 py-2">#{h.id}</td>
                                        <td className="px-3 py-2">{h.worker?.name || '—'}</td>
                                        <td className="px-3 py-2">{h.project?.name || '—'}</td>
                                        <td className="px-3 py-2 tabular-nums">{h.amount_usd}</td>
                                        <td className="px-3 py-2">{h.hold_start}</td>
                                        <td className="px-3 py-2">{h.maturity_date}</td>
                                        <td className="px-3 py-2"><StatusBadge status={h.status} /></td>
                                        <td className="px-3 py-2">
                                            {h.status === 'matured' ? (
                                                <PrimaryButton
                                                    type="button"
                                                    onClick={() => router.post(route('retention-holds.release', h.id))}
                                                >
                                                    Release
                                                </PrimaryButton>
                                            ) : (
                                                <span className="text-slate-400">—</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {!list.length && (
                                    <tr>
                                        <td colSpan={8} className="px-3 py-8 text-center text-slate-500">
                                            No insurance holds yet. They are created when payroll payouts with holdback are approved.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
