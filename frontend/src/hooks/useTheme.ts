import { useEffect, useState } from 'react'

const STORAGE_KEY = 'ancf_theme'

export type Theme = 'light' | 'dark'

/** Thème enregistré dans le navigateur, sombre par défaut. */
export function getInitialTheme(): Theme {
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored === 'light' || stored === 'dark') return stored
  return 'dark'
}

/** Applique le thème à la page et l'enregistre dans le navigateur. */
export function applyTheme(theme: Theme) {
  document.documentElement.classList.toggle('dark', theme === 'dark')
  localStorage.setItem(STORAGE_KEY, theme)
}

/** Hook du thème clair/sombre. */
export function useTheme() {
  const [theme, setTheme] = useState<Theme>(getInitialTheme)

  useEffect(() => {
    applyTheme(theme)
  }, [theme])

  /** Bascule entre le thème clair et le thème sombre. */
  const toggleTheme = () => setTheme((t) => (t === 'dark' ? 'light' : 'dark'))

  return { theme, toggleTheme }
}
