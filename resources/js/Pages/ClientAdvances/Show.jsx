import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';

function roundMoney(n) {
    return Math.round((Number(n) || 0) * 100) / 100;
}

function dateOnly(value) {
    if (!value) {
        return null;
    }
    return String(value).slice(0, 10);
}

function MoneyStat({ label, value, currency, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900 dark:text-white',
        locked: 'text-amber-800 dark:text-amber-200',
        free: 'text-emerald-700 dark:text-emerald-300',
        muted: 'text-slate-400',
    };

    return (
        <div className="bv-card px-4 py-3.5">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div
                dir="ltr"
                className={
                    'mt-1.5 font-sans text-2xl font-semibold tracking-normal tabular-nums sm:text-3xl ' +
                    tones[tone]
                }
            >
                {value == null || value === '' ? (
                    '—'
                ) : (
                    <MoneyAmount
                        value={value}
                        label={currency}
                        size="xl"
                        showLabel={false}
                        accent={tone === 'free'}
                    />
                )}
            </div>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{currency}</p>
        </div>
    );
}

function Meta({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                {label}
            </dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                {children}
            </dd>
        </div>
    );
}

function HoldStatusChip({ status, t }) {
    const map = {
        holding: {
            label: t('client_retention_locked'),
            className:
                'bg-emerald-500/15 text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-300',
        },
        matured: {
            label: t('status_matured'),
            className: 'bg-amber-500/15 text-amber-900 dark:bg-amber-500/20 dark:text-amber-200',
        },
        released: {
            label: t('status_released'),
            className: 'bg-slate-500/15 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300',
        },
    };
    const style = map[status] || map.holding;

    return (
        <span
            className={
                'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold ' +
                style.className
            }
        >
            <NavIcon name="insurance" className="text-xs" />
            {style.label}
        </span>
    );
}

