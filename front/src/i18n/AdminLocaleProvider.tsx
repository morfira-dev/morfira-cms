import { createContext, type PropsWithChildren, useContext, useEffect, useMemo, useState } from 'react'
import { russianMessages, type MessageKey } from './messages'

export type AdminLocale = 'ru' | 'en'

interface AdminLocaleContextValue {
  locale: AdminLocale
  setLocale: (locale: AdminLocale) => void
  t: (key: MessageKey, parameters?: Record<string, string | number>) => string
  errorMessage: (reason: unknown, fallback: MessageKey) => string
  formatDateTime: (value: string | Date) => string
}

const AdminLocaleContext = createContext<AdminLocaleContextValue | null>(null)

function initialLocale(): AdminLocale {
  return 'en'
}

function interpolate(message: string, parameters: Record<string, string | number>): string {
  return Object.entries(parameters).reduce((result, [name, value]) => result.replaceAll(`{{${name}}}`, String(value)), message)
}

export function AdminLocaleProvider({ children }: PropsWithChildren) {
  const [locale, setLocaleState] = useState<AdminLocale>(initialLocale)

  useEffect(() => {
    document.documentElement.lang = locale
    document.title = locale === 'ru' ? 'Morfira — Студия управления' : 'Morfira — Admin Studio'
  }, [locale])

  const value = useMemo<AdminLocaleContextValue>(() => ({
    locale,
    setLocale: setLocaleState,
    t: (key, parameters = {}) => interpolate(locale === 'ru' ? russianMessages[key] : key, parameters),
    errorMessage: (reason, fallback) => locale === 'en' && reason instanceof Error
      ? reason.message
      : interpolate(locale === 'ru' ? russianMessages[fallback] : fallback, {}),
    formatDateTime: (input) => new Intl.DateTimeFormat(locale === 'ru' ? 'ru-RU' : 'en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(input)),
  }), [locale])

  return <AdminLocaleContext.Provider value={value}>{children}</AdminLocaleContext.Provider>
}

export function useAdminLocale(): AdminLocaleContextValue {
  const value = useContext(AdminLocaleContext)
  if (!value) throw new Error('useAdminLocale must be used within AdminLocaleProvider.')
  return value
}
