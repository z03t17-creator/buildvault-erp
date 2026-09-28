import { usePage } from '@inertiajs/react';

/**
 * @param {string} template
 * @param {Record<string, string|number>|undefined} replacements
 */
function applyReplacements(template, replacements) {
    if (!replacements) {
        return template;
    }

    return Object.entries(replacements).reduce(
        (text, [key, value]) => text.replaceAll(`:${key}`, String(value)),
        template,
    );
}

export default function useTranslations() {
    const { translations } = usePage().props;

    return (key, replacementsOrFallback, maybeFallback) => {
        const hasReplacements =
            replacementsOrFallback !== undefined &&
            typeof replacementsOrFallback === 'object' &&
            replacementsOrFallback !== null;

        const replacements = hasReplacements ? replacementsOrFallback : undefined;
        const fallback = hasReplacements
            ? (maybeFallback ?? key)
            : (replacementsOrFallback ?? key);

        const raw = translations?.[key] ?? fallback;

        return applyReplacements(raw, replacements);
    };
}
