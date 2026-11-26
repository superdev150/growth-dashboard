/**
 * Clean axis ticks from 0 to at least `max`: steps of 1, 2, 2.5 or 5 × a power of ten, so the labels read
 * 0 / 50 / 100 / 150 rather than 0 / 47.3 / 94.6.
 */
export function niceTicks(max: number, target = 4): number[] {
  if (!Number.isFinite(max) || max <= 0) return [0, 1]

  const raw = max / target
  const magnitude = 10 ** Math.floor(Math.log10(raw))
  const normalized = raw / magnitude
  const step = (normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 2.5 ? 2.5 : normalized <= 5 ? 5 : 10) * magnitude

  const ticks: number[] = []
  for (let value = 0; value < max + step * 0.999; value += step) {
    ticks.push(Math.round(value * 1e6) / 1e6)
  }
  return ticks
}

/** Indices of the x labels to print: first, last and evenly spaced ones in between, at most `max`. */
export function labelIndices(length: number, max: number): number[] {
  if (length <= max) return Array.from({ length }, (_, i) => i)
  const every = Math.ceil((length - 1) / (max - 1))
  const indices: number[] = []
  for (let i = 0; i < length - 1; i += every) indices.push(i)
  // Drop the one before last if it would crowd the final label.
  if (length - 1 - indices[indices.length - 1] < every / 2) indices.pop()
  indices.push(length - 1)
  return indices
}
