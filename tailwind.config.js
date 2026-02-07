import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    darkMode: 'class', // Enable dark mode with class strategy
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: '#1A5276',
                    50: '#E8F0F7',
                    100: '#D1E1EF',
                    200: '#A3C3DF',
                    300: '#75A5CF',
                    400: '#4787BF',
                    500: '#1A5276',
                    600: '#15425E',
                    700: '#103147',
                    800: '#0B212F',
                    900: '#051018',
                },
                secondary: {
                    DEFAULT: '#2ECC71',
                    50: '#E9F7EF',
                    100: '#D4EFDF',
                    200: '#A9DFBF',
                    300: '#7DCFA0',
                    400: '#52BF80',
                    500: '#2ECC71',
                    600: '#25A35A',
                    700: '#1C7A44',
                    800: '#12522D',
                    900: '#092917',
                },
                danger: {
                    DEFAULT: '#E74C3C',
                    50: '#FCE9E7',
                    100: '#F9D3CF',
                    200: '#F3A79F',
                    300: '#ED7B6F',
                    400: '#E74F3F',
                    500: '#E74C3C',
                    600: '#B93D30',
                    700: '#8B2E24',
                    800: '#5C1F18',
                    900: '#2E0F0C',
                },
                'bg-light': '#F8F9FA',
                'bg-dark': '#1A1A2E',
            },
        },
    },
    plugins: [forms],
};
