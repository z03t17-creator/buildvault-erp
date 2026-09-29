import DataPanel from '@/Components/DataPanel';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyAmount from '@/Components/MoneyAmount';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function LedgerForm({
    mode = 'create',
    transaction,
    projects,
    currencies,
    available,
}) {
    const t = useTranslations();
    const editing = mode === 'edit';
    const { data, setData, post, put, processing, errors } = useForm({
        direction: transaction?.direction || 'in',
        currency: transaction?.currency || 'USD',
        amount: transaction?.amount ?? '',
        occurred_on: transaction?.occurred_on || new Date().toISOString().slice(0, 10),
        description: transaction?.description || '',
        project_id: transaction?.project_id || '',
        reference_code: transaction?.reference_code || '',
    });

    const title = editing ? t('edit_ledger_entry') : t('add_ledger_entry');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={title}
                    subtitle={t('qasa_ledger_hint')}
                    actions={
                        <Link href={route('vault.transactions')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={title} />
            <PageShell narrow>
                <div className="mb-4 grid gap-3 sm:grid-cols-2">
                    <div className="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                        <p className="text-xs uppercase tracking-wider text-slate-500">{t('available_cash')} USD</p>
                        <MoneyAmount value={available?.available_usd} label="USD" size="lg" />
                    </div>
                    <div className="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                        <p className="text-xs uppercase tracking-wider text-slate-500">{t('available_cash')} IQD</p>
                        <MoneyAmount value={available?.available_iqd} label="IQD" size="lg" />
                    </div>
                </div>

                <DataPanel>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (editing) {
                                put(route('vault.transactions.update', transaction.id));
                            } else {
                                post(route('vault.transactions.store'));
                            }
                        }}
                        className="space-y-5"
                    >
                        <FormSection>
                            <FormField>
                                <InputLabel value={t('direction')} />
                                <select
                                    className={selectClass}
                                    value={data.direction}
                                    onChange={(e) => setData('direction', e.target.value)}
                                >
                                    <option value="in">{t('money_in')}</option>
                                    <option value="out">{t('money_out')}</option>
                                </select>
                                <InputError message={errors.direction} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('currency')} />
                                <select
                                    className={selectClass}
                                    value={data.currency}
                                    onChange={(e) => setData('currency', e.target.value)}
                                >
                                    {(currencies || ['USD', 'IQD']).map((c) => (
                                        <option key={c} value={c}>
                                            {c}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.currency} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('amount')} />
                                <MoneyInput
                                    className="mt-1 block w-full"
                                    value={data.amount}
                                    onValueChange={(raw) => setData('amount', raw)}
                                    required
                                />
                                <InputError message={errors.amount} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('date')} />
                                <TextInput
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.occurred_on}
                                    onChange={(e) => setData('occurred_on', e.target.value)}
                                    required
                                />
                                <InputError message={errors.occurred_on} className="mt-1" />
                            </FormField>
                            <FormField className="sm:col-span-2">
                                <InputLabel value={t('description')} />
                                <TextInput
                                    className="mt-1 block w-full"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    required
                                />
                                <InputError message={errors.description} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('project')} />
                                <select
                                    className={selectClass}
                                    value={data.project_id}
                                    onChange={(e) => setData('project_id', e.target.value)}
                                >
                                    <option value="">—</option>
                                    {(projects || []).map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField>
                                <InputLabel value={t('reference')} />
                                <TextInput
                                    className="mt-1 block w-full"
                                    value={data.reference_code}
                                    onChange={(e) => setData('reference_code', e.target.value)}
                                />
                            </FormField>
                        </FormSection>
                        <FormActions>
                            <PrimaryButton disabled={processing}>
                                {editing ? t('save') : t('save')}
                            </PrimaryButton>
                        </FormActions>
                    </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
