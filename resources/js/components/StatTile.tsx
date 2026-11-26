import type { Comparison } from '../lib/api'
import { change } from '../lib/format'
import { Sparkline } from './Sparkline'

interface Props {
  label: string
  comparison: Comparison
  format: (value: number) => string
  /** Which direction is good news. Spend going up is neither. */
  goodWhen: 'up' | 'down' | 'neither'
  trend?: number[]
  hero?: boolean
  periodLabel: string
}

type Tone = 'good' | 'bad' | 'neutral'

export function deltaTone(value: number | null, goodWhen: Props['goodWhen']): Tone {
  if (value === null || Math.abs(value) < 0.005 || goodWhen === 'neither') return 'neutral'
  return (value > 0) === (goodWhen === 'up') ? 'good' : 'bad'
}

export function StatTile({ label, comparison, format, goodWhen, trend, hero = false, periodLabel }: Props) {
  const tone = deltaTone(comparison.change, goodWhen)
  const arrow = comparison.change === null || Math.abs(comparison.change) < 0.005 ? '' : comparison.change > 0 ? '▲' : '▼'
  const previous = comparison.previous === null ? 'nothing to compare' : `${format(comparison.previous)} ${periodLabel}`

  return (
    <article className={hero ? 'card tile hero' : 'card tile'}>
      <h3 className="tile-label">{label}</h3>
      <p className="tile-value">{comparison.value === null ? '—' : format(comparison.value)}</p>
      <p className={`delta ${tone}`}>
        {arrow && <span aria-hidden="true">{arrow} </span>}
        <span>{change(comparison.change)}</span>
        <span className="muted"> vs {previous}</span>
        {tone !== 'neutral' && <span className="sr-only"> ({tone === 'good' ? 'better' : 'worse'})</span>}
      </p>
      {trend && <Sparkline values={trend} />}
    </article>
  )
}
