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
                    '0%': { boxShadow: '0 0 0px 0px rgba(56, 189, 248, 0)' },
                    '50%': { boxShadow: '0 0 12px 2px rgba(56, 189, 248, 1)' },
                    '100%': { boxShadow: '0 0 12px 2px rgba(56, 189, 248, 0.6)' }, // Stile finale
                },
                ring: {
                    '0%': { transform: 'rotate(0)' },
                    '10%': { transform: 'rotate(15deg)' },
                    '20%': { transform: 'rotate(-10deg)' },
                    '30%': { transform: 'rotate(7deg)' },
                    '40%': { transform: 'rotate(-5deg)' },
                    '50%': { transform: 'rotate(3deg)' },
                    '60%': { transform: 'rotate(-2deg)' },
                    '70%': { transform: 'rotate(1deg)' },
                    '100%': { transform: 'rotate(0)' },
                },
            },
            animation: {
                glow: 'glow 0.5s ease-in-out 1 forwards',
                ring: 'ring 0.8s ease-in-out infinite alternate',
            },
        },
    },

    plugins: [forms],
};
