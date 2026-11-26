/**
 * A small trend under a stat tile. Decorative: the tile states the number, the charts and tables carry the detail.
 * The line stretches with its box; the end dot is an HTML element placed by percentage, so it stays round.
 */
export function Sparkline({ values }: { values: number[] }) {
  if (values.length < 2) return null
  const max = Math.max(...values)
  const min = Math.min(...values)
  const span = max - min || 1
  // 0–100 in both axes, inset so the line and the dot never touch the edges.
  const points = values.map((v, i) => [(i / (values.length - 1)) * 100, 8 + (1 - (v - min) / span) * 84])
  const [lastX, lastY] = points[points.length - 1]

  return (
    <div className="sparkline" aria-hidden="true">
      <svg viewBox="0 0 100 100" preserveAspectRatio="none">
        <polyline points={points.map((p) => p.join(',')).join(' ')} />
      </svg>
      <span className="spark-dot" style={{ left: `${lastX}%`, top: `${lastY}%` }} />
    </div>
  )
}
