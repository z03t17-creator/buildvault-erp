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
                sans: ['"Source Sans 3"', ...defaultTheme.fontFamily.sans],
                display: ['"Cormorant Garamond"', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                brand: {
                    DEFAULT: '#10b981',
                    strong: '#059669',
                },
            },
        },
    },

    plugins: [forms],
};
