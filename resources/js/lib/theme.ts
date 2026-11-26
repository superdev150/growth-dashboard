export type ThemeChoice = 'system' | 'light' | 'dark'

const KEY = 'growth.theme'

export function readTheme(): ThemeChoice {
  try {
    const value = localStorage.getItem(KEY)
    if (value === 'light' || value === 'dark') return value
  } catch {
    // storage unavailable
  }
  return 'system'
}

/** `system` removes the attribute and lets prefers-color-scheme decide; the inline script in the shell does the same before paint. */
export function applyTheme(choice: ThemeChoice): void {
  const root = document.documentElement
  if (choice === 'system') delete root.dataset.theme
  else root.dataset.theme = choice
  try {
    if (choice === 'system') localStorage.removeItem(KEY)
    else localStorage.setItem(KEY, choice)
  } catch {
    // not persisted
  }
}
