import { mediaUrl } from '../../../lib/api'
import { ArrowRight } from 'lucide-react'
import { Link } from 'react-router'
import { StatusBadge } from '../../../design-system/primitives/Badge'
import { formatDuration, formatLongDate, formatMoney, formatTime } from '../../../lib/format'
import type { EventListItem } from '../../../lib/api/types'
import { CATEGORIES } from '../api'

/** A programme entry: pasted poster, category tag, title, next date, from-price. */
export function EventCard({ event }: { event: EventListItem }) {
  const color = CATEGORIES.find((c) => c.value === event.category)?.color
  const next = event.nextPerformance

  return (
    <li>
      <Link
        to={`/spectacles/${event.slug}`}
        className="group grid gap-5 sm:grid-cols-[10rem_1fr] md:grid-cols-[12rem_1fr]"
      >
        <div className="brutal overflow-hidden bg-paper-50">
          {event.posterUrl ? (
            <img
              src={mediaUrl(event.posterUrl)}
              alt=""
              className="aspect-[3/4] w-full object-cover transition-transform duration-500 ease-theatre group-hover:scale-105"
            />
          ) : (
            <div className="aspect-[3/4] w-full bg-surface-alt" />
          )}
        </div>
        <div className="flex flex-col">
          <span
            className="self-start border-2 border-ink-900 px-2 py-0.5 text-xs font-bold tracking-[0.12em] text-ink-900 uppercase"
            style={{ background: color }}
          >
            {event.categoryLabel}
          </span>
          <h2 className="mt-3 font-display text-4xl leading-none group-hover:text-accent md:text-5xl">
            {event.title}
          </h2>
          {event.subtitle ? <p className="mt-2 text-muted">{event.subtitle}</p> : null}
          <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:max-w-md">
            <div>
              <dt className="text-xs font-bold tracking-[0.12em] uppercase">Prochaine date</dt>
              <dd>
                {next ? `${formatLongDate(next.startsAt)}, ${formatTime(next.startsAt)}` : '—'}
              </dd>
            </div>
            <div>
              <dt className="text-xs font-bold tracking-[0.12em] uppercase">Représentations</dt>
              <dd>
                {event.performanceCount} · {formatDuration(event.durationMinutes)}
              </dd>
            </div>
          </dl>
          <div className="mt-auto flex flex-wrap items-center gap-4 pt-5">
            {next ? <StatusBadge status={next.status} /> : null}
            {event.fromPriceCents != null ? (
              <span className="tabular font-bold">
                dès {formatMoney(event.fromPriceCents, event.currency)}
              </span>
            ) : null}
            <span className="ml-auto inline-flex items-center gap-2 font-display text-lg tracking-[0.08em] uppercase underline decoration-2 underline-offset-6">
              Réserver <ArrowRight aria-hidden className="size-5" />
            </span>
          </div>
        </div>
      </Link>
    </li>
  )
}
