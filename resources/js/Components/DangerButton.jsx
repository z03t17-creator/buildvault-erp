export default function DangerButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={`bv-btn-danger ${disabled ? 'opacity-40' : ''} ${className}`.trim()}
            disabled={disabled}
        >
            {children}
        </button>
    );
}
