import { Link } from 'react-router'
import type { EventListItem } from '../../../lib/api/types'
import { PerformanceRow } from '../components/PerformanceRow'
import { SectionTitle } from './LandingGrid'

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
    <section aria-labelledby="upcoming-title" className="border-b-2 border-ink-900">
      <div className="flex flex-wrap items-end justify-between gap-4 border-b-2 border-ink-900 px-4 pt-14 pb-8 sm:px-8">
        <SectionTitle id="upcoming-title" kicker="les prochains soirs" bar="bg-pop-red">
          Prochainement
        </SectionTitle>
        <Link
          to="/programme"
          className="font-display text-lg tracking-[0.1em] uppercase underline decoration-2 underline-offset-8 hover:text-accent"
        >
          Toute la programmation →
        </Link>
      </div>
      {isLoading ? (
        <ul aria-busy="true" aria-label="Chargement des représentations">
          {Array.from({ length: 3 }, (_, i) => (
            <li
              key={i}
              className="h-20 animate-pulse border-b-2 border-ink-900 bg-surface-alt/50"
            />
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
