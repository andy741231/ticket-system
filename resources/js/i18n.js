import { createI18n } from 'vue-i18n';

// Import your translation files
import en from './Locales/en.json';

const i18n = createI18n({
    legacy: false, // you must set `false`, to use Composition API
    locale: 'en', // set locale
    fallbackLocale: 'en', // set fallback locale
    messages: { en },
    silentTranslationWarn: true
});

export default i18n;
