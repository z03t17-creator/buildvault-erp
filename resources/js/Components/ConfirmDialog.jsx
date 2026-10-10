import Modal from '@/Components/Modal';
import { AlertTriangle } from 'lucide-react';

/**
 * Dark, compact confirm dialog for destructive actions.
 * Replaces browser window.confirm with an in-app modal.
 */
export default function ConfirmDialog({
    show = false,
    title,
    message,
    confirmLabel,
    cancelLabel,
    processing = false,
    tone = 'danger',
    onConfirm,
    onClose,
}) {
    const tones = {
        danger: {
            iconWrap: 'bg-rose-500/15 text-rose-300 ring-1 ring-rose-400/25',
            confirm:
                'bg-rose-500 text-white hover:bg-rose-400 focus-visible:ring-rose-300/50 disabled:bg-rose-500/50',
        },
        warn: {
            iconWrap: 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-400/25',
            confirm:
                'bg-amber-500 text-slate-950 hover:bg-amber-400 focus-visible:ring-amber-300/50 disabled:bg-amber-500/50',
        },
    };
    const style = tones[tone] || tones.danger;

    return (
        <Modal show={show} onClose={processing ? () => {} : onClose} maxWidth="sm" closeable={!processing}>
            <div className="relative overflow-hidden bg-[#0B0F19] text-slate-100">
                <div className="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-rose-500/10 to-transparent" />

                <div className="relative px-5 pb-5 pt-6 sm:px-6">
                    <div className="flex flex-col items-center text-center">
                        <span
                            className={
                                'mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl ' +
                                style.iconWrap
                            }
                        >
                            <AlertTriangle className="h-5 w-5" strokeWidth={1.75} />
                        </span>
                        <h2 className="text-base font-semibold tracking-tight text-white">
                            {title}
                        </h2>
                        {message ? (
                            <p className="mt-2 max-w-sm text-sm leading-relaxed text-slate-400">
                                {message}
                            </p>
                        ) : null}
                    </div>

                    <div className="mt-6 grid grid-cols-2 gap-2.5">
                        <button
                            type="button"
                            disabled={processing}
                            onClick={onClose}
                            className="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-white/[0.08] disabled:opacity-50"
                        >
                            {cancelLabel}
                        </button>
                        <button
                            type="button"
                            disabled={processing}
                            onClick={onConfirm}
                            className={
                                'rounded-xl px-3 py-2.5 text-sm font-semibold shadow-lg shadow-rose-950/30 transition focus:outline-none focus-visible:ring-2 disabled:cursor-not-allowed ' +
                                style.confirm
                            }
                        >
                            {processing ? '…' : confirmLabel}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
