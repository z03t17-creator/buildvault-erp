import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-200 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-emerald-500 text-slate-900 focus:border-emerald-600 dark:text-white'
                    : 'border-transparent text-slate-500 hover:border-emerald-300/70 hover:text-emerald-800 focus:border-slate-300 focus:text-slate-700 dark:text-slate-400 dark:hover:border-emerald-700 dark:hover:text-emerald-300') +
                className
            }
        >
            {children}
        </Link>
    );
}
