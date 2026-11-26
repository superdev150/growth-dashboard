import { useEffect, useState } from 'react'

/**
 * useState that remembers its value in localStorage. Storage can be missing or throw (private windows, blocked site
 * data), so every access is guarded and the page works the same without it. `valid` rejects stale or edited values.
 */
export function usePersistentState<T>(key: string, initial: T, valid: (value: unknown) => value is T) {
  const [value, setValue] = useState<T>(() => {
    try {
      const stored = localStorage.getItem(key)
      if (stored !== null) {
        const parsed: unknown = JSON.parse(stored)
        if (valid(parsed)) return parsed
      }
    } catch {
      // unavailable or corrupt: use the default
    }
    return initial
  })

  useEffect(() => {
    try {
      localStorage.setItem(key, JSON.stringify(value))
    } catch {
      // not persisted; fine
    }
  }, [key, value])

  return [value, setValue] as const
}
