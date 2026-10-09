export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={`bv-btn-primary ${disabled ? 'opacity-40' : ''} ${className}`.trim()}
            disabled={disabled}
        >
            {children}
        </button>
    );
}
