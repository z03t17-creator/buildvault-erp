import ConfirmDialog from '@/Components/ConfirmDialog';
import SecondaryButton from '@/Components/SecondaryButton';
import useTranslations from '@/hooks/useTranslations';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Compact edit / delete / print controls for a stock item row.
 */
export default function StockItemRowActions({ itemId, canManage = false, className = '' }) {
    const t = useTranslations();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const closeConfirm = () => {
        if (deleting) return;
        setConfirmOpen(false);
    };

    const confirmDelete = () => {
        if (deleting) return;
        setDeleting(true);
        router.delete(route('stock.items.destroy', itemId), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setConfirmOpen(false);
            },
        });
    };

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
                        onClick={() => setConfirmOpen(true)}
                    >
                        {t('delete')}
                    </SecondaryButton>
                    <ConfirmDialog
                        show={confirmOpen}
                        title={t('confirm_delete_title')}
                        message={t('confirm_delete_hint')}
                        confirmLabel={t('delete')}
                        cancelLabel={t('cancel')}
                        processing={deleting}
                        onClose={closeConfirm}
                        onConfirm={confirmDelete}
                    />
                </>
            ) : null}
        </div>
    );
}
