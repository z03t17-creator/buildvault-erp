import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, router, useForm, usePage } from '@inertiajs/react';

function formatIqd(n, iqdLabel = 'IQD') {
    return (
        new Intl.NumberFormat('en-US', {
            maximumFractionDigits: 0,
        }).format(Number(n) || 0) +
        ' ' +
        iqdLabel
    );
}

export default function Index({ holds, matured, settings, exchangeRate }) {
    const t = useTranslations();
    const page = usePage();
    const canRetention = useCan('vault.retention');
    const shared = page.props.insuranceSettings || {};
    const cfg = settings || shared || {};
    const list = holds || [];
    const ready = matured || [];
    const iqd = t('IQD');
    const rate = page.props.exchangeRate || exchangeRate || 1310;

    const form = useForm({
        holdback_pct: cfg.holdback_pct ?? 10,
        maturity_months: cfg.maturity_months ?? 6,
    });

    const hint = t('insurance_holds_hint', {
        percent: form.data.holdback_pct ?? cfg.holdback_pct ?? 10,
        months: form.data.maturity_months ?? cfg.maturity_months ?? 6,
    });

    const amountIqd = (h) =>
        formatIqd(
            h.amount_iqd ?? Math.round(Number(h.amount_usd || 0) * Number(rate || 1310)),
            iqd,
        );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('insurance_holds')}
                    subtitle={hint}
                />
            }
        >
            <Head title={t('insurance_holds')} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    {canRetention && (
                    <section className="bv-surface p-4 sm:p-5">
                        <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('insurance_settings')}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {t('insurance_settings_hint')}
                        </p>
                        <form
                            className="mt-4 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.put(route('retention-holds.settings'), { preserveScroll: true });
                            }}
                        >
                            <div>
                                <InputLabel htmlFor="holdback_pct" value={t('holdback_percent')} />
                                <TextInput
                                    id="holdback_pct"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    className="mt-1 block w-36"
                                    value={form.data.holdback_pct}
                                    onChange={(e) => form.setData('holdback_pct', e.target.value)}
                                />
                                <InputError message={form.errors.holdback_pct} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="maturity_months" value={t('maturity_months')} />
                                <TextInput
                                    id="maturity_months"
                                    type="number"
                                    min="1"
                                    max="120"
                                    step="1"
                                    className="mt-1 block w-36"
                                    value={form.data.maturity_months}
                                    onChange={(e) => form.setData('maturity_months', e.target.value)}
                                />
                                <InputError message={form.errors.maturity_months} className="mt-1" />
                            </div>
                            <PrimaryButton type="submit" disabled={form.processing}>
                                {t('save_settings')}
                            </PrimaryButton>
                        </form>
                    </section>
                    )}

                    {canRetention && ready.length > 0 && (
                        <section className="border border-amber-300/80 bg-amber-50/90 p-4 dark:border-amber-700/60 dark:bg-amber-950/40">
                            <h3 className="font-semibold text-amber-950 dark:text-amber-100">
                                {t('matured_release_heading')}
                            </h3>
                            <ul className="mt-3 space-y-2 text-sm">
                                {ready.map((h) => (
                                    <li key={h.id} className="flex flex-wrap items-center justify-between gap-2">
                                        <span>
                                            #{h.id} {h.worker?.name} · {amountIqd(h)}
                                        </span>
                                        <PrimaryButton
                                            type="button"
                                            onClick={() => router.post(route('retention-holds.release', h.id))}
                                        >
                                            {t('release')}
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
                                    <th className="px-3 py-2 text-start">{t('col_id')}</th>
                                    <th className="px-3 py-2 text-start">{t('Worker')}</th>
                                    <th className="px-3 py-2 text-start">{t('Project')}</th>
                                    <th className="px-3 py-2 text-start">{t('Amount')}</th>
                                    <th className="px-3 py-2 text-start">{t('hold_start')}</th>
                                    <th className="px-3 py-2 text-start">{t('matures')}</th>
                                    <th className="px-3 py-2 text-start">{t('Status')}</th>
                                    <th className="px-3 py-2 text-start">{t('action')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {list.map((h) => (
                                    <tr key={h.id}>
                                        <td className="px-3 py-2">#{h.id}</td>
                                        <td className="px-3 py-2">{h.worker?.name || '—'}</td>
                                        <td className="px-3 py-2">{h.project?.name || '—'}</td>
                                        <td className="px-3 py-2 tabular-nums">{amountIqd(h)}</td>
                                        <td className="px-3 py-2">{h.hold_start}</td>
                                        <td className="px-3 py-2">{h.maturity_date}</td>
                                        <td className="px-3 py-2"><StatusBadge status={h.status} /></td>
                                        <td className="px-3 py-2">
                                            {canRetention && h.status === 'matured' ? (
                                                <PrimaryButton
                                                    type="button"
                                                    onClick={() => router.post(route('retention-holds.release', h.id))}
                                                >
                                                    {t('release')}
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
                                            {t('no_insurance_holds')}
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
