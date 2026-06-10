/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    './index.html',
    './src/**/*.{js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Outfit', 'system-ui', '-apple-system', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
      },
      colors: {
        metro: '#003CA6',
        rer: '#A0006E',
        tram: '#6EBE4A',
        bus: '#FF7900',
        transilien: '#004494',
      },
    },
  },
  plugins: [],
}
