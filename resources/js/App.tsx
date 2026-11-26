import { useMemo } from 'react'
import { AppsTable } from './components/AppsTable'
import { CampaignsTable } from './components/CampaignsTable'
import { Notices } from './components/Notices'
import { Segmented } from './components/Segmented'
import { StackedColumns, type Series } from './components/StackedColumns'
import { StatTile } from './components/StatTile'
import { ThemeToggle } from './components/ThemeToggle'
import { useGrowth } from './hooks/useGrowth'
import { usePersistentState } from './hooks/usePersistentState'
import { ApiError, PERIODS, totalByDay, type GrowthReport, type Metric, type Period } from './lib/api'
import { count, money, ratio } from './lib/format'

type RevenueKey = 'revenue' | 'proceeds'

const isPeriod = (v: unknown): v is Period => PERIODS.includes(v as Period)
const isRevenueKey = (v: unknown): v is RevenueKey => v === 'revenue' || v === 'proceeds'

/** Colors are assigned by the app's position in the configuration, so filtering never repaints an app. */
const SERIES_COLORS = ['var(--series-1)', 'var(--series-2)', 'var(--series-3)', 'var(--series-4)']

export function App() {
  const [days, setDays] = usePersistentState<Period>('growth.days', 30, isPeriod)
  const [revenueKey, setRevenueKey] = usePersistentState<RevenueKey>('growth.revenue', 'revenue', isRevenueKey)
  const { report, refresh } = useGrowth(days)
  const data = report.data

  return (
    <div className="page">
      <header className="top">
        <div>
          <h1>Growth</h1>
          <p className="muted">
            Ad spend and subscription revenue, per app
            {data?.driver === 'demo' && <span className="badge">demo data</span>}
          </p>
        </div>
        <div className="controls">
          <Segmented<Period> label="Period" value={days} onChange={setDays} options={PERIODS.map((p) => ({ value: p, label: `${p} days` }))} />
          <Segmented<RevenueKey>
            label="Revenue"
            value={revenueKey}
            onChange={setRevenueKey}
            options={[
              { value: 'revenue', label: 'Gross' },
              { value: 'proceeds', label: 'After fees' },
            ]}
          />
          <button type="button" className="ghost" onClick={() => refresh.mutate()} disabled={refresh.isPending || report.isFetching}>
            {refresh.isPending ? 'Refreshing…' : 'Refresh'}
          </button>
          <ThemeToggle />
        </div>
      </header>

      {refresh.error && (
        <div className="notice" role="status">
          <span className="notice-icon" aria-hidden="true">i</span>
          {refresh.error instanceof ApiError ? refresh.error.message : 'Refresh failed.'}
        </div>
      )}

      {report.isPending ? (
        <p className="card muted" aria-busy="true">Loading…</p>
      ) : report.isError && !data ? (
        <div className="notice warning" role="alert">
          <span className="notice-icon" aria-hidden="true">!</span>
          <div>
            <strong>{report.error instanceof ApiError ? report.error.message : 'Something went wrong.'}</strong>{' '}
            <button type="button" className="link" onClick={() => report.refetch()}>
              Try again
            </button>
          </div>
        </div>
      ) : data ? (
        <Dashboard report={data} revenueKey={revenueKey} stale={report.isPlaceholderData} />
      ) : null}
    </div>
  )
}

function Dashboard({ report, revenueKey, stale }: { report: GrowthReport; revenueKey: RevenueKey; stale: boolean }) {
  const cur = report.currency
  const periodLabel = `the ${report.days} days before`
  const colors = useMemo(() => Object.fromEntries(report.apps.map((app, i) => [app.id, SERIES_COLORS[i % SERIES_COLORS.length]])), [report.apps])
  // Today is partial in every source (ad platforms report with a delay too): marked in the charts, left out of trends.
  const today = report.dates[report.dates.length - 1]
  const incomplete = useMemo(() => new Set([today, ...report.apps.flatMap((app) => app.incomplete)]), [today, report.apps])
  const trend = (metric: Metric) => totalByDay(report, metric).filter((_, i) => !incomplete.has(report.dates[i]))
  const seriesOf = (metric: Metric): Series[] => report.apps.map((app) => ({ id: app.id, name: app.name, color: colors[app.id], values: app.series[metric] }))
  const fmtMoney = (v: number) => money(v, cur)
  const fmtTick = (v: number) => money(v, cur, true).replace(/\.00$/, '')

  return (
    <main className={stale ? 'stale' : undefined} aria-busy={stale}>
      <Notices report={report} />

      <section className="tiles" aria-label="Key figures">
        <StatTile
          hero
          label={revenueKey === 'revenue' ? 'Revenue' : 'Proceeds after store fees'}
          comparison={report.totals[revenueKey]}
          format={fmtMoney}
          goodWhen="up"
          trend={trend(revenueKey)}
          periodLabel={periodLabel}
        />
        <StatTile label="Ad spend" comparison={report.totals.spend} format={fmtMoney} goodWhen="neither" trend={trend('spend')} periodLabel={periodLabel} />
        <StatTile label="Return on ad spend" comparison={report.totals.roas} format={ratio} goodWhen="up" periodLabel={periodLabel} />
        <StatTile label="Installs from ads" comparison={report.totals.installs} format={(v) => count(v)} goodWhen="up" trend={trend('installs')} periodLabel={periodLabel} />
        <StatTile label="Cost per install" comparison={report.totals.cpi} format={fmtMoney} goodWhen="down" periodLabel={periodLabel} />
      </section>

      <div className="charts">
        <StackedColumns
          title={revenueKey === 'revenue' ? 'Revenue by day' : 'Proceeds by day'}
          subtitle="Today is still being counted."
          dates={report.dates}
          series={seriesOf(revenueKey)}
          format={fmtMoney}
          formatTick={fmtTick}
          incomplete={incomplete}
        />
        <StackedColumns
          title="Ad spend by day"
          subtitle="Today is still being counted."
          dates={report.dates}
          series={seriesOf('spend')}
          format={fmtMoney}
          formatTick={fmtTick}
          incomplete={incomplete}
        />
      </div>

      <AppsTable report={report} colors={colors} revenueKey={revenueKey} />
      <CampaignsTable report={report} />

      <p className="muted footnote">
        Updated {new Date(report.generated_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })}. Days are UTC. Upstream answers are cached for 10
        minutes; Refresh skips the cache.
      </p>
    </main>
  )
}
