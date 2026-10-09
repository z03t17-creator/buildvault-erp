import { usePage } from '@inertiajs/react';

/**
 * @param {string} ability
 * @returns {boolean}
 */
export default function useCan(ability) {
    const can = usePage().props.auth?.can || {};
    return Boolean(can[ability]);
}

/**
 * @returns {(ability: string) => boolean}
 */
export function useCanMap() {
    const can = usePage().props.auth?.can || {};
    return (ability) => Boolean(can[ability]);
}
