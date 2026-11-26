import { fireEvent, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { StackedColumns, type Series } from './StackedColumns'

const dates = ['2026-03-08', '2026-03-09', '2026-03-10']
const series: Series[] = [
  { id: 'a', name: 'Alpha', color: 'blue', values: [10, 20, 0] },
  { id: 'b', name: 'Beta', color: 'orange', values: [5, 0, 7] },
]
const format = (v: number) => `$${v}`

function setup() {
  render(<StackedColumns title="Revenue by day" dates={dates} series={series} format={format} incomplete={new Set(['2026-03-10'])} />)
  return screen.getByTestId('plot')
}

describe('StackedColumns', () => {
  it('draws one segment per non-zero value', () => {
    const plot = setup()
    expect(plot.querySelectorAll('path')).toHaveLength(4)
  })

  it('describes the chart for screen readers', () => {
    const plot = setup()
    expect(plot).toHaveAttribute('aria-label', expect.stringContaining('Highest on Mon, Mar 9: $20'))
  })

  it('shows every series and the total for the hovered day', () => {
    setup()
    fireEvent.mouseEnter(screen.getByTestId('day-0'))

    const tip = within(screen.getByRole('status'))
    expect(tip.getByText('Sun, Mar 8')).toBeInTheDocument()
    expect(tip.getByText('$10')).toBeInTheDocument()
    expect(tip.getByText('$5')).toBeInTheDocument()
    expect(tip.getByText('$15')).toBeInTheDocument()
  })

  it('moves between days with the keyboard and marks days in progress', async () => {
    const user = userEvent.setup()
    const plot = setup()
    plot.focus()

    await user.keyboard('{End}')
    expect(screen.getByRole('status')).toHaveTextContent('Tue, Mar 10')
    expect(screen.getByRole('status')).toHaveTextContent('in progress')

    await user.keyboard('{ArrowLeft}')
    expect(screen.getByRole('status')).toHaveTextContent('Mon, Mar 9')

    await user.keyboard('{Escape}')
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('switches to a table with the same numbers', async () => {
    const user = userEvent.setup()
    setup()
    await user.click(screen.getByRole('button', { name: 'Table' }))

    const rows = screen.getAllByRole('row')
    expect(rows).toHaveLength(4) // header + 3 days
    expect(within(rows[1]).getAllByRole('cell').map((c) => c.textContent)).toEqual(['$10', '$5', '$15'])
    expect(screen.getByRole('button', { name: 'Chart' })).toHaveAttribute('aria-pressed', 'true')
  })

  it('names the series in a legend', () => {
    setup()
    expect(screen.getByRole('list')).toHaveTextContent('AlphaBeta')
  })
})
