import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { deltaTone, StatTile } from './StatTile'

describe('deltaTone', () => {
  it('depends on whether up is good', () => {
    expect(deltaTone(0.1, 'up')).toBe('good')
    expect(deltaTone(0.1, 'down')).toBe('bad') // cost per install went up
    expect(deltaTone(-0.1, 'down')).toBe('good')
    expect(deltaTone(0.1, 'neither')).toBe('neutral') // spending more is a choice, not news
  })
  it('treats tiny or unknown changes as neutral', () => {
    expect(deltaTone(0.004, 'up')).toBe('neutral')
    expect(deltaTone(null, 'up')).toBe('neutral')
  })
})

describe('StatTile', () => {
  it('says better or worse in words, not only in color', () => {
    render(<StatTile label="Cost per install" comparison={{ value: 1.3, previous: 1.1, change: 0.18 }} format={(v) => `$${v.toFixed(2)}`} goodWhen="down" periodLabel="before" />)

    expect(screen.getByText('$1.30')).toBeInTheDocument()
    expect(screen.getByText('+18%')).toBeInTheDocument()
    expect(screen.getByText('(worse)', { exact: false })).toBeInTheDocument()
  })
  it('handles a metric with no previous period', () => {
    render(<StatTile label="ROAS" comparison={{ value: null, previous: null, change: null }} format={String} goodWhen="up" periodLabel="before" />)

    expect(screen.getAllByText('—')).toHaveLength(2)
    expect(screen.getByText('vs nothing to compare', { exact: false })).toBeInTheDocument()
  })
})
