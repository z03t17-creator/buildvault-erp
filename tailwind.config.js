import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                /* Judi-style clean UI; money always sans */
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#0d9488',
                    strong: '#0f766e',
                    soft: '#ccfbf1',
                },
            },
            borderRadius: {
                xl: '0.875rem',
                '2xl': '1.125rem',
            },
            minHeight: {
                touch: '2.75rem',
            },
            boxShadow: {
                judi: '0 1px 2px rgb(15 23 42 / 0.04), 0 12px 32px -16px rgb(15 23 42 / 0.12)',
            },
        },
    },

    plugins: [forms],
};
