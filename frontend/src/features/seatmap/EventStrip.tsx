import { mediaUrl } from '../../lib/api'
import { Link } from 'react-router'
import { Pill } from '../../design-system/primitives/Pill'
import { formatShortDate, formatTime, venueDay } from '../../lib/format'
import type { EventDetail, PerformanceDetail } from '../../lib/api/types'

/**
 * Event card with date pills and time slots: switching performance swaps the plan.
 * Pills are links (each performance has its own URL).
 */
export function EventStrip({
  performance,
  event,
}: {
  performance: PerformanceDetail
  event: EventDetail | undefined
}) {
  const bookable = (event?.performances ?? []).filter(
    (p) => p.status !== 'PAST' && p.status !== 'CANCELLED',
  )
  const days = new Map<string, typeof bookable>()
  for (const p of bookable) {
    const day = venueDay(p.startsAt)
    days.set(day, [...(days.get(day) ?? []), p])
  }
  const currentDay = venueDay(performance.startsAt)
  const slots = days.get(currentDay) ?? []

  return (
    <section aria-label="Spectacle et date" className="flex flex-col gap-5">
      <Link to={`/spectacles/${performance.eventSlug}`} className="group flex items-center gap-4">
        {performance.posterUrl ? (
          <img
            src={mediaUrl(performance.posterUrl)}
            alt=""
            className="h-24 w-18 shrink-0 border-2 border-paper-100 object-cover"
          />
        ) : null}
        <div className="min-w-0">
          <p className="text-xs font-bold tracking-[0.14em] text-pop-yellow uppercase">
            {performance.categoryLabel}
          </p>
          <h1 className="font-display text-3xl leading-none break-words group-hover:text-pop-yellow sm:text-4xl">
            {performance.eventTitle}
          </h1>
          <p className="mt-1 text-sm text-muted">
            {formatShortDate(performance.startsAt)} · {formatTime(performance.startsAt)} ·{' '}
            {performance.layoutName}
          </p>
        </div>
      </Link>

      {days.size > 1 ? (
        <nav aria-label="Autres dates" className="flex flex-wrap gap-2">
          {[...days.entries()].map(([day, list]) => {
            const first = list[0]
            if (!first) return null
            const selected = day === currentDay
            return (
              <Pill key={day} asLink={`/representations/${first.id}`} selected={selected}>
                {formatShortDate(first.startsAt)}
              </Pill>
            )
          })}
        </nav>
      ) : null}

      {slots.length > 1 ? (
        <nav aria-label="Horaires" className="flex flex-wrap gap-2">
          {slots.map((slot) => (
            <Pill
              key={slot.id}
              asLink={`/representations/${slot.id}`}
              selected={slot.id === performance.id}
            >
              {formatTime(slot.startsAt)}
            </Pill>
          ))}
        </nav>
      ) : null}
    </section>
  )
}
