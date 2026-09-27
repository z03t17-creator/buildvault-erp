import { usePage } from '@inertiajs/react';

export default function useTranslations() {
    const { translations } = usePage().props;

    return (key, fallback = key) => translations?.[key] ?? fallback;
}
