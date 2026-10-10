import DateInput from '@/Components/DateInput';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import { stockFieldClass, stockMoneyClass, stockSegmentClass } from '@/Components/StockDesk';
import SuggestionCombobox from '@/Components/SuggestionCombobox';
import TextInput from '@/Components/TextInput';
import StockTabs from '@/Components/StockTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, useForm } from '@inertiajs/react';

export default function Create({
    items = [],
    projects = [],
    staff = [],
    siteKinds = ['villa', 'building'],
    placeSuggestions = [],
    defaults = {},
}) {
    const t = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        stock_item_id: '',
        quantity: '',
        moved_on: defaults.moved_on || new Date().toISOString().slice(0, 10),
        project_id: '',
        site_kind: '',
        block: '',
        zone: '',
        floor_label: '',
        apartment_number: '',
        villa_number: '',
        staff_id: '',
        receiver: '',
        issuer: defaults.issuer || '',
        reference: '',
        notes: '',
    });

    const selected = items.find((i) => String(i.id) === String(data.stock_item_id));

    const samePlace = (left, right) =>
        String(left ?? '')
            .trim()
            .toLocaleLowerCase() ===
        String(right ?? '')
            .trim()
            .toLocaleLowerCase();

    const inProject = (row) => {
        if (!data.project_id) return true;
        if (row.project_id == null || row.project_id === '') return true;
        return String(row.project_id) === String(data.project_id);
    };

    const placeOptions = (rows, key, predicate) => {
        const seen = new Set();
        const out = [];
        for (const row of rows) {
            if (!predicate(row)) continue;
            const value = String(row[key] ?? '').trim();
            if (!value) continue;
            const id = value.toLocaleLowerCase();
            if (seen.has(id)) continue;
            seen.add(id);
            out.push(value);
        }
        return out;
    };

    const buildingRows = placeSuggestions.filter(
        (row) => row.site_kind === 'building' && inProject(row),
    );
    const villaRows = placeSuggestions.filter((row) => row.site_kind === 'villa' && inProject(row));
    const blockChosen = (row) => !data.block || samePlace(row.block, data.block);
    const zoneChosen = (row) => !data.zone || samePlace(row.zone, data.zone);
    const floorChosen = (row) => !data.floor_label || samePlace(row.floor, data.floor_label);
    const villaChosen = (row) => !data.villa_number || samePlace(row.villa_number, data.villa_number);

    const blockSuggestions = placeOptions(buildingRows, 'block', () => true);
    const buildingZoneSuggestions = placeOptions(buildingRows, 'zone', blockChosen);
    const floorSuggestions = placeOptions(
        buildingRows,
        'floor',
        (row) => blockChosen(row) && zoneChosen(row),
    );
    const apartmentSuggestions = placeOptions(
        buildingRows,
        'apartment_number',
        (row) => blockChosen(row) && zoneChosen(row) && floorChosen(row),
    );
    const villaSuggestions = placeOptions(villaRows, 'villa_number', () => true);
    const villaZoneSuggestions = placeOptions(villaRows, 'zone', villaChosen);

    const setPlace = (key, value) => {
        const next = { ...data, [key]: value };
        const clear = (keys) => keys.forEach((field) => {
            next[field] = '';
        });
        if (key === 'project_id') {
            clear(['block', 'zone', 'floor_label', 'apartment_number', 'villa_number']);
        }
        if (key === 'site_kind') {
            clear(['zone']);
            if (value === 'building') clear(['villa_number']);
            if (value === 'villa') clear(['block', 'floor_label', 'apartment_number']);
        }
        if (key === 'block') clear(['zone', 'floor_label', 'apartment_number']);
        if (key === 'zone' && data.site_kind === 'building') clear(['floor_label', 'apartment_number']);
        if (key === 'floor_label') clear(['apartment_number']);
        if (key === 'villa_number') clear(['zone']);
        setData(next);
    };

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('warehouse_tab_dispatch')}
                    subtitle={t('warehouse_dispatch_hint')}
                    icon={<NavIcon name="stockOut" className="text-lg text-amber-700 dark:text-amber-300" />}
                />
            }
        >
            <Head title={t('warehouse_tab_dispatch')} />
            <PageShell className="!max-w-5xl !space-y-4">
                <StockTabs />
                <form
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('stock.out.store'));
                    }}
                    className="bv-card space-y-4 p-4 sm:p-5"
                >
                    <FormSection cols={2}>
                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('product')} htmlFor="stock_item_id" />
                            <select
                                id="stock_item_id"
                                className={stockFieldClass}
                                value={data.stock_item_id}
                                onChange={(e) => setData('stock_item_id', e.target.value)}
                            >
                                <option value="">{t('warehouse_pick_item')}</option>
                                {items.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name} · {item.sku || item.barcode || '—'} · {item.quantity}{' '}
                                        {item.unit}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.stock_item_id} className="mt-1" />
                            {selected ? (
                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {t('on_hand')}: {selected.quantity} {selected.unit}
                                </p>
                            ) : null}
                        </FormField>
                        <FormField>
                            <InputLabel value={t('quantity')} htmlFor="quantity" />
                            <MoneyInput
                                id="quantity"
                                className={stockMoneyClass}
                                value={data.quantity}
                                onValueChange={(next) => setData('quantity', next)}
                                allowDecimals
                            />
                            <InputError message={errors.quantity} className="mt-1" />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('date')} htmlFor="moved_on" />
                            <DateInput
                                id="moved_on"
                                className={stockFieldClass}
                                value={data.moved_on}
                                onChange={(e) => setData('moved_on', e.target.value)}
                            />
                        </FormField>
                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('project')} htmlFor="project_id" />
                            <select
                                id="project_id"
                                className={stockFieldClass}
                                value={data.project_id}
                                onChange={(e) => setPlace('project_id', e.target.value)}
                            >
                                <option value="">{t('warehouse_pick_project')}</option>
                                {projects.map((row) => (
                                    <option key={row.id} value={row.id}>
                                        {row.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.project_id} className="mt-1" />
                        </FormField>
                    </FormSection>

                    <div className="space-y-3 rounded-xl border border-slate-700 bg-slate-950/40 p-3">
                        <p className="text-sm font-semibold text-slate-900 dark:text-slate-100">{t('warehouse_place')}</p>
                        <div className="flex flex-wrap gap-2">
                            {siteKinds.map((kind) => (
                                <button
                                    key={kind}
                                    type="button"
                                    className={stockSegmentClass(data.site_kind === kind, 'amber')}
                                    onClick={() => setPlace('site_kind', kind)}
                                >
                                    {t(`warehouse_site_${kind}`)}
                                </button>
                            ))}
                        </div>
                        {data.site_kind === 'building' ? (
                            <FormSection cols={2}>
                                <FormField>
                                    <InputLabel value={t('warehouse_block')} />
                                    <SuggestionCombobox
                                        className={stockFieldClass}
                                        value={data.block}
                                        onChange={(next) => setPlace('block', next)}
                                        suggestions={blockSuggestions}
                                    />
                                </FormField>
                                <FormField>
                                    <InputLabel value={t('warehouse_zone')} />
                                    <SuggestionCombobox
                                        className={stockFieldClass}
                                        value={data.zone}
                                        onChange={(next) => setPlace('zone', next)}
                                        suggestions={buildingZoneSuggestions}
                                    />
                                </FormField>
                                <FormField>
                                    <InputLabel value={t('warehouse_floor')} />
                                    <SuggestionCombobox
                                        className={stockFieldClass}
                                        value={data.floor_label}
                                        onChange={(next) => setPlace('floor_label', next)}
                                        suggestions={floorSuggestions}
                                    />
                                </FormField>
                                <FormField>
                                    <InputLabel value={t('warehouse_apartment')} />
                                    <SuggestionCombobox
                                        className={stockFieldClass}
                                        value={data.apartment_number}
                                        onChange={(next) => setPlace('apartment_number', next)}
                                        suggestions={apartmentSuggestions}
                                    />
                                </FormField>
                            </FormSection>
                        ) : null}
                        {data.site_kind === 'villa' ? (
                            <FormSection cols={2}>
                                <FormField>
                                    <InputLabel value={t('warehouse_villa')} />
                                    <SuggestionCombobox
                                        className={stockFieldClass}
                                        value={data.villa_number}
                                        onChange={(next) => setPlace('villa_number', next)}
                                        suggestions={villaSuggestions}
                                    />
                                </FormField>
                                <FormField>
                                    <InputLabel value={t('warehouse_zone')} />
                                    <SuggestionCombobox
                                        className={stockFieldClass}
                                        value={data.zone}
                                        onChange={(next) => setPlace('zone', next)}
                                        suggestions={villaZoneSuggestions}
                                    />
                                </FormField>
                            </FormSection>
                        ) : null}
                    </div>

                    <FormSection cols={2}>
                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('warehouse_receiver')} htmlFor="staff_id" />
                            <select
                                id="staff_id"
                                className={stockFieldClass}
                                value={data.staff_id}
                                onChange={(e) => {
                                    const id = e.target.value;
                                    const person = staff.find((s) => String(s.id) === String(id));
                                    setData({
                                        ...data,
                                        staff_id: id,
                                        receiver: person?.name || '',
                                    });
                                }}
                            >
                                <option value="">{t('warehouse_pick_staff')}</option>
                                {staff.map((person) => (
                                    <option key={person.id} value={person.id}>
                                        {person.name}
                                        {person.role || person.trade
                                            ? ` — ${person.role || person.trade}`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('note')} htmlFor="notes" />
                            <TextInput
                                id="notes"
                                className={stockFieldClass}
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                            />
                        </FormField>
                    </FormSection>

                    <FormActions>
                        <PrimaryButton
                            disabled={processing}
                            className="min-h-[3rem] min-w-[12rem] !bg-amber-600 hover:!bg-amber-500"
                        >
                            {t('warehouse_dispatch_save')}
                        </PrimaryButton>
                    </FormActions>
                </form>
            </PageShell>
        </AuthenticatedLayout>
    );
}
