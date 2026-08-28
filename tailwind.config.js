import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                aviation: {
                    50: '#F0F6FC',
                    100: '#E1EDF8',
                    200: '#C3DCF1',
                    300: '#94C1E5',
                    400: '#69A5C9',
                    500: '#558BB3',
                    600: '#2764AA',
                    700: '#1D4F88',
                    800: '#173E6B',
                    900: '#133256',
                    950: '#0B1E34',
                },
                navy: {
                    800: '#0F172A',
                    900: '#090E1A',
                    950: '#050914',
                },
                arrival: {
                    50: '#FDF7F4',
                    100: '#FAECE6',
                    200: '#F5D7CA',
                    300: '#EAB8A5',
                    400: '#DD9379',
                    500: '#C76F4E',
                    600: '#975432',
                    700: '#7C4225',
                    800: '#62351F',
                    900: '#4C2B1B',
                    int: '#E9A52F',
                },
                surface: {
                    DEFAULT: '#F7F9FB',
                    subtle: '#EEF2F5',
                    card: '#FFFFFF',
                    dark: '#050914',
                    darkcard: '#0B111E',
                    darksubtle: '#111927',
                },
                text: {
                    primary: '#172033',
                    secondary: '#657184',
                }
            },
            borderRadius: {
                'card': '1rem',
                'card-lg': '1.25rem',
                '2xl': '1rem',
                '3xl': '1.25rem',
            },
            boxShadow: {
                'flight': '0 4px 20px -2px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.04)',
                'flight-hover': '0 12px 30px -4px rgba(39, 100, 170, 0.12), 0 4px 12px -2px rgba(15, 23, 42, 0.06)',
                'flight-dark': '0 4px 24px -2px rgba(0, 0, 0, 0.45)',
            },
            gridTemplateColumns: {
                '24': 'repeat(24, minmax(0, 1fr))',
            }
        },
    },

    plugins: [forms],
};

