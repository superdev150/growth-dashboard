import type { GrowthReport } from '../lib/api'
import { count, money, ratio } from '../lib/format'

const sum = (values: number[]) => values.reduce((a, b) => a + b, 0)

/** One row per app; ratios are computed from each app's sums. */
export function AppsTable({ report, colors, revenueKey }: { report: GrowthReport; colors: Record<string, string>; revenueKey: 'revenue' | 'proceeds' }) {
  const cur = report.currency
  return (
    <section className="card" aria-labelledby="apps-title">
      <h2 id="apps-title">By app</h2>
      <div className="table-scroll">
        <table className="data">
          <thead>
            <tr>
              <th scope="col">App</th>
              <th scope="col" className="num">{revenueKey === 'revenue' ? 'Revenue' : 'Proceeds'}</th>
              <th scope="col" className="num">Ad spend</th>
              <th scope="col" className="num">ROAS</th>
              <th scope="col" className="num">Installs</th>
              <th scope="col" className="num">Cost / install</th>
              <th scope="col" className="num">New customers</th>
            </tr>
          </thead>
          <tbody>
            {report.apps.map((app) => {
              const revenue = sum(app.series[revenueKey])
              const spend = sum(app.series.spend)
              const installs = sum(app.series.installs)
              return (
                <tr key={app.id}>
                  <th scope="row">
                    <span className="swatch" style={{ background: colors[app.id] }} aria-hidden="true" />
                    {app.name}
                  </th>
                  {app.error ? (
                    <td colSpan={6} className="muted">Subscription numbers unavailable · ad spend {money(spend, cur)}</td>
                  ) : (
                    <>
                      <td className="num">{money(revenue, cur)}</td>
                      <td className="num">{money(spend, cur)}</td>
                      <td className="num">{spend > 0 ? ratio(revenue / spend) : '—'}</td>
                      <td className="num">{count(installs)}</td>
                      <td className="num">{installs > 0 ? money(spend / installs, cur) : '—'}</td>
                      <td className="num">{count(sum(app.series.customers))}</td>
                    </>
                  )}
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
    </section>
  )
}
