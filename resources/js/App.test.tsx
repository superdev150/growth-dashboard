import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { App } from './App'
import { makeApp, makeReport } from './test/fixture'

type Handler = (url: URL) => Response | Promise<Response>

let handler: Handler
const calls: URL[] = []

beforeEach(() => {
  calls.length = 0
  handler = () => Response.json(makeReport())
  vi.stubGlobal('fetch', vi.fn(async (input: string) => {
    const url = new URL(input, 'http://localhost')
    calls.push(url)
    return handler(url)
  }))
})

afterEach(() => vi.unstubAllGlobals())

function renderApp() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <App />
    </QueryClientProvider>,
  )
}

describe('App', () => {
  it('loads 30 days by default and leads with revenue', async () => {
    renderApp()

    expect(await screen.findByText('$7,426')).toBeInTheDocument()
    expect(calls[0].searchParams.get('days')).toBe('30')
    expect(screen.getByText('demo data')).toBeInTheDocument()
    expect(screen.getByText('1.60×')).toBeInTheDocument()
    expect(screen.getByText(/\$374\.75 from 1 campaign not tied to any app/)).toBeInTheDocument()
  })

  it('switches period and remembers it', async () => {
    const user = userEvent.setup()
    renderApp()
    await screen.findByText('$7,426')

    await user.click(screen.getByRole('radio', { name: '7 days' }))

    await waitFor(() => expect(calls.at(-1)?.searchParams.get('days')).toBe('7'))
    expect(localStorage.getItem('growth.days')).toBe('7')
  })

  it('starts from a remembered period, and ignores an edited one', async () => {
    localStorage.setItem('growth.days', '90')
    renderApp()
    await waitFor(() => expect(calls[0].searchParams.get('days')).toBe('90'))
  })

  it('ignores an invalid stored period', async () => {
    localStorage.setItem('growth.days', '"lots"')
    renderApp()
    await waitFor(() => expect(calls[0].searchParams.get('days')).toBe('30'))
  })

  it('shows gross or after-fees revenue', async () => {
    const user = userEvent.setup()
    renderApp()
    await screen.findByText('$7,426')

    await user.click(screen.getByRole('radio', { name: 'After fees' }))

    expect(screen.getByText('$5,198')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Proceeds by day' })).toBeInTheDocument()
  })

  it('explains partial numbers and keeps the rest', async () => {
    handler = () =>
      Response.json(
        makeReport({
          partial: true,
          apps: [makeApp('notes', 'Lumen Notes', 1), makeApp('trails', 'Pocket Trails', 0.5, 'Pocket Trails: RevenueCat rejected the key (401).')],
        }),
      )
    renderApp()

    expect(await screen.findByText('Some numbers are missing, so totals are partial.')).toBeInTheDocument()
    expect(screen.getByText('Pocket Trails: RevenueCat rejected the key (401).')).toBeInTheDocument()
    expect(screen.getByText(/Subscription numbers unavailable/)).toBeInTheDocument()
    expect(screen.getByText('$7,426')).toBeInTheDocument()
  })

  it('refreshes past the cache, and says so when refreshing too often', async () => {
    const user = userEvent.setup()
    renderApp()
    await screen.findByText('$7,426')

    handler = (url) => (url.searchParams.get('refresh') ? new Response('{}', { status: 429 }) : Response.json(makeReport()))
    await user.click(screen.getByRole('button', { name: 'Refresh' }))

    expect(calls.at(-1)?.searchParams.get('refresh')).toBe('1')
    expect(await screen.findByText(/Refreshed too often/)).toBeInTheDocument()
    expect(screen.getByText('$7,426')).toBeInTheDocument() // the cached numbers stay
  })

  it('offers a retry when the server cannot be reached', async () => {
    const user = userEvent.setup()
    handler = () => Promise.reject(new TypeError('Failed to fetch'))
    renderApp()

    expect(await screen.findByText('Could not reach the server. Check your connection.')).toBeInTheDocument()

    handler = () => Response.json(makeReport())
    await user.click(screen.getByRole('button', { name: 'Try again' }))
    expect(await screen.findByText('$7,426')).toBeInTheDocument()
  })
})
