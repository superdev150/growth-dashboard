import { useId, useLayoutEffect, useRef, useState, type KeyboardEvent } from 'react'
import { labelIndices, niceTicks } from '../lib/ticks'
import { longDate, shortDate } from '../lib/format'
import { Legend } from './Legend'

export interface Series {
  id: string
  name: string
  /** A CSS color, normally var(--series-N): the color follows the app, never its rank. */
  color: string
  values: number[]
}

interface Props {
  title: string
  subtitle?: string
  dates: string[]
  series: Series[]
  format: (value: number) => string
  /** Compact form for axis ticks. */
  formatTick?: (value: number) => string
  /** Days still being computed upstream: drawn lighter and marked in the tooltip and table. */
  incomplete?: ReadonlySet<string>
  height?: number
}

const AXIS_WIDTH = 52
const AXIS_HEIGHT = 24
const TOP_PAD = 8
const MAX_COLUMN = 24
const GAP = 2 // surface gap between stacked segments
const RADIUS = 4

/** A column segment with a rounded data end and a square base. */
function segmentPath(x: number, y: number, w: number, h: number, rounded: boolean): string {
  const r = rounded ? Math.min(RADIUS, h, w / 2) : 0
  return `M${x},${y + h}V${y + r}Q${x},${y} ${x + r},${y}H${x + w - r}Q${x + w},${y} ${x + w},${y + r}V${y + h}Z`
}

/**
 * Stacked columns, one per day, one segment per series. Hover, focus and arrow keys show a tooltip for the day;
 * "Table" swaps the plot for the same numbers as a table (the accessible and exact view).
 */
