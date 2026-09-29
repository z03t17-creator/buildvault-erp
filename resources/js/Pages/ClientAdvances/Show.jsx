import DataPanel from '@/Components/DataPanel';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{children}</dd>
        </div>
    );
}

export default function Show({ advance }) {
    const t = useTranslations();
    const canDelete = useCan('clientAdvances.create');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={advance.client_name}
                    subtitle={t('client_advances')}
                    actions={
                        <>
                            <Link href={route('client-advances.index')}>
                                <SecondaryButton>{t('back')}</SecondaryButton>
                            </Link>
                            {canDelete && (
                                <SecondaryButton
                                    type="button"
                                    onClick={() => {
                                        if (confirm(t('confirm_soft_delete'))) {
                                            router.delete(route('client-advances.destroy', advance.id));
                                        }
                                    }}
                                >
                                    {t('delete')}
                                </SecondaryButton>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={advance.client_name} />
            <PageShell narrow>
                <DataPanel>
                    <dl className="grid gap-6 sm:grid-cols-2">
                        <Field label={t('project')}>{advance.project?.name || '—'}</Field>
                        <Field label={t('date')}>
                            <span className="font-mono tabular-nums">{advance.received_on}</span>
                        </Field>
                        <Field label={t('currency')}>{advance.currency}</Field>
                        <Field label={`${t('money_in')} USD`}>
                            <span className="font-mono tabular-nums">
                                <MoneyAmount value={advance.amount_usd} label="USD" size="lg" showLabel={false} />
                            </span>
                        </Field>
                        <Field label={`${t('money_in')} IQD`}>
                            <span className="font-mono tabular-nums">
                                <MoneyAmount value={advance.amount_iqd} label="IQD" size="lg" showLabel={false} />
                            </span>
                        </Field>
                        <Field label={t('reference')}>{advance.reference || '—'}</Field>
                    </dl>
                </DataPanel>
                {(advance.retention_holds || []).length > 0 && (
                    <DataPanel title={t('lock_retention')}>
                        <ul className="space-y-2">
                            {advance.retention_holds.map((h) => (
                                <li key={h.id} className="flex items-center justify-between text-sm">
                                    <StatusBadge status={h.status} />
                                    <span className="font-mono tabular-nums">
                                        USD {h.amount_usd} / IQD {h.amount_iqd} · matures {h.maturity_date}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
