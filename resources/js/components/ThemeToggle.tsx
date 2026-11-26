import { useState } from 'react'
import { applyTheme, readTheme, type ThemeChoice } from '../lib/theme'
import { Segmented } from './Segmented'

export function ThemeToggle() {
  const [theme, setTheme] = useState<ThemeChoice>(readTheme)
  return (
    <Segmented<ThemeChoice>
      label="Theme"
      value={theme}
      onChange={(choice) => {
        setTheme(choice)
        applyTheme(choice)
      }}
      options={[
        { value: 'system', label: 'Auto' },
        { value: 'light', label: 'Light' },
        { value: 'dark', label: 'Dark' },
      ]}
    />
  )
}
