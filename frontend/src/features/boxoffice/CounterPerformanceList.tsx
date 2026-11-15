import { cn } from '../../design-system/cn'
import { dateTile, formatTime } from '../../lib/format'
import type { CounterPerformance } from './api'

/** The counter's day list: pick a performance to sell. */
export function CounterPerformanceList({
  performances,
  selectedId,
  onSelect,
}: {
  performances: CounterPerformance[]
  selectedId: string | null
  onSelect: (id: string) => void
}) {
  if (performances.length === 0) {
    return (
      <p className="p-4 text-sm text-muted">Aucune représentation dans les 14 prochains jours.</p>
    )
  }
  return (
    <ul className="divide-y-2 divide-fg/10" aria-label="Représentations">
      {performances.map((p) => {
        const tile = dateTile(p.startsAt)
        const fill = p.capacity > 0 ? 1 - p.remaining / p.capacity : 0
        return (
          <li key={p.id}>
            <button
              type="button"
              disabled={!p.sellable}
              aria-pressed={p.id === selectedId}
              onClick={() => {
                onSelect(p.id)
              }}
              className={cn(
                'grid w-full grid-cols-[3.5rem_1fr] items-center gap-3 p-3 text-left transition-colors',
                p.id === selectedId ? 'bg-pop-yellow text-ink-900' : 'hover:bg-surface-alt',
                !p.sellable && 'cursor-not-allowed opacity-50',
              )}
            >
              <span className="flex flex-col items-center leading-none">
                <span className="tabular font-display text-3xl">{tile.day}</span>
                <span className="small-caps text-[0.65rem]">{tile.month}</span>
              </span>
              <span className="min-w-0">
                <span className="block truncate font-display text-xl leading-tight">
                  {p.eventTitle}
                </span>
                <span className="block text-xs">
                  {formatTime(p.startsAt)} · {p.remaining} / {p.capacity} places
                  {!p.onlineOpen && p.sellable ? ' · guichet seul' : ''}
                  {p.status === 'CANCELLED' ? ' · annulée' : ''}
                </span>
                <span aria-hidden className="mt-1 block h-1.5 w-full bg-ink-900/10">
                  <span
                    className="block h-full bg-pop-red"
                    style={{ width: `${String(Math.round(fill * 100))}%` }}
                  />
                </span>
              </span>
            </button>
          </li>
        )
      })}
    </ul>
  )
}
