import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import flowbitePlugin from 'flowbite/plugin';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
        './node_modules/flowbite/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                brand: {
                    DEFAULT: '#0f766e',
                    hover: '#0d9488',
                },
            },
            fontFamily: {
                sans: ['Source Sans 3', 'Noto Sans Arabic', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms, flowbitePlugin],
};
