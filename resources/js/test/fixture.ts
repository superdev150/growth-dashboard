import type { AppReport, GrowthReport } from '../lib/api'

const dates = ['2026-03-04', '2026-03-05', '2026-03-06', '2026-03-07', '2026-03-08', '2026-03-09', '2026-03-10']

function app(id: string, name: string, scale: number, error: string | null = null): AppReport {
  const series = (base: number) => dates.map((_, i) => (error && base > 50 ? 0 : Math.round(base * scale * (1 + i / 10) * 100) / 100))
  return {
    id,
    name,
    error,
    incomplete: ['2026-03-10'],
    series: { spend: series(40), installs: series(20), revenue: series(100), proceeds: series(70), customers: series(10), trials: series(4) },
    previous: { spend: 250, installs: 120, revenue: 600, proceeds: 420, customers: 60, trials: 25 },
  }
}

export function makeReport(overrides: Partial<GrowthReport> = {}): GrowthReport {
  return {
    driver: 'demo',
    currency: 'USD',
    days: 7,
    dates,
    generated_at: '2026-03-10T15:00:00+00:00',
    partial: false,
    totals: {
      spend: { value: 4655.2, previous: 4574, change: 0.0178 },
      installs: { value: 4039, previous: 3982, change: 0.0143 },
      revenue: { value: 7426.4, previous: 7448, change: -0.0029 },
      proceeds: { value: 5198.48, previous: 5213.6, change: -0.0029 },
      customers: { value: 1823, previous: 1728, change: 0.055 },
      trials: { value: 600, previous: 610, change: -0.0164 },
      cpi: { value: 1.15, previous: 1.15, change: 0.003 },
      roas: { value: 1.6, previous: 1.63, change: -0.0184 },
    },
    apps: [app('notes', 'Lumen Notes', 1), app('trails', 'Pocket Trails', 0.5)],
    ads: {
      error: null,
      account_currency: 'USD',
      rate: null,
      campaigns: [{ id: 'c1', name: 'LN · App installs · US', app: 'notes', status: 'ACTIVE', spend: 2245, impressions: 140000, clicks: 3848, installs: 1203, cpi: 1.87 }],
      unassigned: { spend: 374.75, campaigns: 1 },
    },
    ...overrides,
  }
}

export { app as makeApp }
