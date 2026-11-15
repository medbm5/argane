import { Link } from 'react-router'
import { useStudioEvents } from '../../features/studio/api'
import { StudioPage } from '../../features/studio/StudioPage'
import { formatMoney, formatShortDate, formatTime } from '../../lib/format'

/** Studio landing: quick numbers and what is coming (the full dashboard is Slice 18). */
export function StudioOverviewPage() {
  const events = useStudioEvents()
  const list = events.data ?? []
  const upcoming = list
    .flatMap((e) => e.performances.map((p) => ({ ...p, title: e.title, eventId: e.id })))
    .filter((p) => p.status !== 'PAST' && p.status !== 'CANCELLED')
    .sort((a, b) => a.startsAt.localeCompare(b.startsAt))
    .slice(0, 6)
  const sold = list.reduce((n, e) => n + e.sold, 0)
  const revenue = list.reduce((n, e) => n + e.revenueCents, 0)

  return (
    <StudioPage kicker="saison 26/27" title="Vue d’ensemble">
      <dl className="grid gap-4 sm:grid-cols-3">
        {[
          ['Spectacles', String(list.length)],
          ['Billets vendus', String(sold)],
          ['Recettes', formatMoney(revenue, 'MAD')],
        ].map(([term, value]) => (
          <div key={term} className="brutal bg-surface p-5">
            <dt className="text-xs font-bold tracking-[0.12em] uppercase">{term}</dt>
            <dd className="tabular mt-1 font-display text-4xl">{value}</dd>
          </div>
        ))}
      </dl>
      <h2 className="mt-10 font-display text-3xl">Prochaines représentations</h2>
      <ul className="mt-4 space-y-2">
        {upcoming.map((p) => (
          <li
            key={p.id}
            className="brutal flex flex-wrap items-center justify-between gap-3 bg-surface px-4 py-3"
          >
            <Link
              to={`/studio/spectacles/${p.eventId}`}
              className="font-display text-xl hover:text-accent"
            >
              {p.title}
            </Link>
            <span className="text-sm">
              {formatShortDate(p.startsAt)} · {formatTime(p.startsAt)} · {p.sold} / {p.capacity} ·{' '}
              {p.status}
            </span>
          </li>
        ))}
      </ul>
    </StudioPage>
  )
}
