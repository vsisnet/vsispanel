import { createI18n } from 'vue-i18n'
import vi from './locales/vi.json'
import en from './locales/en.json'

const savedLocale = localStorage.getItem('vsispanel_locale') || 'en'

const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'en',
  messages: {
    vi,
    en
  }
})

export function setLocale(locale) {
  i18n.global.locale.value = locale
  localStorage.setItem('vsispanel_locale', locale)
  document.querySelector('html').setAttribute('lang', locale)
}

export function getLocale() {
  return i18n.global.locale.value
}

export default i18n
