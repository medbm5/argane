import { cn } from '../cn'

export interface DotSparklineProps {
  /** One value per column (e.g. tickets per day). */
  values: number[]
  /** Dots per column at the maximum value. */
  rows?: number
  label?: string
  className?: string
}

const GAP = 4
const DOT = 2.4

/**
 * Dot-matrix sparkline: each column is a stack of dots, lit up to the
 * column's share of the maximum value. The last column is highlighted.
 */
export function DotSparkline({ values, rows = 6, label, className }: DotSparklineProps) {
  const max = Math.max(0, ...values)
  const width = Math.max(values.length, 1) * GAP
  const height = rows * GAP

  return (
    <svg
      viewBox={`0 0 ${width} ${height}`}
      role="img"
      aria-label={label ?? 'Tendance'}
      className={cn('h-6 w-full text-accent', className)}
      preserveAspectRatio="none"
    >
      {values.map((value, col) => {
        const lit = max === 0 ? 0 : Math.round((value / max) * rows)
        const isLast = col === values.length - 1
        return Array.from({ length: rows }, (_, row) => {
          const on = rows - row <= lit
          return (
            <circle
              key={`${col}-${row}`}
              data-on={on || undefined}
              cx={col * GAP + GAP / 2}
              cy={row * GAP + GAP / 2}
              r={DOT / 2}
              className={cn(
                on ? (isLast ? 'fill-bordeaux-700' : 'fill-current') : 'fill-paper-300',
                !on && 'opacity-60',
              )}
            />
          )
        })
      })}
    </svg>
  )
}
