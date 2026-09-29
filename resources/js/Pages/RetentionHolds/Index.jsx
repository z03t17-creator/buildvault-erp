import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { formatIqd } from '@/lib/numberFormat';
import { Head, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ holds, matured, settings, exchangeRate }) {
    const t = useTranslations();
    const page = usePage();
    const canRetention = useCan('vault.retentionManage');
    const canViewRetention = useCan('vault.retention');
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

    const amountValue = (h) =>
        h.amount_iqd ?? Math.round(Number(h.amount_usd || 0) * Number(rate || 1310));
    const amountIqd = (h) => formatIqd(amountValue(h), iqd);

    if (!canViewRetention && !canRetention) {
        return null;
    }

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
            <PageShell className="!space-y-8">
                {canRetention && (
                    <DataPanel title={t('insurance_settings')} subtitle={t('insurance_settings_hint')}>
                        <form
                            className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end"
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
                    </DataPanel>
                )}

                {canRetention && ready.length > 0 && (
                    <DataPanel title={t('matured_release_heading')}>
                        <ul className="space-y-2 text-sm">
                            {ready.map((h) => (
                                <li
                                    key={h.id}
                                    className="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span>
                                        #{h.id} {h.worker?.name} · {amountIqd(h)}
                                    </span>
                                    <PrimaryButton
                                        type="button"
                                        onClick={() =>
                                            router.post(route('retention-holds.release', h.id))
                                        }
                                    >
                                        {t('release')}
                                    </PrimaryButton>
                                </li>
                            ))}
                        </ul>
                    </DataPanel>
                )}

                {list.length === 0 ? (
                    <EmptyState title={t('no_insurance_holds')} />
                ) : (
                    <DataPanel padded={false}>
                        <DataTable minWidth="64rem">
                            <thead>
                                <tr>
                                    <Th>{t('col_id')}</Th>
                                    <Th>{t('worker')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('pay_period')}</Th>
                                    <Th align="end">{t('holdback_percent')}</Th>
                                    <Th align="end">{t('amount_iqd')}</Th>
                                    <Th>{t('hold_start')}</Th>
                                    <Th>{t('matures')}</Th>
                                    <Th>{t('status')}</Th>
                                    <Th align="end">
                                        {t('released_amount')} ({iqd})
                                    </Th>
                                    <Th>{t('action')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((h) => (
                                    <tr key={h.id}>
                                        <Td muted className="tabular-nums">
                                            #{h.id}
                                        </Td>
                                        <Td>{h.worker?.name || '—'}</Td>
                                        <Td muted>{h.project?.name || '—'}</Td>
                                        <Td muted className="tabular-nums">
                                            {h.pay_period || '—'}
                                        </Td>
                                        <Td align="end" className="tabular-nums">
                                            {h.hold_pct != null
                                                ? `${Number(h.hold_pct).toFixed(0)}%`
                                                : '—'}
                                        </Td>
                                        <Td align="end">
                                            <MoneyAmount
                                                value={amountValue(h)}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                            />
                                        </Td>
                                        <Td muted>{h.hold_start}</Td>
                                        <Td muted>{h.maturity_date}</Td>
                                        <Td>
                                            <StatusBadge status={h.status} />
                                        </Td>
                                        <Td align="end">
                                            {h.released_amount_usd != null ? (
                                                <MoneyAmount
                                                    value={
                                                        h.released_amount_iqd ??
                                                        Math.round(
                                                            Number(h.released_amount_usd) *
                                                                Number(rate || 1310),
                                                        )
                                                    }
                                                    label={iqd}
                                                    size="sm"
                                                    showLabel={false}
                                                />
                                            ) : (
                                                '—'
                                            )}
                                        </Td>
                                        <Td>
                                            {canRetention && h.status === 'matured' ? (
                                                <PrimaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            route('retention-holds.release', h.id),
                                                        )
                                                    }
                                                >
                                                    {t('release')}
                                                </PrimaryButton>
                                            ) : (
                                                <span className="text-slate-400">—</span>
                                            )}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
