import { ArrowLeft } from 'lucide-react'
import { Link, useParams } from 'react-router'
import { CATEGORIES, useEvent } from '../features/catalog/api'
import { PerformanceRow } from '../features/catalog/components/PerformanceRow'
import { ApiError } from '../lib/api'
import { formatAgeRating, formatDuration, formatMoney, formatWeekday } from '../lib/format'
import { NotFoundPage } from './NotFoundPage'

export function EventPage() {
  const { slug = '' } = useParams()
  const query = useEvent(slug)

  if (query.isError) {
    if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
    return (
      <div className="mx-auto max-w-3xl px-4 py-20">
        <p className="brutal bg-pop-pink p-6 font-bold text-ink-900">
          Ce spectacle n’a pas pu être chargé. Réessayez dans un instant.
        </p>
      </div>
    )
  }

  if (query.isPending) {
    return (
      <div
        className="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-8 md:grid-cols-[22rem_1fr]"
        aria-busy="true"
      >
        <div className="brutal aspect-[3/4] animate-pulse bg-surface-alt" />
        <div className="space-y-4">
          <div className="h-16 w-3/4 animate-pulse bg-surface-alt" />
          <div className="h-40 animate-pulse bg-surface-alt" />
        </div>
      </div>
    )
  }

  const event = query.data
  const color = CATEGORIES.find((c) => c.value === event.category)?.color
  const upcoming = event.performances.filter((p) => p.status !== 'PAST')
  const past = event.performances.length - upcoming.length

  return (
    <article className="mx-auto max-w-7xl px-4 py-10 sm:px-8 md:py-14">
      <Link
        to="/programme"
        className="inline-flex items-center gap-2 text-sm font-bold tracking-[0.12em] uppercase hover:text-accent"
      >
        <ArrowLeft aria-hidden className="size-4" /> Programmation
      </Link>

      <div className="mt-8 grid gap-10 md:grid-cols-[minmax(16rem,22rem)_1fr] md:gap-14">
        <div className="md:sticky md:top-28 md:self-start">
          <div className="brutal -rotate-1 bg-paper-50 p-2">
            {event.posterUrl ? (
              <img
                src={event.posterUrl}
                alt={`Affiche de ${event.title}`}
                className="aspect-[3/4] w-full border-2 border-ink-900 object-cover"
              />
            ) : (
              <div className="aspect-[3/4] w-full bg-surface-alt" />
            )}
          </div>
        </div>

        <div>
          <span
            className="inline-block border-2 border-ink-900 px-2 py-0.5 text-xs font-bold tracking-[0.12em] text-ink-900 uppercase"
            style={{ background: color }}
          >
            {event.categoryLabel}
          </span>
          <h1 className="mt-4 font-display text-[clamp(3rem,8vw,6rem)] leading-[0.9]">
            {event.title}
          </h1>
          {event.subtitle ? (
            <p className="mt-3 -rotate-1 font-marker text-2xl text-accent">{event.subtitle}</p>
          ) : null}
          <span aria-hidden className="mt-6 block h-2 w-24 bg-pop-red" />

          <dl className="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3">
            {[
              ['Durée', formatDuration(event.durationMinutes)],
              ['Âge', formatAgeRating(event.ageRating)],
              [
                'Tarifs',
                event.fromPriceCents != null
                  ? `dès ${formatMoney(event.fromPriceCents, event.currency)}`
                  : '—',
              ],
            ].map(([term, value]) => (
              <div key={term} className="border-2 border-fg bg-surface p-3">
                <dt className="text-xs font-bold tracking-[0.12em] uppercase">{term}</dt>
                <dd className="mt-1 font-display text-2xl">{value}</dd>
              </div>
            ))}
          </dl>

          <div className="mt-8 space-y-4 text-lg">
            {event.description.split(/\n{2,}/).map((para, i) => (
              <p key={i}>{para}</p>
            ))}
          </div>

          <section aria-labelledby="dates-title" className="mt-12">
            <h2 id="dates-title" className="font-display text-4xl">
              Les dates
            </h2>
            <p className="mt-2 text-sm text-muted">
              Choisissez une représentation pour voir la salle et vos places.
              {past > 0 ? ` ${past} date${past > 1 ? 's' : ''} passée${past > 1 ? 's' : ''}.` : ''}
            </p>
            {upcoming.length === 0 ? (
              <p className="brutal mt-6 bg-surface p-6 font-bold">
                Plus aucune date à venir pour ce spectacle.
              </p>
            ) : (
              <ul className="brutal mt-6 bg-surface">
                {upcoming.map((performance) => (
                  <PerformanceRow
                    key={performance.id}
                    performance={performance}
                    title={formatWeekday(performance.startsAt)}
                    subtitle={performance.layoutName}
                    href={`/representations/${performance.id}`}
                    currency={event.currency}
                  />
                ))}
              </ul>
            )}
            <p className="mt-6 text-sm text-muted">
              Tarif réduit sur justificatif à l’entrée · Groupes de 10 à 30 personnes · Placement
              numéroté, fosse debout en configuration concert.
            </p>
          </section>
        </div>
      </div>
    </article>
  )
}
