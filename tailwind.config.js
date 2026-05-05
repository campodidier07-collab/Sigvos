/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./public/**/*.{html,php,js}",
    "./views/**/*.{html,php,js}",
    "./app/**/*.{html,php,js}"
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
        heading: ['Outfit', 'sans-serif'],
      },
      colors: {
        agro: {
          50: '#f2fbf5',
          100: '#e1f6e8',
          200: '#c4ebd4',
          300: '#96d9b4',
          400: '#5fc18f',
          500: '#3aa574',
          600: '#2b845a',
          700: '#25694a',
          800: '#21543d',
          900: '#1d4533',
          950: '#0f271d',
        }
      },
      animation: {
        'float': 'float 6s ease-in-out infinite',
        'fade-in-up': 'fadeInUp 0.8s ease-out forwards',
      },
      keyframes: {
        float: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-10px)' },
        },
        fadeInUp: {
          '0%': { opacity: '0', transform: 'translateY(20px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        }
      }
    }
  },
  plugins: [],
}
