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
      className={cn('flex flex-col gap-3 rounded-md border border-line bg-surface p-5', className)}
    >
      <h3 className="text-sm text-muted">{label}</h3>
      <div className="flex items-baseline justify-between gap-3">
        <p className="tabular font-display text-4xl leading-none text-fg">{value}</p>
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
