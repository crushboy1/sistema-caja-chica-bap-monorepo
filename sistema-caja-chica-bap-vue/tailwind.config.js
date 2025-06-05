/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        // Colores principales de BAP
        'verde-bap': '#76C49D',
        'verde-bap-dark': '#5da887', // Tono más oscuro para el gradiente de fondo
        'verde-bap-light': '#D1FAE5', // Tono más claro para estado activo y glow
        'verde-bap-extralight': '#F0FDF4', // Un verde muy, muy tenue (similar a green-50 de Tailwind)

        'amarillo-bap': '#FEDE72',
        'amarillo-bap-dark': '#CA8A04', // Tono más oscuro para texto/iconos en amarillo
        'amarillo-bap-light': '#FEF3C7', // Tono más claro para estado activo y glow

        'rojo-bap': '#DB3D47',
        'rojo-bap-dark': '#B91C1C', // Tono más oscuro para el gradiente de fondo
        'rojo-bap-light': '#FEE2E2', // Tono más claro para estado activo y glow

        // Colores hover personalizados (usando valores directos de Tailwind para consistencia)
        'verde-bap-hover': '#22C55E', // green-600
        'rojo-bap-hover': '#DC2626', // red-600
        'amarillo-bap-hover': '#D97706', // orange-600 (para hover en amarillo)
        'observar-bap-hover': '#C2410C', // orange-600 (para observación)
        'descargo-bap-hover': '#2563EB', // blue-600 (para descargo)

        // Colores para estados de solicitud
        'estado-creada': '#DBEAFE',
        'estado-creada-text': '#1E40AF',
        'estado-pendiente': '#BFDBFE',
        'estado-pendiente-text': '#1D4ED8',
        'estado-observada': '#FFEDD5',
        'estado-observada-text': '#EA580C',
        'estado-descargo': '#FEF3C7',
        'estado-descargo-text': '#D97706',
        'estado-aprobada-adm': '#D1FAE5',
        'estado-aprobada-adm-text': '#059669',
        'estado-aprobada-final': '#D1FAE5',
        'estado-aprobada-final-text': '#059669',
        'estado-rechazada': '#FEE2E2',
        'estado-rechazada-text': '#DC2626',
        // Colores adicionales para glassmorphism y efectos modernos
        'glass-white': 'rgba(255, 255, 255, 0.85)',
        'glass-border': 'rgba(255, 255, 255, 0.2)',
        'backdrop-dark': 'rgba(0, 0, 0, 0.6)',
      },

      // 🎨 ANIMACIONES MODERNAS
      animation: {
        // Animaciones suaves de entrada
        'fade-in': 'fadeIn 0.5s ease-in-out forwards', // Añadido forwards
        'fade-in-up': 'fadeInUp 0.6s ease-out forwards', // Añadido forwards
        'fade-in-down': 'fadeInDown 0.6s ease-out forwards', // Añadido forwards
        'slide-in-right': 'slideInRight 0.5s ease-out forwards', // Añadido forwards
        'slide-in-left': 'slideInLeft 0.5s ease-out forwards', // Añadido forwards

        // Animaciones de hover y interacción
        'bounce-gentle': 'bounceGentle 0.6s ease-in-out infinite', // Añadido infinite
        'pulse-soft': 'pulseSoft 2s infinite ease-in-out', // Ajustado timing-function
        wiggle: 'wiggle 1s ease-in-out infinite',
        float: 'float 3s ease-in-out infinite',

        // Animaciones de loading
        'spin-slow': 'spin 3s linear infinite',
        'ping-slow': 'ping 3s cubic-bezier(0, 0, 0.2, 1) infinite',
        shimmer: 'shimmer 2s infinite linear', // Ajustado timing-function

        // Animaciones de tarjetas
        'card-hover': 'cardHover 0.3s ease-out forwards', // Añadido forwards
        'card-click': 'cardClick 0.15s ease-in-out',
        'scale-in': 'scaleIn 0.3s ease-out forwards', // Añadido forwards
        'glow-pulse': 'glowPulse 2s ease-in-out infinite alternate',

        // Animaciones específicas para el modal
        'modal-scale': 'modalScale 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)',
        'modal-fade': 'modalFade 0.4s cubic-bezier(0.4, 0, 0.2, 1)',
        'shimmer-slide': 'shimmerSlide 1s ease-in-out',
        'bounce-scale': 'bounceScale 0.3s ease-out',
      },

      // 🎭 KEYFRAMES PERSONALIZADOS
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        fadeInUp: {
          '0%': { opacity: '0', transform: 'translateY(30px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        fadeInDown: {
          '0%': { opacity: '0', transform: 'translateY(-30px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        slideInRight: {
          '0%': { opacity: '0', transform: 'translateX(50px)' },
          '100%': { opacity: '1', transform: 'translateX(0)' },
        },
        slideInLeft: {
          '0%': { opacity: '0', transform: 'translateX(-50px)' },
          '100%': { opacity: '1', transform: 'translateX(0)' },
        },
        bounceGentle: {
          '0%, 20%, 50%, 80%, 100%': { transform: 'translateY(0)' },
          '40%': { transform: 'translateY(-10px)' },
          '60%': { transform: 'translateY(-5px)' },
        },
        pulseSoft: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.8' },
        },
        wiggle: {
          '0%, 100%': { transform: 'rotate(-3deg)' },
          '50%': { transform: 'rotate(3deg)' },
        },
        float: {
          '0%, 100%': { transform: 'translateY(0px)' },
          '50%': { transform: 'translateY(-10px)' },
        },
        shimmer: {
          '0%': { backgroundPosition: '-200% 0' },
          '100%': { backgroundPosition: '200% 0' },
        },
        cardHover: {
          '0%': { transform: 'translateY(0) scale(1)' },
          '100%': { transform: 'translateY(-5px) scale(1.02)' },
        },
        cardClick: {
          '0%': { transform: 'scale(1)' },
          '50%': { transform: 'scale(0.98)' },
          '100%': { transform: 'scale(1)' },
        },
        scaleIn: {
          '0%': { transform: 'scale(0.9)', opacity: '0' },
          '100%': { transform: 'scale(1)', opacity: '1' },
        },
        glowPulse: {
          '0%': { boxShadow: '0 0 5px rgba(118, 196, 157, 0.5)' },
          '100%': {
            boxShadow: '0 0 20px rgba(118, 196, 157, 0.8), 0 0 30px rgba(118, 196, 157, 0.3)',
          },
        },
        // Keyframes para el gradiente de texto (si se usa en otros elementos)
        'pulse-gradient': {
          '0%': { backgroundPosition: '0% 50%' },
          '100%': { backgroundPosition: '100% 50%' },
        },
        // Keyframes específicos para el modal
        modalScale: {
          '0%': {
            opacity: '0',
            transform: 'scale(0.8) translateY(50px)',
          },
          '100%': {
            opacity: '1',
            transform: 'scale(1) translateY(0)',
          },
        },
        modalFade: {
          '0%': {
            opacity: '0',
            backdropFilter: 'blur(0px)',
          },
          '100%': {
            opacity: '1',
            backdropFilter: 'blur(8px)',
          },
        },
        shimmerSlide: {
          '0%': { transform: 'translateX(-100%)' },
          '100%': { transform: 'translateX(100%)' },
        },
        bounceScale: {
          '0%': { transform: 'scale(1)' },
          '50%': { transform: 'scale(1.05)' },
          '100%': { transform: 'scale(1)' },
        },
      },

      // 🎯 TRANSICIONES SUAVES
      transitionProperty: {
        height: 'height',
        spacing: 'margin, padding',
        'colors-transform':
          'color, background-color, border-color, text-decoration-color, fill, stroke, transform',
        all: 'all', // Añadir 'all' para transiciones generales
      },

      transitionDuration: {
        400: '400ms',
        600: '600ms',
        800: '800ms',
        1000: '1000ms', // Añadir 1000ms
      },

      transitionTimingFunction: {
        'bounce-in': 'cubic-bezier(0.68, -0.55, 0.265, 1.55)',
        smooth: 'cubic-bezier(0.4, 0, 0.2, 1)',
      },

      // 🌟 EFECTOS DE SOMBRA MODERNOS
      boxShadow: {
        soft: '0 2px 15px -3px rgba(0, 0, 0, 0.07), 0 10px 20px -2px rgba(0, 0, 0, 0.04)',
        medium: '0 4px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 30px -5px rgba(0, 0, 0, 0.05)',
        strong: '0 10px 40px -10px rgba(0, 0, 0, 0.15), 0 20px 50px -10px rgba(0, 0, 0, 0.1)',
        'glow-verde': '0 0 20px rgba(118, 196, 157, 0.6), 0 0 30px rgba(118, 196, 157, 0.4)', // Ajustado para más brillo
        'glow-amarillo': '0 0 20px rgba(254, 222, 114, 0.6), 0 0 30px rgba(254, 222, 114, 0.4)', // Ajustado
        'glow-rojo': '0 0 20px rgba(219, 61, 71, 0.6), 0 0 30px rgba(219, 61, 71, 0.4)', // Ajustado
        'inner-soft': 'inset 0 2px 4px 0 rgba(0, 0, 0, 0.06)',
        'card-hover': '0 20px 40px -15px rgba(0, 0, 0, 0.2)',

        // Sombras específicas para el modal
        modal: '0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1)',
        'card-modern':
          '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06), 0 0 0 1px rgba(0, 0, 0, 0.05)',
        'glow-subtle': '0 0 15px rgba(118, 196, 157, 0.3)',
      },

      // 🎨 GRADIENTES MODERNOS
      backgroundImage: {
        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
        'gradient-conic': 'conic-gradient(from 180deg at 50% 50%, var(--tw-gradient-stops))',
        'shimmer-gradient':
          'linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent)',
        'glass-gradient':
          'linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0))',
      },

      // 📐 ESPACIADO PERSONALIZADO
      spacing: {
        18: '4.5rem',
        88: '22rem',
        128: '32rem',
      },

      // 🔤 TIPOGRAFÍA MEJORADA
      fontSize: {
        '2xs': ['0.625rem', { lineHeight: '0.75rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
      },

      // 🎪 EFECTOS DE BACKDROP
      backdropBlur: {
        xs: '2px',
      },

      // 🌈 BORDER RADIUS MODERNO
      borderRadius: {
        '4xl': '2rem',
        '5xl': '2.5rem',
        xs: '2px',
        sm: '4px',
        md: '8px',
        lg: '12px',
        xl: '16px',
      },

      // Nueva propiedad para text-shadow
      textShadow: {
        sm: '0 1px 2px var(--tw-shadow-color)',
        DEFAULT: '0 2px 4px var(--tw-shadow-color)',
        lg: '0 8px 16px var(--tw-shadow-color)',
      },
    },
  },

  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),

    // Plugin personalizado para utilities modernas
    function ({ addUtilities, theme }) {
      const newUtilities = {
        // Glassmorphism effect
        '.glass': {
          background: 'rgba(255, 255, 255, 0.85)', // Ajustado para ser más visible
          backdropFilter: 'blur(20px) saturate(180%)', // Ajustado
          border: '1px solid rgba(255, 255, 255, 0.3)', // Ajustado
        },
        // Glassmorphism mejorado específico para el modal
        '.glass-modal': {
          background: 'rgba(255, 255, 255, 0.9)',
          backdropFilter: 'blur(20px) saturate(180%)',
          border: '1px solid rgba(255, 255, 255, 0.2)',
          boxShadow: theme('boxShadow.modal'),
        },
        // Efecto de tarjeta flotante
        '.card-float': {
          transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
          '&:hover': {
            transform: 'translateY(-4px) scale(1.02)',
            boxShadow: theme('boxShadow.card-modern'),
          },
        },
        // Card moderna con hover (ya no se usa directamente en SolicitudFondoView, pero se mantiene por si acaso)
        '.card-modern': {
          background: 'white',
          borderRadius: theme('borderRadius.xl'),
          boxShadow: theme('boxShadow.soft'),
          transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
          '&:hover': {
            transform: 'translateY(-5px)',
            boxShadow: theme('boxShadow.card-hover'),
          },
        },

        // Texto con gradiente (se mantiene por si se usa en otros elementos, pero no en el h1 principal)
        '.text-gradient': {
          background: `linear-gradient(90deg, ${theme('colors.verde-bap-dark')}, ${theme('colors.amarillo-bap-dark')}, ${theme('colors.rojo-bap-dark')})`,
          '-webkit-background-clip': 'text',
          'background-clip': 'text',
          '-webkit-text-fill-color': 'transparent',
          color: 'transparent', // Fallback
          'background-size': '200% auto', // Para la animación del gradiente
          animation: 'pulse-gradient 4s infinite alternate',
        },

        // Botón con efecto shimmer mejorado
        '.btn-shimmer-modern': {
          position: 'relative',
          overflow: 'hidden',
          '&::before': {
            content: '""',
            position: 'absolute',
            top: '0',
            left: '-100%',
            width: '100%',
            height: '100%',
            background: 'linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent)',
            transition: 'left 0.7s ease-in-out',
          },
          '&:hover::before': {
            left: '100%',
          },
        },
        // Texto con efecto de sombra suave
        '.text-shadow-soft': {
          textShadow: '0 2px 4px rgba(0, 0, 0, 0.1)',
        },

        // Gradiente de fondo para headers
        '.bg-gradient-bap': {
          background: `linear-gradient(135deg, ${theme('colors.verde-bap')}, ${theme('colors.verde-bap-dark')})`,
        },
        // Scroll suave personalizado
        '.scroll-smooth-custom': {
          scrollBehavior: 'smooth',
          '&::-webkit-scrollbar': {
            width: '6px',
          },
          '&::-webkit-scrollbar-track': {
            background: '#f1f1f1',
            borderRadius: '10px',
          },
          '&::-webkit-scrollbar-thumb': {
            background: theme('colors.verde-bap'),
            borderRadius: '10px',
            '&:hover': {
              background: theme('colors.verde-bap-dark'),
            },
          },
        },
        // Scroll moderno para el modal
        '.scroll-modal': {
          scrollBehavior: 'smooth',
          '&::-webkit-scrollbar': {
            width: '8px',
          },
          '&::-webkit-scrollbar-track': {
            background: 'rgba(0, 0, 0, 0.05)',
            borderRadius: '10px',
          },
          '&::-webkit-scrollbar-thumb': {
            background: `linear-gradient(180deg, ${theme('colors.verde-bap')}, ${theme('colors.verde-bap-dark')})`,
            borderRadius: '10px',
            transition: 'all 0.3s ease',
            '&:hover': {
              background: `linear-gradient(180deg, ${theme('colors.verde-bap-dark')}, #4a9470)`,
              boxShadow: `0 0 10px ${theme('colors.verde-bap')}`,
            },
          },
        },

        // Plugin para text-shadow
        '.text-shadow-sm': {
          textShadow: theme('textShadow.sm'),
        },
        '.text-shadow': {
          textShadow: theme('textShadow.DEFAULT'),
        },
        '.text-shadow-lg': {
          textShadow: theme('textShadow.lg'),
        },
        '.text-shadow-none': {
          textShadow: 'none',
        },
      }

      addUtilities(newUtilities, ['responsive', 'hover'])
    },
  ],
}
