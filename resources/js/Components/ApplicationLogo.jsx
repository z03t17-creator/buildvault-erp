export default function ApplicationLogo({ className = '', alt = 'ZHAKO', ...props }) {
    return (
        <img
            src="/images/zhako-logo.jpg"
            alt={alt}
            className={`object-contain ${className}`}
            {...props}
        />
    );
}
