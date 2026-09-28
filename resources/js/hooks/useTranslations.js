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
            replacementsOrFallback !== null &&
            typeof replacementsOrFallback === 'object' &&
            !Array.isArray(replacementsOrFallback);

        const replacements = hasReplacements ? replacementsOrFallback : undefined;
        const fallback = hasReplacements
            ? (maybeFallback ?? key)
            : (replacementsOrFallback === undefined ? key : replacementsOrFallback);

        const raw = translations?.[key] ?? (fallback === null ? undefined : fallback) ?? key;

        return applyReplacements(raw, replacements);
    };
}
