/** The JSON contract of GET /api/growth (see app/Growth/GrowthReport.php). */

export type Metric = 'spend' | 'installs' | 'revenue' | 'proceeds' | 'customers' | 'trials'
export type Ratio = 'cpi' | 'roas'
export type Period = 7 | 30 | 90

export const PERIODS: readonly Period[] = [7, 30, 90]

export interface Comparison {
  value: number | null
  previous: number | null
  /** Relative change, e.g. 0.042 = +4.2 %. Null when there is nothing to compare with. */
  change: number | null
}

export interface AppReport {
  id: string
  name: string
  /** Why this app's subscription numbers are missing; the rest of the report is still valid. */
  error: string | null
  /** Dates the provider is still computing (usually today). */
  incomplete: string[]
  series: Record<Metric, number[]>
  previous: Record<Metric, number>
}

export interface Campaign {
  id: string
  name: string
  app: string
  status: string
  spend: number
  impressions: number
  clicks: number
  installs: number
  cpi: number | null
}

export interface AdsReport {
  error: string | null
  account_currency: string | null
  rate: { factor: number; per_usd: number; source: string; date: string } | null
  campaigns: Campaign[]
  unassigned: { spend: number; campaigns: number }
}

export interface GrowthReport {
  driver: 'demo' | 'live'
  currency: string
  days: Period
  dates: string[]
  generated_at: string
  partial: boolean
  totals: Record<Metric | Ratio, Comparison>
  apps: AppReport[]
  ads: AdsReport
}

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

export async function fetchGrowth(days: Period, refresh = false, signal?: AbortSignal): Promise<GrowthReport> {
  const params = new URLSearchParams({ days: String(days) })
  if (refresh) params.set('refresh', '1')

  let response: Response
  try {
    response = await fetch(`/api/growth?${params}`, { headers: { Accept: 'application/json' }, signal })
  } catch (error) {
    if ((error as Error).name === 'AbortError') throw error
    throw new ApiError('Could not reach the server. Check your connection.', 0)
  }

  if (response.status === 429) {
    throw new ApiError(refresh ? 'Refreshed too often. Showing the cached numbers; try again in a minute.' : 'Too many requests. Try again in a minute.', 429)
  }
  if (!response.ok) {
    throw new ApiError(`The server answered ${response.status}.`, response.status)
  }
  return (await response.json()) as GrowthReport
}

/** Sum of every app's series, day by day. */
export function totalByDay(report: GrowthReport, metric: Metric): number[] {
  return report.dates.map((_, i) => report.apps.reduce((sum, app) => sum + (app.series[metric][i] ?? 0), 0))
}
