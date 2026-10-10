import SecondaryButton from '@/Components/SecondaryButton';
import useTranslations from '@/hooks/useTranslations';
import { Link, router } from '@inertiajs/react';

/**
 * Compact edit / delete / print controls for a stock item row.
 */
export default function StockItemRowActions({ itemId, canManage = false, className = '' }) {
    const t = useTranslations();

    return (
        <div className={`flex flex-wrap items-center gap-1.5 ${className}`.trim()}>
            <Link href={route('stock.items.print', itemId)}>
                <SecondaryButton type="button" className="!min-h-[2rem] !px-2.5 !text-xs">
                    {t('print')}
                </SecondaryButton>
            </Link>
            {canManage ? (
                <>
                    <Link href={route('stock.items.edit', itemId)}>
                        <SecondaryButton type="button" className="!min-h-[2rem] !px-2.5 !text-xs">
                            {t('edit')}
                        </SecondaryButton>
                    </Link>
                    <SecondaryButton
                        type="button"
                        className="!min-h-[2rem] !px-2.5 !text-xs !text-rose-700 dark:!text-rose-300"
                        onClick={() => {
                            if (confirm(t('confirm_delete'))) {
                                router.delete(route('stock.items.destroy', itemId));
                            }
                        }}
                    >
                        {t('delete')}
                    </SecondaryButton>
                </>
            ) : null}
        </div>
    );
}