export default function Show({ advance }) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const canDelete = useCan('clientAdvances.create');

    const usdAmt = Number(advance.amount_usd) || 0;
    const iqdAmt = Number(advance.amount_iqd) || 0;
    const currency = advance.currency === 'IQD' || (iqdAmt > 0 && usdAmt <= 0) ? 'IQD' : 'USD';
    const currencyLabel = currency === 'IQD' ? iqd : usd;
    const gross = currency === 'IQD' ? iqdAmt : usdAmt;

    const holds = advance.retention_holds || [];
    const locked = holds.reduce(
        (sum, h) => sum + (Number(currency === 'IQD' ? h.amount_iqd : h.amount_usd) || 0),
        0,
    );
    const available = roundMoney(Math.max(0, gross - locked));
    const hasLock = holds.length > 0;

    const receivedOn = dateOnly(advance.received_on);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={advance.client_name}
                    subtitle={t('client_show_hint')}
                    icon={<NavIcon name="clientAdvances" className="text-lg" />}
                    actions={
                        <>
                            <Link href={route('client-advances.index')}>
                                <SecondaryButton type="button">
                                    <NavIcon name="clientAdvances" className="text-sm" />
                                    {t('client_advances')}
                                </SecondaryButton>
                            </Link>
                            {canDelete && (
                                <SecondaryButton
                                    type="button"
                                    className="!border-rose-300 !text-rose-700 hover:!bg-rose-50 dark:!border-rose-800 dark:!text-rose-300 dark:hover:!bg-rose-950/40"
                                    onClick={() => {
                                        if (confirm(t('confirm_soft_delete'))) {
                                            router.delete(
                                                route('client-advances.destroy', advance.id),
                                            );
                                        }
                                    }}
                                >
                                    <NavIcon name="trash" className="text-sm" />
                                    {t('delete')}
                                </SecondaryButton>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={advance.client_name} />
            <PageShell narrow className="!space-y-6">
                <section>
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white dark:bg-emerald-500 dark:text-slate-950">
                            <NavIcon name="moneyIn" className="text-lg" />
                        </span>
                        <div>
                            <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                {t('client_money_in')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('client_show_money_hint', { currency: currencyLabel })}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <MoneyStat
                            label={t('client_form_preview_gross')}
                            value={gross}
                            currency={currencyLabel}
                            tone="default"
                        />
                        <MoneyStat
                            label={
                                hasLock
                                    ? t('client_form_preview_locked')
                                    : t('client_form_preview_no_lock')
                            }
                            value={locked}
                            currency={currencyLabel}
                            tone={hasLock ? 'locked' : 'muted'}
                        />
                        <MoneyStat
                            label={
                                hasLock
                                    ? t('client_form_preview_available')
                                    : t('client_form_preview_full_available')
                            }
                            value={available}
                            currency={currencyLabel}
                            tone="free"
                        />
                    </div>
                    {(usdAmt > 0 || iqdAmt > 0) && (
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            <div className="bv-card flex items-center justify-between px-4 py-3">
                                <span className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {usd}
                                </span>
                                <span dir="ltr" className="font-sans tabular-nums text-emerald-700 dark:text-emerald-300">
                                    {usdAmt > 0 ? (
                                        <MoneyAmount
                                            value={usdAmt}
                                            label={usd}
                                            size="md"
                                            showLabel={false}
                                        />
                                    ) : (
                                        <span className="text-slate-300">—</span>
                                    )}
                                </span>
                            </div>
                            <div className="bv-card flex items-center justify-between px-4 py-3">
                                <span className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {iqd}
                                </span>
                                <span dir="ltr" className="font-sans tabular-nums text-emerald-700 dark:text-emerald-300">
                                    {iqdAmt > 0 ? (
                                        <MoneyAmount
                                            value={iqdAmt}
                                            label={iqd}
                                            size="md"
                                            showLabel={false}
                                        />
                                    ) : (
                                        <span className="text-slate-300">—</span>
                                    )}
                                </span>
                            </div>
                        </div>
                    )}
                </section>

                <section className="bv-card p-5 sm:p-6">
                    <div className="mb-4 flex items-center gap-3 border-b border-slate-200/80 pb-4 dark:border-slate-800">
                        <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600/10 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                            <NavIcon name="clientAdvances" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('client_show_details')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('client_show_details_hint')}
                            </p>
                        </div>
                    </div>
                    <dl className="grid gap-6 sm:grid-cols-2">
                        <Meta label={t('client_name')}>{advance.client_name}</Meta>
                        <Meta label={t('project')}>{advance.project?.name || '—'}</Meta>
                        <Meta label={t('date')}>
                            <span className="inline-flex items-center gap-2 font-sans tabular-nums">
                                <NavIcon name="calendar" className="text-sm text-slate-400" />
                                {receivedOn || '—'}
                            </span>
                        </Meta>
                        <Meta label={t('currency')}>{currencyLabel}</Meta>
                        <Meta label={t('reference')}>{advance.reference || '—'}</Meta>
                        <Meta label={t('entered_by')}>
                            {advance.entered_by?.name || '—'}
                        </Meta>
                        {advance.notes && (
                            <div className="sm:col-span-2">
                                <Meta label={t('notes')}>{advance.notes}</Meta>
                            </div>
                        )}
                        {advance.transaction && (
                            <div className="sm:col-span-2">
                                <Meta label={t('client_show_vault_link')}>
                                    <Link
                                        href={route('vault.transactions')}
                                        className="inline-flex items-center gap-1.5 font-medium text-emerald-800 underline-offset-2 hover:underline dark:text-emerald-300"
                                    >
                                        <NavIcon name="vault" className="text-sm" />
                                        {t('money_in')} · #{advance.transaction.id}
                                    </Link>
                                </Meta>
                            </div>
                        )}
                    </dl>
                </section>

                <section className="bv-card p-5 sm:p-6">
                    <div className="mb-4 flex items-center gap-3 border-b border-slate-200/80 pb-4 dark:border-slate-800">
                        <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/15 text-amber-800 dark:text-amber-200">
                            <NavIcon name="insurance" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('client_retention_col')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('client_show_retention_hint')}
                            </p>
                        </div>
                    </div>

                    {holds.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center dark:border-slate-700">
                            <span className="mx-auto mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                                <NavIcon name="insurance" className="text-base" />
                            </span>
                            <p className="text-sm font-medium text-slate-700 dark:text-slate-200">
                                {t('client_show_no_retention')}
                            </p>
                            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {t('client_show_no_retention_hint')}
                            </p>
                        </div>
                    ) : (
                        <ul className="space-y-3">
                            {holds.map((h) => {
                                const holdUsd = Number(h.amount_usd) || 0;
                                const holdIqd = Number(h.amount_iqd) || 0;
                                const holdAmt = holdUsd > 0 ? holdUsd : holdIqd;
                                const holdCur = holdUsd > 0 ? usd : iqd;

                                return (
                                    <li
                                        key={h.id}
                                        className="flex flex-col gap-3 rounded-xl border border-slate-200/80 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                                    >
                                        <div className="flex items-start gap-3">
                                            <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/15 text-amber-800 dark:text-amber-200">
                                                <NavIcon name="insurance" className="text-sm" />
                                            </span>
                                            <div>
                                                <HoldStatusChip status={h.status} t={t} />
                                                <p className="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                                                    {t('client_show_hold_dates', {
                                                        start: dateOnly(h.hold_start) || '—',
                                                        end: dateOnly(h.maturity_date) || '—',
                                                    })}
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            dir="ltr"
                                            className="font-sans text-lg font-semibold tabular-nums text-amber-800 dark:text-amber-200 sm:text-end"
                                        >
                                            <MoneyAmount
                                                value={holdAmt}
                                                label={holdCur}
                                                size="lg"
                                                showLabel={false}
                                            />
                                            <span className="ms-1 text-xs font-medium text-slate-400">
                                                {holdCur}
                                            </span>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            </PageShell>
        </AuthenticatedLayout>
    );
}
