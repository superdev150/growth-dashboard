const cache = new Map<string, Intl.NumberFormat>()

function formatter(key: string, options: Intl.NumberFormatOptions): Intl.NumberFormat {
  let f = cache.get(key)
  if (!f) {
    f = new Intl.NumberFormat('en-US', options)
    cache.set(key, f)
  }
  return f
}

/** $1,284.50 · $12,840 · compact: $12.8K */
export function money(value: number, currency: string, compact = false): string {
  if (compact && Math.abs(value) >= 10_000) {
    return formatter(`m:c:${currency}`, { style: 'currency', currency, notation: 'compact', maximumFractionDigits: 1 }).format(value)
  }
  const digits = Math.abs(value) >= 1000 ? 0 : 2
  return formatter(`m:${currency}:${digits}`, { style: 'currency', currency, minimumFractionDigits: digits, maximumFractionDigits: digits }).format(value)
}

/** 1,284 · compact: 12.8K */
export function count(value: number, compact = false): string {
  if (compact && Math.abs(value) >= 10_000) {
    return formatter('c:c', { notation: 'compact', maximumFractionDigits: 1 }).format(value)
  }
  return formatter('c', { maximumFractionDigits: 0 }).format(value)
}

/** 1.62× */
export function ratio(value: number): string {
  return `${value.toFixed(2)}×`
}

/** +4.2% · −3.1% (a real minus sign) · 0% · — when there is nothing to compare with */
export function change(value: number | null): string {
  if (value === null) return '—'
  const pct = Math.round(value * 1000) / 10
  if (pct === 0) return '0%'
  return `${pct > 0 ? '+' : '−'}${Math.abs(pct).toLocaleString('en-US')}%`
}

/** "Mar 9". Dates from the API are UTC days. */
export function shortDate(iso: string): string {
  return new Date(`${iso}T00:00:00Z`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' })
}

/** "Mon, Mar 9" */
export function longDate(iso: string): string {
  return new Date(`${iso}T00:00:00Z`).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' })
}
