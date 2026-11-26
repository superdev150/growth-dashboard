import type { GrowthReport } from '../lib/api'

/** What is missing from the numbers, and why. Shown above everything, with an icon and words, never color alone. */
export function Notices({ report }: { report: GrowthReport }) {
  const problems = [
    ...report.apps.filter((app) => app.error).map((app) => ({ key: app.id, text: app.error as string })),
    ...(report.ads.error ? [{ key: 'ads', text: `Ads: ${report.ads.error}` }] : []),
  ]
  if (problems.length === 0) return null

  return (
    <div className="notice warning" role="status">
      <span className="notice-icon" aria-hidden="true">!</span>
      <div>
        <strong>Some numbers are missing, so totals are partial.</strong>
        <ul>
          {problems.map((p) => (
            <li key={p.key}>{p.text}</li>
          ))}
        </ul>
      </div>
    </div>
  )
}
