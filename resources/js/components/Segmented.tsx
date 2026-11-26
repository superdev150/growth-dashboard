interface Option<T> {
  value: T
  label: string
}

/** A radio group drawn as a segmented control; arrows move between options, as in any native radio group. */
export function Segmented<T extends string | number>({ label, options, value, onChange }: { label: string; options: Option<T>[]; value: T; onChange: (value: T) => void }) {
  return (
    <div className="segmented" role="radiogroup" aria-label={label}>
      {options.map((option, i) => (
        <button
          key={String(option.value)}
          type="button"
          role="radio"
          aria-checked={option.value === value}
          tabIndex={option.value === value ? 0 : -1}
          onClick={() => onChange(option.value)}
          onKeyDown={(event) => {
            const step = event.key === 'ArrowRight' || event.key === 'ArrowDown' ? 1 : event.key === 'ArrowLeft' || event.key === 'ArrowUp' ? -1 : 0
            if (!step) return
            event.preventDefault()
            const next = options[(i + step + options.length) % options.length]
            onChange(next.value)
            const buttons = event.currentTarget.parentElement?.querySelectorAll<HTMLButtonElement>('button')
            buttons?.[(i + step + options.length) % options.length]?.focus()
          }}
        >
          {option.label}
        </button>
      ))}
    </div>
  )
}
