import {
    Archive,
    ArrowLeftRight,
    Banknote,
    Boxes,
    ClipboardList,
    FileSpreadsheet,
    Grid3x3,
    LayoutDashboard,
    Lock,
    Receipt,
    Scale,
    Shield,
    Users,
    Wallet,
} from 'lucide-react';

const NAV_ICON_MAP = {
    dashboard: LayoutDashboard,
    vault: Wallet,
    payroll: Banknote,
    settlements: Scale,
    projects: ClipboardList,
    workers: Users,
    clientAdvances: ArrowLeftRight,
    stock: Boxes,
    payouts: Receipt,
    expenses: Receipt,
    penalties: Shield,
    advances: Banknote,
    productions: Grid3x3,
    spatial: Grid3x3,
    attendance: ClipboardList,
    insurance: Lock,
    docs: FileSpreadsheet,
    imports: FileSpreadsheet,
    reports: FileSpreadsheet,
    backups: Archive,
    users: Users,
    audit: Shield,
};

export function NavIcon({ name, className = 'h-4 w-4 shrink-0 opacity-80' }) {
    const Icon = NAV_ICON_MAP[name] || LayoutDashboard;
    return <Icon className={className} aria-hidden />;
}
