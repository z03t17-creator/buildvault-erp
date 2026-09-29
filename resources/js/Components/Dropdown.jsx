import { Transition } from '@headlessui/react';
import { Link } from '@inertiajs/react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import { createPortal } from 'react-dom';

const DropDownContext = createContext();

const Dropdown = ({ children }) => {
    const [open, setOpen] = useState(false);
    const triggerRef = useRef(null);

    const toggleOpen = () => {
        setOpen((previousState) => !previousState);
    };

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKey = (e) => {
            if (e.key === 'Escape') {
                setOpen(false);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open]);

    return (
        <DropDownContext.Provider value={{ open, setOpen, toggleOpen, triggerRef }}>
            <div className="relative">{children}</div>
        </DropDownContext.Provider>
    );
};

const Trigger = ({ children }) => {
    const { open, toggleOpen, triggerRef } = useContext(DropDownContext);

    return (
        <div ref={triggerRef} onClick={toggleOpen} aria-expanded={open}>
            {children}
        </div>
    );
};

const Content = ({
    align = 'right',
    width = '48',
    contentClasses = 'py-1 bg-white',
    children,
}) => {
    const { open, setOpen, triggerRef } = useContext(DropDownContext);
    const [coords, setCoords] = useState({ top: 0, left: 0 });
    const [mounted, setMounted] = useState(false);

    const updateCoords = useCallback(() => {
        const el = triggerRef?.current;
        if (!el) {
            return;
        }
        const rect = el.getBoundingClientRect();
        const menuWidth = width === '48' ? 192 : 192;
        const isRtl = document.documentElement.dir === 'rtl';
        let left;
        if (align === 'left') {
            left = isRtl ? rect.right - menuWidth : rect.left;
        } else {
            left = isRtl ? rect.left : rect.right - menuWidth;
        }
        left = Math.min(Math.max(8, left), window.innerWidth - menuWidth - 8);
        setCoords({
            top: rect.bottom + 8,
            left,
        });
    }, [triggerRef, align, width]);

    useEffect(() => {
        setMounted(true);
    }, []);

    useLayoutEffect(() => {
        if (!open) {
            return undefined;
        }
        updateCoords();
        window.addEventListener('resize', updateCoords);
        window.addEventListener('scroll', updateCoords, true);
        return () => {
            window.removeEventListener('resize', updateCoords);
            window.removeEventListener('scroll', updateCoords, true);
        };
    }, [open, updateCoords]);

    const widthClasses = width === '48' ? 'w-48' : '';

    const style = { top: coords.top, left: coords.left };

    if (!mounted) {
        return null;
    }

    return createPortal(
        <Transition
            show={open}
            enter="transition ease-out duration-150"
            enterFrom="opacity-0 scale-95"
            enterTo="opacity-100 scale-100"
            leave="transition ease-in duration-100"
            leaveFrom="opacity-100 scale-100"
            leaveTo="opacity-0 scale-95"
        >
            <div className="fixed inset-0 z-[80]" role="presentation">
                <div
                    className="absolute inset-0"
                    onClick={() => setOpen(false)}
                    aria-hidden
                />
                <div
                    className={`absolute z-[81] rounded-xl shadow-lg ring-1 ring-black/10 dark:ring-white/10 ${widthClasses}`}
                    style={style}
                    onClick={() => setOpen(false)}
                    role="menu"
                >
                    <div
                        className={
                            `rounded-xl bg-white dark:bg-slate-900 ` + contentClasses
                        }
                    >
                        {children}
                    </div>
                </div>
            </div>
        </Transition>,
        document.body
    );
};

const DropdownLink = ({ className = '', children, ...props }) => {
    return (
        <Link
            {...props}
            className={
                'block w-full px-4 py-2.5 text-start text-sm font-medium leading-5 text-slate-700 transition duration-150 ease-in-out hover:bg-slate-100 focus:bg-slate-100 focus:outline-none dark:text-slate-200 dark:hover:bg-slate-800 ' +
                className
            }
        >
            {children}
        </Link>
    );
};

Dropdown.Trigger = Trigger;
Dropdown.Content = Content;
Dropdown.Link = DropdownLink;

export default Dropdown;
