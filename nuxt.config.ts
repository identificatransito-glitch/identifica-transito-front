import Aura from '@primeuix/themes/aura';
import tailwindcss from "@tailwindcss/vite";

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },
    ssr: true,
    routeRules: {
        '/': { prerender: true },
        '/dashboard/**': { ssr: false },
        '/auth/**': { ssr: false },
        '/uikit/**': { prerender: false },
        '/uikit/ChartDoc': { prerender: false },
        '/uikit/TableDoc': { prerender: false },
    },
    nitro: {
        prerender: {
            failOnError: false,
            crawlLinks: false,
            routes: ['/', '/auth/register', '/auth/login']
        }
    },
    runtimeConfig: {
        public: {
            apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://127.0.0.1:8000/api',
            useMocks: process.env.NUXT_PUBLIC_USE_MOCKS !== 'false',
            mercadoPagoPublicKey: process.env.NUXT_PUBLIC_MERCADOPAGO_PUBLIC_KEY || ''
        }
    },
  components: true,
  app: {
        baseURL: process.env.NUXT_APP_BASE_URL || '/',
    head: {
        link: [
            { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
            { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
            { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,400..900;1,400..900&display=swap' }
        ],
        style: [
            {
                key: 'landing-startup-critical',
                innerHTML: `.landing-startup-loader{position:fixed;inset:0;z-index:2147483647;display:grid;place-items:center;padding:24px;overflow:hidden;background:#070b1f;color:#fff;pointer-events:auto}.landing-startup-loader__content{display:grid;width:min(360px,100%);justify-items:center;gap:32px}.landing-startup-loader__logo{display:block;width:172px;height:auto;max-width:100%;filter:brightness(0) invert(1)}.landing-startup-loader__progress{display:grid;width:100%;gap:10px}.landing-startup-loader__track{height:6px;overflow:hidden;border:1px solid #3069ff;border-radius:999px;background:#0b102a}.landing-startup-loader__fill{display:block;width:100%;height:100%;transform-origin:left;background:#2864ec;animation:landing-startup-fill 1.1s cubic-bezier(.22,1,.36,1) both}.landing-startup-loader__percent{font:600 12px/1 Figtree,Arial,sans-serif;letter-spacing:.1em;text-align:center;color:#dbe5ff}@keyframes landing-startup-fill{from{transform:scaleX(.08)}to{transform:scaleX(1)}}html:has(.landing-startup-loader:not(.landing-startup-loader--hidden)),body:has(.landing-startup-loader:not(.landing-startup-loader--hidden)){height:100%;overflow:hidden;overscroll-behavior:none;scrollbar-gutter:stable}@media(prefers-reduced-motion:reduce){.landing-startup-loader__fill{animation:none}}`
            }
        ]
    }
  },
  modules: ['@nuxt/fonts', '@nuxt/image', '@primevue/nuxt-module'],
  vite: {    plugins: [      tailwindcss(),    ],  },
  css: [
    './app/assets/css/main.css',
    './app/assets/css/styles.scss',
    './app/assets/css/landing-preview.css'
  ],
  primevue: {
        options: {
            locale: {
                firstDayOfWeek: 0,
                dayNames: ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'],
                dayNamesShort: ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'],
                dayNamesMin: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
                monthNames: ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
                monthNamesShort: ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'],
                today: 'Hoje',
                clear: 'Limpar'
            },
            theme: {
                preset: Aura,
                options: {
                    darkModeSelector: false
                }
            }
        }
    }
})
