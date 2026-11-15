import { Link } from 'react-router'
import type { EventListItem } from '../../../lib/api/types'
import { PerformanceRow } from '../components/PerformanceRow'

export function UpcomingPerformances({
  events,
  isLoading,
}: {
  events: EventListItem[]
  isLoading: boolean
}) {
  const upcoming = events
    .filter((e) => e.nextPerformance)
    .sort((a, b) =>
      (a.nextPerformance?.startsAt ?? '').localeCompare(b.nextPerformance?.startsAt ?? ''),
    )
    .slice(0, 6)

  return (
    <section aria-labelledby="upcoming-title" className="border-b border-line">
      <div className="flex items-baseline justify-between gap-4 border-b border-line px-4 py-5 sm:px-8">
        <h2 id="upcoming-title" className="font-display text-3xl">
          Prochainement
        </h2>
        <Link to="/programme" className="text-sm text-accent hover:underline">
          Toute la programmation
        </Link>
      </div>
      {isLoading ? (
        <ul aria-busy="true" aria-label="Chargement des représentations">
          {Array.from({ length: 3 }, (_, i) => (
            <li key={i} className="h-20 animate-pulse border-b border-line bg-surface-alt/50" />
          ))}
        </ul>
      ) : upcoming.length === 0 ? (
        <p className="px-4 py-10 text-muted sm:px-8">
          Le programme de la saison se prépare. Revenez très vite.
        </p>
      ) : (
        <ul>
          {upcoming.map((event) =>
            event.nextPerformance ? (
              <PerformanceRow
                key={event.slug}
                performance={event.nextPerformance}
                title={event.title}
                subtitle={event.subtitle}
                href={`/spectacles/${event.slug}`}
                currency={event.currency}
              />
            ) : null,
          )}
        </ul>
      )}
    </section>
  )
}
