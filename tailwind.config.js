import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                // Velo theme — extracted from uploaded velo-dashboard.html
                bg:      { DEFAULT: '#0a0812', 2: '#0f0d1a', 3: '#141224' },
                surface: { DEFAULT: '#1a1730', 2: '#201d38', 3: '#272446' },

                brand: {
                    DEFAULT: '#7c3aed',
                    50:  '#f5f3ff', 100: '#ede9fe', 200: '#ddd6fe',
                    300: '#c4b5fd', 400: '#a78bfa', 500: '#8b5cf6',
                    600: '#7c3aed', 700: '#6d28d9', 800: '#5b21b6', 900: '#4c1d95',
                },
                fuchsia: { DEFAULT: '#c026d3' },
                cyan:    { DEFAULT: '#06b6d4' },
                amber:   { DEFAULT: '#f59e0b' },
                emerald: { DEFAULT: '#10b981' },
                rose:    { DEFAULT: '#f43f5e' },

                ink: { DEFAULT: '#e8e4f8', 2: '#9b93c4', 3: '#5c5480' },

                frost: {
                    1: 'rgba(139,92,246,0.14)',
                    2: 'rgba(139,92,246,0.22)',
                    3: 'rgba(139,92,246,0.35)',
                },
            },
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
            },
            fontSize: { '2xs': ['10px', { lineHeight: '14px' }] },
            borderRadius: { sm: '6px', DEFAULT: '10px', md: '12px', lg: '14px', xl: '18px' },
            boxShadow: {
                glow:       '0 4px 20px rgba(124,58,237,0.35)',
                'glow-sm':  '0 2px 10px rgba(124,58,237,0.25)',
                'glow-lg':  '0 8px 32px rgba(124,58,237,0.45)',
            },
            backgroundImage: {
                'brand-gradient':   'linear-gradient(135deg, #7c3aed, #c026d3)',
                'brand-gradient-2': 'linear-gradient(135deg, #9d5ff5, #c026d3)',
                'radial-brand':     'radial-gradient(circle at top right, rgba(124,58,237,0.25) 0%, transparent 70%)',
                'grid-fade':        'linear-gradient(rgba(139,92,246,.14) 1px, transparent 1px), linear-gradient(90deg, rgba(139,92,246,.14) 1px, transparent 1px)',
            },
            backgroundSize: { 'grid-sm': '24px 24px' },
            keyframes: {
                'fade-in':    { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                'slide-up':   { '0%': { opacity: '0', transform: 'translateY(8px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
                'glow-pulse': {
                    '0%, 100%': { boxShadow: '0 0 0 0 rgba(124,58,237,0.4)' },
                    '50%':      { boxShadow: '0 0 0 8px rgba(124,58,237,0)' },
                },
            },
            animation: {
                'fade-in':    'fade-in 180ms ease-out both',
                'slide-up':   'slide-up 220ms ease-out both',
                'glow-pulse': 'glow-pulse 2s ease-in-out infinite',
            },
        },
    },
    plugins: [forms, typography],
};
