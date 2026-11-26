import { describe, expect, it } from 'vitest'
import { labelIndices, niceTicks } from './ticks'

describe('niceTicks', () => {
  it('rounds to clean steps that cover the maximum', () => {
    expect(niceTicks(380)).toEqual([0, 100, 200, 300, 400])
    expect(niceTicks(171)).toEqual([0, 50, 100, 150, 200])
    expect(niceTicks(9)).toEqual([0, 2.5, 5, 7.5, 10])
    expect(niceTicks(4)).toEqual([0, 1, 2, 3, 4])
  })
  it('never ends below the data', () => {
    for (const max of [1, 7, 33, 99.9, 101, 2500, 123456]) {
      const ticks = niceTicks(max)
      expect(ticks[ticks.length - 1]).toBeGreaterThanOrEqual(max)
      expect(ticks.length).toBeLessThanOrEqual(6)
    }
  })
  it('handles an empty chart', () => {
    expect(niceTicks(0)).toEqual([0, 1])
    expect(niceTicks(Number.NaN)).toEqual([0, 1])
  })
})

describe('labelIndices', () => {
  it('labels everything when it fits', () => {
    expect(labelIndices(5, 8)).toEqual([0, 1, 2, 3, 4])
  })
  it('always keeps the first and last day and spaces the rest', () => {
    const indices = labelIndices(30, 7)
    expect(indices[0]).toBe(0)
    expect(indices[indices.length - 1]).toBe(29)
    expect(indices.length).toBeLessThanOrEqual(7)
  })
})
