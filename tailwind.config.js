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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                slate: {
                    50: '#f6f8fa',
                    100: '#edf1f5',
                    200: '#dde4ec',
                    300: '#c4d0dc',
                    400: '#8fa1b5',
                    500: '#63768d',
                    600: '#4b5c70',
                    700: '#384657',
                    800: '#27323f',
                    900: '#1d2630',
                    950: '#141b22',
                },
                blue: {
                    50: '#f2f7fb',
                    100: '#e3eef7',
                    200: '#c6dced',
                    300: '#9bc2df',
                    400: '#6ba2cc',
                    500: '#4b86b4',
                    600: '#3b6e9c',
                    700: '#305980',
                    800: '#294b6b',
                    900: '#243f59',
                    950: '#18293b',
                },
                indigo: {
                    50: '#f4f6fb',
                    100: '#e7ebf6',
                    200: '#d2daf0',
                    500: '#5a72b5',
                    600: '#495f9e',
                    700: '#3c4e82',
                },
                emerald: {
                    50: '#f2f9f6',
                    100: '#dff0ea',
                    200: '#bfe2d6',
                    300: '#94ccb9',
                    400: '#65b099',
                    500: '#48967e',
                    600: '#387d68',
                    700: '#2e6555',
                    800: '#275145',
                    900: '#214339',
                },
                rose: {
                    50: '#fcf4f5',
                    100: '#f7e6e8',
                    200: '#eccad0',
                    300: '#dea4ae',
                    400: '#cc7886',
                    500: '#ba5a6a',
                    600: '#a64858',
                    700: '#8b3a48',
                    800: '#74333f',
                    900: '#622e38',
                },
                amber: {
                    50: '#fdf8f0',
                    100: '#f9eddb',
                    200: '#f1d8b5',
                    300: '#e6bd88',
                    400: '#da9f5c',
                    500: '#c9853b',
                    600: '#af6d2b',
                    700: '#915624',
                    800: '#774622',
                    900: '#623a1f',
                },
            },
        },
    },

    plugins: [forms],
};

