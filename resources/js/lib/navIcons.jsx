/**
 * Flaticon Uicons (https://www.flaticon.com/uicons) — free set with attribution.
 * Classes: fi fi-rr-* (regular rounded), fi fi-sr-* (solid rounded).
 */

const NAV_ICON_MAP = {
    dashboard: 'fi-rr-apps',
    vault: 'fi-rr-wallet',
    payroll: 'fi-rr-money-bill-wave',
    settlements: 'fi-rr-balance-scale-left',
    projects: 'fi-rr-building',
    workers: 'fi-rr-users',
    clientAdvances: 'fi-rr-exchange',
    stock: 'fi-rr-boxes',
    payouts: 'fi-rr-receipt',
    expenses: 'fi-rr-shopping-cart',
    penalties: 'fi-rr-shield',
    advances: 'fi-rr-hand-holding-usd',
    productions: 'fi-rr-cube',
    spatial: 'fi-rr-grid',
    attendance: 'fi-rr-calendar-clock',
    insurance: 'fi-rr-lock',
    docs: 'fi-rr-folder',
    imports: 'fi-rr-file-import',
    reports: 'fi-rr-chart-histogram',
    backups: 'fi-rr-database',
    users: 'fi-rr-user',
    audit: 'fi-rr-shield-check',
    more: 'fi-rr-menu-dots',
    menu: 'fi-rr-menu-burger',
    home: 'fi-rr-home',
    logout: 'fi-rr-sign-out-alt',
    stockIn: 'fi-rr-box-open',
    stockOut: 'fi-rr-box',
    stockMovements: 'fi-rr-exchange',
};

const TONE_CHIP = {
    teal: 'bv-icon-chip',
    emerald: 'bv-icon-chip',
    amber: 'bv-icon-chip bv-icon-chip-amber',
    rose: 'bv-icon-chip bv-icon-chip-rose',
    sky: 'bv-icon-chip bv-icon-chip-sky',
    slate: 'bv-icon-chip !bg-slate-100 !text-slate-600 dark:!bg-slate-800 dark:!text-slate-300',
};

export function iconClassFor(name) {
    return NAV_ICON_MAP[name] || 'fi-rr-apps';
}

export function NavIcon({ name, solid = false, className = 'text-base' }) {
    const glyph = iconClassFor(name);
    const weight = solid ? 'fi-sr' : 'fi-rr';
    const glyphClass = glyph.replace(/^fi-rr/, weight).replace(/^fi-sr/, weight);

    return (
        <i
            className={`fi ${glyphClass} ${className}`.trim()}
            aria-hidden
        />
    );
}

export function IconChip({ name, tone = 'teal', solid = false, className = '' }) {
    return (
        <span className={`${TONE_CHIP[tone] || TONE_CHIP.teal} ${className}`.trim()}>
            <NavIcon name={name} solid={solid} className="text-[1.05rem]" />
        </span>
    );
}
