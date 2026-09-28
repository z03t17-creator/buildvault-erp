import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import MoneyAmount from '@/Components/MoneyAmount';
import MoneyInput from '@/Components/MoneyInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Create({ projects, workers, categories, payrollSuggestions }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const { data, setData, post, processing, errors } = useForm({
        project_id: '',
        worker_id: '',
        category: 'payroll',
        amount_usd: '',
        notes: '',
    });

    const suggestion = useMemo(() => {
        if (!data.worker_id || !payrollSuggestions) {
            return null;
        }
        return payrollSuggestions[data.worker_id] || payrollSuggestions[String(data.worker_id)] || null;
    }, [data.worker_id, payrollSuggestions]);

    useEffect(() => {
        if (!data.worker_id) {
            return;
        }
        const worker = (workers || []).find((w) => String(w.id) === String(data.worker_id));
        if (worker?.project_id && !data.project_id) {
            setData('project_id', String(worker.project_id));
        }
        if (data.category === 'payroll' && suggestion && (data.amount_usd === '' || data.amount_usd === null)) {
            setData('amount_usd', String(suggestion.net_pay_usd));
        }
    }, [data.worker_id, data.category, suggestion]);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('new_payout') || 'New payout'}
                    actions={
                        <Link href={route('payouts.index')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('new_payout') || 'New payout'} />
            <div className="py-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('payouts.store'));
                    }}
                    className="mx-auto max-w-xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                >
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
                        <InputLabel value={t('category') || 'Category'} />
                        <select className={selectClass} value={data.category} onChange={(e) => setData('category', e.target.value)}>
                            {(categories || []).map((c) => (
                                <option key={c} value={c}>{c}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel value={t('worker')} />
                        <select className={selectClass} value={data.worker_id} onChange={(e) => setData('worker_id', e.target.value)}>
                            <option value="">—</option>
                            {(workers || []).map((w) => (
                                <option key={w.id} value={w.id}>{w.name}</option>
                            ))}
                        </select>
                    </div>

                    {data.category === 'payroll' && suggestion && (
                        <div className="rounded-md border border-amber-200/80 bg-amber-50/80 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                            <p className="font-medium">{t('payroll_net_formula')}</p>
                            <ul className="mt-2 space-y-1 text-xs">
                                <li>{t('col_penalties')}: <MoneyAmount value={Math.round(Number(suggestion.penalties_usd || 0) * (suggestion.net_pay_iqd && suggestion.net_pay_usd ? suggestion.net_pay_iqd / suggestion.net_pay_usd : 1310))} label={iqd} size="sm" /></li>
                                <li>{t('col_insurance_holdback')}: {Number(suggestion.insurance_holdback_pct).toFixed(0)}% · {suggestion.insurance_holdback_usd} USD</li>
                                <li>{t('col_advances')}: <MoneyAmount value={suggestion.advances_iqd} label={iqd} size="sm" /></li>
                                <li className="font-semibold">{t('col_net')}: <MoneyAmount value={suggestion.net_pay_iqd} label={iqd} size="md" /></li>
                            </ul>
                            <SecondaryButton
                                type="button"
                                className="mt-3"
                                onClick={() => setData('amount_usd', String(suggestion.net_pay_usd))}
                            >
                                {t('use_payroll_net')}
                            </SecondaryButton>
                        </div>
                    )}

                    <div>
                        <InputLabel value={t('amount_usd') || 'Amount USD'} />
                        <MoneyInput allowDecimals className="mt-1 block w-full" value={data.amount_usd} onValueChange={(raw) => setData('amount_usd', raw)} required />
                        <InputError message={errors.amount_usd} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value={t('notes')} />
                        <textarea className={selectClass} rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>
                    <PrimaryButton disabled={processing}>{t('create_pending') || 'Create pending'}</PrimaryButton>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
