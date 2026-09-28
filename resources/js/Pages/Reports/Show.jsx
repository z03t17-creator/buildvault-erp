import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import InputLabel from '@/Components/InputLabel';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const selectClass =
    'mt-1.5 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 print:border-slate-400';

function buildQuery(filters) {
    const params = new URLSearchParams();
    Object.entries(filters || {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });
    const qs = params.toString();
    return qs ? `?${qs}` : '';
}

export default function Show({ report, meta, filterOptions, exportUrls }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const needed = meta?.filters || [];
    const [filters, setFilters] = useState({
        from: report.filters?.from || '',
        to: report.filters?.to || '',
        month: report.filters?.month || '',
        project_id: report.filters?.project_id ? String(report.filters.project_id) : '',
        worker_id: report.filters?.worker_id ? String(report.filters.worker_id) : '',
    });

    const applyFilters = (event) => {
        event.preventDefault();
        const payload = {};
        needed.forEach((key) => {
            if (filters[key] !== '' && filters[key] !== null && filters[key] !== undefined) {
                payload[key] = filters[key];
            }
        });
        router.get(route('reports.show', report.type), payload, {
            preserveState: true,
            replace: true,
        });
    };

    const qs = buildQuery(
        Object.fromEntries(needed.map((key) => [key, filters[key]]).filter(([, v]) => v !== '')),
    );

    const labelFor = (key) => {
        const translated = t(key);
        return translated === key ? key.replaceAll('_', ' ') : translated;
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t(report.title_key)}
                    subtitle={t(`report_${report.type}_hint`)}
                    actions={
                        <div className="flex flex-wrap gap-2 print:hidden">
                            <Link href={route('reports.index')}>
                                <SecondaryButton type="button">{t('all_reports')}</SecondaryButton>
                            </Link>
                            <a href={`${exportUrls.pdf}${qs}`}>
                                <SecondaryButton type="button">{t('download_pdf')}</SecondaryButton>
                            </a>
                            <a href={`${exportUrls.xlsx}${qs}`}>
                                <SecondaryButton type="button">{t('download_excel')}</SecondaryButton>
                            </a>
                            <a href={`${exportUrls.csv}${qs}`}>
                                <SecondaryButton type="button">{t('download_csv')}</SecondaryButton>
                            </a>
                            <PrimaryButton type="button" onClick={() => window.print()}>
                                {t('print')}
                            </PrimaryButton>
                        </div>
                    }
                />
            }
        >
            <Head title={t(report.title_key)} />

            <PageShell>
                {needed.length > 0 && (
                    <DataPanel title={t('filters')} className="print:hidden">
                        <form onSubmit={applyFilters} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            {needed.includes('month') && (
                                <div>
                                    <InputLabel value={t('month')} />
                                    <input
                                        type="month"
                                        className={selectClass}
                                        value={filters.month}
                                        onChange={(e) =>
                                            setFilters((prev) => ({ ...prev, month: e.target.value }))
                                        }
                                    />
                                </div>
                            )}
                            {needed.includes('from') && (
                                <div>
                                    <InputLabel value={t('from_date')} />
                                    <input
                                        type="date"
                                        className={selectClass}
                                        value={filters.from}
                                        onChange={(e) =>
                                            setFilters((prev) => ({ ...prev, from: e.target.value }))
                                        }
                                    />
                                </div>
                            )}
                            {needed.includes('to') && (
                                <div>
                                    <InputLabel value={t('to_date')} />
                                    <input
                                        type="date"
                                        className={selectClass}
                                        value={filters.to}
                                        onChange={(e) =>
                                            setFilters((prev) => ({ ...prev, to: e.target.value }))
                                        }
                                    />
                                </div>
                            )}
                            {needed.includes('project_id') && (
                                <div>
                                    <InputLabel value={t('project')} />
                                    <select
                                        className={selectClass}
                                        value={filters.project_id}
                                        onChange={(e) =>
                                            setFilters((prev) => ({
                                                ...prev,
                                                project_id: e.target.value,
                                            }))
                                        }
                                    >
                                        <option value="">{t('all_projects')}</option>
                                        {(filterOptions?.projects || []).map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            {needed.includes('worker_id') && (
                                <div>
                                    <InputLabel value={t('employee')} />
                                    <select
                                        className={selectClass}
                                        value={filters.worker_id}
                                        onChange={(e) =>
                                            setFilters((prev) => ({
                                                ...prev,
                                                worker_id: e.target.value,
                                            }))
                                        }
                                    >
                                        <option value="">{t('all_employees')}</option>
                                        {(filterOptions?.workers || []).map((w) => (
                                            <option key={w.id} value={w.id}>
                                                {w.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            <div className="flex items-end">
                                <PrimaryButton type="submit">{t('apply_filters')}</PrimaryButton>
                            </div>
                        </form>
                    </DataPanel>
                )}

                {report.summary?.length > 0 && (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {report.summary.map((line) => (
                            <div
                                key={`${line.label_key}-${line.value}`}
                                className="rounded-xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-slate-700/80 dark:bg-slate-950/60"
                            >
                                <p className="text-xs font-medium uppercase tracking-wider text-slate-500">
                                    {labelFor(line.label_key)}
                                </p>
                                {line.money ? (
                                    <div className="mt-1">
                                        <MoneyAmount value={line.value} label={iqd} size="md" />
                                    </div>
                                ) : (
                                    <p className="mt-1 text-xl font-semibold tabular-nums text-slate-900 dark:text-white">
                                        {line.value}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                <DataPanel
                    title={t(report.title_key)}
                    subtitle={
                        report.filters?.from
                            ? `${report.filters.from} → ${report.filters.to || ''}`
                            : report.filters?.month || undefined
                    }
                    padded={false}
                >
                    {report.empty ? (
                        <EmptyState
                            title={t('no_report_data')}
                            description={t('no_report_data_hint')}
                        />
                    ) : (
                        <DataTable minWidth="48rem">
                            <thead>
                                <tr>
                                    {report.columns.map((col) => (
                                        <Th key={col.key} align={col.align || 'start'}>
                                            {labelFor(col.label_key)}
                                        </Th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {report.rows.map((row, idx) => (
                                    <tr key={idx}>
                                        {report.columns.map((col) => {
                                            const value = row[col.key];
                                            if (col.money) {
                                                return (
                                                    <Td key={col.key} align="end">
                                                        <MoneyAmount
                                                            value={value}
                                                            label={iqd}
                                                            size="sm"
                                                            showLabel={false}
                                                        />
                                                    </Td>
                                                );
                                            }
                                            return (
                                                <Td
                                                    key={col.key}
                                                    align={col.align || 'start'}
                                                    muted={col.align !== 'end'}
                                                >
                                                    {value === null || value === undefined || value === ''
                                                        ? '—'
                                                        : typeof value === 'string' &&
                                                            [
                                                                'status',
                                                                'type',
                                                                'category',
                                                                'line',
                                                                'kind',
                                                                'direction',
                                                                'unit_type',
                                                                'role',
                                                            ].includes(col.key)
                                                          ? labelFor(value)
                                                          : String(value)}
                                                </Td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
