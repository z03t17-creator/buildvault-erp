/**
 * Consistent page body width + vertical rhythm for authenticated screens.
 */
export default function PageShell({ children, className = '', narrow = false }) {
    return (
        <div className={`py-8 ${className}`.trim()}>
            <div
                className={
                    (narrow ? 'mx-auto max-w-4xl' : 'mx-auto max-w-7xl') +
                    ' space-y-6 px-4 sm:px-6 lg:px-8'
                }
            >
                {children}
            </div>
        </div>
    );
}
