import type { ReactNode } from 'react'
import { cn } from '../cn'

export interface KpiTileProps {
  label: string
  value: string
  /** Relative change versus the previous period, e.g. 0.12 for +12 %. */
  delta?: number | null
  /** When true, a decrease is good (e.g. refund rate). */
  invertDelta?: boolean
  hint?: string
  chart?: ReactNode
  className?: string
}

const pct = new Intl.NumberFormat('fr-FR', {
  style: 'percent',
  maximumFractionDigits: 1,
  signDisplay: 'exceptZero',
})

export function KpiTile({
  label,
  value,
  delta,
  invertDelta = false,
  hint,
  chart,
  className,
}: KpiTileProps) {
  const hasDelta = delta !== undefined && delta !== null && Number.isFinite(delta)
  const good = hasDelta && (invertDelta ? delta < 0 : delta > 0)
  const bad = hasDelta && (invertDelta ? delta > 0 : delta < 0)

  return (
    <section
      aria-label={label}
      className={cn(
        'flex flex-col gap-3 border-2 border-ink-900 bg-surface p-5 shadow-[4px_4px_0_var(--ink-900)]',
        className,
      )}
    >
      <h3 className="text-xs font-bold tracking-[0.12em] uppercase">{label}</h3>
      <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
        <p className="tabular min-w-0 font-display text-[clamp(1.75rem,2.6vw,2.5rem)] leading-none break-words text-fg">
          {value}
        </p>
        {hasDelta ? (
          <p
            className={cn(
              'tabular text-sm font-medium',
              good && 'text-success',
              bad && 'text-danger',
              !good && !bad && 'text-muted',
            )}
          >
            <span aria-hidden>{delta > 0 ? '▲ ' : delta < 0 ? '▼ ' : ''}</span>
            {pct.format(delta)}
            <span className="sr-only"> par rapport à la période précédente</span>
          </p>
        ) : null}
      </div>
      {chart}
      {hint ? <p className="text-xs text-muted">{hint}</p> : null}
    </section>
  )
}
