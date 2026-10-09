import SidebarNavLink from '@/Components/SidebarNavLink';
import { NavIcon } from '@/lib/navIcons';
import { useEffect, useId, useState } from 'react';

/**
 * Collapsible sidebar hub — one parent row, nested links underneath.
 * Auto-expands when a child route is active; otherwise remembers last toggle.
 */
export default function SidebarNavHub({
    id,
    label,
    icon = 'dataRecords',
    childrenItems = [],
    onNavigate,
}) {
    const panelId = useId();
    const childActive = childrenItems.some((item) => item.active);
    const [open, setOpen] = useState(childActive);

    useEffect(() => {
        if (childActive) {
            setOpen(true);
        }
    }, [childActive]);

    if (!childrenItems.length) {
        return null;
    }

    return (
        <div className="bv-nav-hub">
            <button
                type="button"
                id={`${id}-trigger`}
                aria-expanded={open}
                aria-controls={panelId}
                title={label}
                onClick={() => setOpen((v) => !v)}
                className={
                    'bv-nav-item group w-full ' +
                    (childActive ? 'bv-nav-item-active' : 'bv-nav-item-idle')
                }
            >
                <span
                    className={
                        'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[0.95rem] transition ' +
                        (childActive
                            ? 'bg-teal-600 text-white dark:bg-teal-500 dark:text-slate-950'
                            : 'bg-slate-100 text-slate-500 group-hover:bg-teal-50 group-hover:text-teal-700 dark:bg-slate-800 dark:text-slate-400 dark:group-hover:bg-teal-500/10 dark:group-hover:text-teal-300')
                    }
                >
                    <NavIcon name={icon} solid={childActive} />
                </span>
                <span className="min-w-0 flex-1 truncate text-start">{label}</span>
                <NavIcon
                    name="chevronRight"
                    className={
                        'text-xs opacity-60 transition-transform duration-200 rtl:rotate-180 ' +
                        (open ? 'ltr:rotate-90 rtl:-rotate-90' : '')
                    }
                />
            </button>

            <div
                id={panelId}
                role="region"
                aria-labelledby={`${id}-trigger`}
                className={'bv-nav-hub-panel ' + (open ? 'bv-nav-hub-panel-open' : '')}
            >
                <div className="bv-nav-hub-panel-inner space-y-1 ps-3 pt-1">
                    {childrenItems.map((item) => (
                        <SidebarNavLink
                            key={item.key}
                            href={item.href}
                            active={item.active}
                            badge={item.badge}
                            icon={item.key}
                            title={item.hint || item.label}
                            onClick={onNavigate}
                            className="bv-nav-hub-child"
                        >
                            {item.label}
                        </SidebarNavLink>
                    ))}
                </div>
            </div>
        </div>
    );
}
