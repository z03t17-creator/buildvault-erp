import DataPanel from '@/Components/DataPanel';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import StockTabs from '@/Components/StockTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function Edit({ category }) {
    const t = useTranslations();
    const { data, setData, put, processing, errors } = useForm({
        name: category.name || '',
        notes: category.notes || '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('edit_stock_category')}
                    subtitle={t('stock_categories_form_hint')}
                    icon={<NavIcon name="stockCategories" className="text-lg" />}
                    actions={
                        <Link href={route('stock.categories.index')}>
                            <SecondaryButton type="button">{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('edit_stock_category')} />
            <PageShell narrow className="!space-y-6">
                <StockTabs />
                <p className="rounded-xl border border-rose-200/70 bg-rose-50/70 px-4 py-3 text-sm text-rose-950 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-100">
                    {t('stock_categories_linked_hint', {
                        count: category.stock_items_count ?? 0,
                    })}
                </p>
                <DataPanel>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            put(route('stock.categories.update', category.id));
                        }}
                        className="space-y-5"
                    >
                        <FormSection cols={1}>
                            <FormField>
                                <InputLabel value={t('name')} />
                                <TextInput
                                    className={fieldClass}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('notes')} />
                                <textarea
                                    className={fieldClass + ' py-2'}
                                    rows={3}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                />
                                <InputError message={errors.notes} className="mt-1" />
                            </FormField>
                        </FormSection>
                        <FormActions>
                            <PrimaryButton
                                disabled={processing}
                                className="!bg-rose-600 hover:!bg-rose-500"
                            >
                                {t('update')}
                            </PrimaryButton>
                        </FormActions>
                    </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
