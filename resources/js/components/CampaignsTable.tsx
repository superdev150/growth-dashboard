import type { GrowthReport } from '../lib/api'
import { count, money } from '../lib/format'

export function CampaignsTable({ report }: { report: GrowthReport }) {
  const { ads, currency } = report
  const names = Object.fromEntries(report.apps.map((app) => [app.id, app.name]))
  if (ads.error) return null

  return (
    <section className="card" aria-labelledby="campaigns-title">
      <h2 id="campaigns-title">Campaigns</h2>
      {ads.campaigns.length === 0 ? (
        <p className="muted">No campaign spent in this period.</p>
      ) : (
        <div className="table-scroll">
          <table className="data">
            <thead>
              <tr>
                <th scope="col">Campaign</th>
                <th scope="col">App</th>
                <th scope="col" className="num">Spend</th>
                <th scope="col" className="num">Clicks</th>
                <th scope="col" className="num">Installs</th>
                <th scope="col" className="num">Cost / install</th>
              </tr>
            </thead>
            <tbody>
              {ads.campaigns.map((c) => (
                <tr key={c.id}>
                  <th scope="row">
                    {c.name}
                    {c.status !== 'ACTIVE' && <span className="badge">{c.status.toLowerCase()}</span>}
                  </th>
                  <td>{names[c.app] ?? c.app}</td>
                  <td className="num">{money(c.spend, currency)}</td>
                  <td className="num">{count(c.clicks)}</td>
                  <td className="num">{count(c.installs)}</td>
                  <td className="num">{c.cpi === null ? '—' : money(c.cpi, currency)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <p className="muted footnote">
        {ads.unassigned.campaigns > 0 &&
          `${money(ads.unassigned.spend, currency)} from ${ads.unassigned.campaigns} campaign${ads.unassigned.campaigns > 1 ? 's' : ''} not tied to any app is left out of every total. `}
        {ads.rate &&
          `Spend converted from ${ads.account_currency} at ${ads.rate.per_usd} per USD (${ads.rate.source}${ads.rate.date ? `, ${ads.rate.date}` : ''}).`}
      </p>
    </section>
  )
}
