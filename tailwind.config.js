import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            keyframes: {
                glow: {
                    '0%, 100%': { boxShadow: '0 0 6px 0px rgba(56, 189, 248, 0.5)' },
                    '50%': { boxShadow: '0 0 12px 2px rgba(56, 189, 248, 1)' },
                },
            },
            animation: {
                glow: 'glow 2s infinite',
            },
        },
    },

    plugins: [forms],
};
