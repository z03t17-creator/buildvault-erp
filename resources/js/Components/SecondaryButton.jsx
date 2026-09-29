export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            type={type}
            className={`bv-btn-secondary ${disabled ? 'opacity-40' : ''} ${className}`.trim()}
            disabled={disabled}
        >
            {children}
        </button>
    );
}
