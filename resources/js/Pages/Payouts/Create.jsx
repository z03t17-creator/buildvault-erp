import DataPanel from '@/Components/DataPanel';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import MoneyAmount from '@/Components/MoneyAmount';
import MoneyInput from '@/Components/MoneyInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Create({ projects, workers, categories, currencies, payrollSuggestions, availableCash }) {
    const t = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        project_id: '',
        worker_id: '',
        category: 'payroll',
        currency: 'USD',
        amount: '',
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
        if (data.category === 'payroll' && suggestion && (data.amount === '' || data.amount === null)) {
            setData('amount', String(suggestion.net_pay_usd));
            setData('currency', 'USD');
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
            <PageShell narrow>
                <DataPanel>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(route('payouts.store'));
                        }}
                        className="space-y-5"
                    >
                        <FormSection>
                            <FormField>
                                <InputLabel value={t('project')} />
                                <select className={selectClass} value={data.project_id} onChange={(e) => setData('project_id', e.target.value)} required>
                                    <option value="">—</option>
                                    {(projects || []).map((p) => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                                <InputError message={errors.project_id} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('category') || 'Category'} />
                                <select className={selectClass} value={data.category} onChange={(e) => setData('category', e.target.value)}>
                                    {(categories || []).map((c) => (
                                        <option key={c} value={c}>{c}</option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField>
                                <InputLabel value={t('worker')} />
                                <select className={selectClass} value={data.worker_id} onChange={(e) => setData('worker_id', e.target.value)}>
                                    <option value="">—</option>
                                    {(workers || []).map((w) => (
                                        <option key={w.id} value={w.id}>{w.name}</option>
                                    ))}
                                </select>
                            </FormField>

                            {data.category === 'payroll' && suggestion && (
                                <div className="rounded-md border border-amber-200/80 bg-amber-50/80 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                                    <p className="font-medium">{t('payroll_net_formula')}</p>
                                    <ul className="mt-2 space-y-1 text-xs">
                                        <li>{t('col_insurance_holdback')}: {Number(suggestion.insurance_holdback_pct).toFixed(0)}% · {suggestion.insurance_holdback_usd} USD</li>
                                        <li className="font-semibold">{t('col_net')}: {suggestion.net_pay_usd} USD</li>
                                    </ul>
                                    <SecondaryButton
                                        type="button"
                                        className="mt-3"
                                        onClick={() => {
                                            setData('amount', String(suggestion.net_pay_usd));
                                            setData('currency', 'USD');
                                        }}
                                    >
                                        {t('use_payroll_net')}
                                    </SecondaryButton>
                                </div>
                            )}

                            <FormField>
                                <InputLabel value={t('currency')} />
                                <select className={selectClass} value={data.currency} onChange={(e) => setData('currency', e.target.value)}>
                                    {(currencies || ['USD', 'IQD']).map((c) => (
                                        <option key={c} value={c}>{c}</option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField>
                                <InputLabel value={t('amount')} />
                                <MoneyInput allowDecimals className="mt-1 block w-full" value={data.amount} onValueChange={(raw) => setData('amount', raw)} required />
                                <InputError message={errors.amount} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('notes')} />
                                <textarea className={selectClass} rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            </FormField>
                        </FormSection>
                        <FormActions>
                            <PrimaryButton disabled={processing}>{t('create_pending') || 'Create pending'}</PrimaryButton>
                        </FormActions>
                    </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
