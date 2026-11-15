import { ArrowUpRight } from 'lucide-react'
import { Link } from 'react-router'
import { StatusBadge } from '../../../design-system/primitives/Badge'
import { dateTile, formatMoney, formatShortDate, formatTime } from '../../../lib/format'
import type { PerformanceSummary } from '../../../lib/api/types'

export interface PerformanceRowProps {
  performance: PerformanceSummary
  title: string
  subtitle?: string | null
  href: string
  currency: string
}

/** One dated line of the programme: date tile, title, time, price, status. */
export function PerformanceRow({
  performance,
  title,
  subtitle,
  href,
  currency,
}: PerformanceRowProps) {
  const tile = dateTile(performance.startsAt)
  const soldOut = performance.status === 'SOLD_OUT'
  const lowStock =
    performance.isOnSale &&
    performance.capacity > 0 &&
    performance.remaining / performance.capacity < 0.15

  return (
    <li className="border-b-2 border-ink-900 last:border-b-0">
      <Link
        to={href}
        className="group grid grid-cols-[4rem_1fr_auto] items-center gap-4 px-4 py-4 transition-colors duration-200 hover:bg-pop-yellow hover:text-ink-900 focus-visible:bg-pop-yellow sm:grid-cols-[5rem_1fr_5rem_8rem_8rem] sm:px-8 md:grid-cols-[5rem_1fr_5rem_8rem_15rem]"
      >
        <span className="flex flex-col items-center leading-none" aria-hidden>
          <span className="tabular font-display text-5xl">{tile.day}</span>
          <span className="small-caps text-xs">{tile.month}</span>
        </span>
        <span className="min-w-0">
          <span className="block truncate font-display text-3xl leading-tight">{title}</span>
          {subtitle ? <span className="block truncate text-sm text-muted">{subtitle}</span> : null}
          <span className="sr-only">
            {formatShortDate(performance.startsAt)} à {formatTime(performance.startsAt)}
          </span>
        </span>
        <span className="tabular hidden text-sm text-muted sm:block">
          {formatTime(performance.startsAt)}
        </span>
        <span className="tabular hidden text-sm sm:block">
          {performance.fromPriceCents != null && !soldOut
            ? `dès ${formatMoney(performance.fromPriceCents, currency)}`
            : null}
        </span>
        <span className="flex items-center justify-end gap-3">
          {lowStock ? (
            <span className="hidden text-xs text-highlight md:inline">Dernières places</span>
          ) : null}
          <StatusBadge status={performance.status} />
          <ArrowUpRight
            aria-hidden
            className="hidden size-4 text-muted transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 sm:block"
          />
        </span>
      </Link>
    </li>
  )
}