export function StackedColumns({ title, subtitle, dates, series, format, formatTick = format, incomplete, height = 220 }: Props) {
  const box = useRef<HTMLDivElement>(null)
  const [width, setWidth] = useState(640)
  const [active, setActive] = useState<number | null>(null)
  const [table, setTable] = useState(false)
  const titleId = useId()

  useLayoutEffect(() => {
    const element = box.current
    if (!element || typeof ResizeObserver === 'undefined') return
    const observer = new ResizeObserver(([entry]) => setWidth(Math.max(240, Math.round(entry.contentRect.width))))
    observer.observe(element)
    return () => observer.disconnect()
  }, [])

  const totals = dates.map((_, i) => series.reduce((sum, s) => sum + (s.values[i] ?? 0), 0))
  const ticks = niceTicks(Math.max(...totals, 0))
  const top = ticks[ticks.length - 1]

  const plotWidth = width - AXIS_WIDTH
  const plotHeight = height - AXIS_HEIGHT - TOP_PAD
  const band = plotWidth / Math.max(dates.length, 1)
  const column = Math.max(2, Math.min(MAX_COLUMN, band * 0.66))
  const y = (value: number) => TOP_PAD + plotHeight - (value / top) * plotHeight
  const labels = new Set(labelIndices(dates.length, Math.max(2, Math.floor(plotWidth / 64))))

  const peak = totals.indexOf(Math.max(...totals))
  const summary = `${title}, ${dates.length} days. Highest on ${dates[peak] ? longDate(dates[peak]) : '—'}: ${format(totals[peak] ?? 0)}.`

  function onKeyDown(event: KeyboardEvent) {
    const last = dates.length - 1
    const moves: Record<string, number> = { ArrowRight: 1, ArrowLeft: -1 }
    if (event.key in moves) {
      event.preventDefault()
      setActive((current) => Math.min(last, Math.max(0, (current ?? (moves[event.key] > 0 ? -1 : last + 1)) + moves[event.key])))
    } else if (event.key === 'Home' || event.key === 'End') {
      event.preventDefault()
      setActive(event.key === 'Home' ? 0 : last)
    } else if (event.key === 'Escape') {
      setActive(null)
    }
  }

  const tooltipLeft = active === null ? 0 : Math.min(Math.max(AXIS_WIDTH + band * (active + 0.5), 110), width - 110) // half the tooltip, so it stays inside

  return (
    <section className="card chart" aria-labelledby={titleId}>
      <header className="chart-head">
        <div>
          <h2 id={titleId}>{title}</h2>
          {subtitle && <p className="muted">{subtitle}</p>}
        </div>
        <button type="button" className="ghost" aria-pressed={table} onClick={() => setTable((t) => !t)}>
          {table ? 'Chart' : 'Table'}
        </button>
      </header>

      <Legend items={series.map(({ id, name, color }) => ({ id, name, color }))} />

      {table ? (
        <div className="table-scroll">
          <table className="data">
            <thead>
              <tr>
                <th scope="col">Day</th>
                {series.map((s) => (
                  <th scope="col" key={s.id} className="num">
                    {s.name}
                  </th>
                ))}
                <th scope="col" className="num">
                  Total
                </th>
              </tr>
            </thead>
            <tbody>
              {dates.map((date, i) => (
                <tr key={date}>
                  <th scope="row">
                    {longDate(date)}
                    {incomplete?.has(date) && <span className="muted"> (in progress)</span>}
                  </th>
                  {series.map((s) => (
                    <td key={s.id} className="num">
                      {format(s.values[i] ?? 0)}
                    </td>
                  ))}
                  <td className="num">{format(totals[i])}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div ref={box} className="plot" onMouseLeave={() => setActive(null)}>
          <svg
            width={width}
            height={height}
            role="img"
            aria-label={summary}
            tabIndex={0}
            onKeyDown={onKeyDown}
            onBlur={() => setActive(null)}
            data-testid="plot"
          >
            {ticks.map((tick) => (
              <g key={tick}>
                <line className={tick === 0 ? 'baseline' : 'grid'} x1={AXIS_WIDTH} x2={width} y1={y(tick)} y2={y(tick)} />
                <text className="tick" x={AXIS_WIDTH - 8} y={y(tick)} dy="0.32em" textAnchor="end">
                  {formatTick(tick)}
                </text>
              </g>
            ))}

            {dates.map((date, i) => {
              const x = AXIS_WIDTH + band * i + (band - column) / 2
              const lastVisible = series.map((s) => s.values[i] ?? 0).findLastIndex((v) => v > 0)
              let base = 0
              return (
                <g key={date} className={incomplete?.has(date) ? 'incomplete' : undefined} opacity={active !== null && active !== i ? 0.55 : 1}>
                  {series.map((s, k) => {
                    const value = s.values[i] ?? 0
                    if (value <= 0) return null
                    const yTop = y(base + value)
                    const yBottom = y(base)
                    base += value
                    // Every segment but the lowest leaves a surface-colored gap under it.
                    const h = Math.max(0, yBottom - yTop - (k > 0 && base > value ? GAP : 0))
                    return <path key={s.id} d={segmentPath(x, yTop, column, h, k === lastVisible)} fill={s.color} />
                  })}
                  {labels.has(i) && (
                    // The outer labels align inward so they never run past the plot edges.
                    <text
                      className="tick"
                      x={i === 0 ? x : i === dates.length - 1 ? x + column : x + column / 2}
                      y={height - 6}
                      textAnchor={i === 0 ? 'start' : i === dates.length - 1 ? 'end' : 'middle'}
                    >
                      {shortDate(date)}
                    </text>
                  )}
                  {/* The hit target is the whole day band, far larger than a thin column. */}
                  <rect
                    className="hit"
                    x={AXIS_WIDTH + band * i}
                    y={TOP_PAD}
                    width={band}
                    height={plotHeight}
                    onMouseEnter={() => setActive(i)}
                    data-testid={`day-${i}`}
                  />
                </g>
              )
            })}
          </svg>

          {active !== null && dates[active] && (
            <div className="tooltip" role="status" style={{ left: tooltipLeft }}>
              <strong>
                {longDate(dates[active])}
                {incomplete?.has(dates[active]) && <span className="muted"> · in progress</span>}
              </strong>
              {series.map((s) => (
                <div className="tooltip-row" key={s.id}>
                  <span className="swatch" style={{ background: s.color }} aria-hidden="true" />
                  <span>{s.name}</span>
                  <span className="num">{format(s.values[active] ?? 0)}</span>
                </div>
              ))}
              {series.length > 1 && (
                <div className="tooltip-row total">
                  <span />
                  <span>Total</span>
                  <span className="num">{format(totals[active])}</span>
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </section>
  )
}
