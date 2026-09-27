import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

/**
 * Bare placeholder shell for Phase 2.5 — polished UI lands in 2.6.
 */
export default function PageStub({ title, children, links = [] }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-display text-2xl font-semibold text-slate-900 dark:text-white">
                    {title}
                </h2>
            }
        >
            <Head title={title} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {links.length > 0 && (
                        <nav className="flex flex-wrap gap-3 text-sm">
                            {links.map((link) => (
                                <Link
                                    key={link.href}
                                    href={link.href}
                                    className="text-emerald-700 underline dark:text-emerald-400"
                                >
                                    {link.label}
                                </Link>
                            ))}
                        </nav>
                    )}
                    <div className="border border-slate-200 bg-white/80 p-6 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200">
                        {children}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
