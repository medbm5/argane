import { cn } from '../cn'

export interface GaugeSegment {
  label: string
  value: number
  /** Tailwind stroke class, e.g. "stroke-bordeaux-700". */
  className: string
}

export interface HalfGaugeProps {
  segments: GaugeSegment[]
  /** Total of the ring; defaults to the sum of the segments. */
  total?: number
  centerLabel: string
  centerCaption?: string
  className?: string
}

const R = 80
const ARC = Math.PI * R

/** Half-ring gauge (e.g. sold / held / remaining) with a legend. */
export function HalfGauge({
  segments,
  total,
  centerLabel,
  centerCaption,
  className,
}: HalfGaugeProps) {
  const sum = total ?? segments.reduce((acc, s) => acc + s.value, 0)
  const lengths = segments.map((s) => (sum > 0 ? (s.value / sum) * ARC : 0))
  const offsets = lengths.map((_, i) => lengths.slice(0, i).reduce((a, b) => a + b, 0))

  return (
    <figure className={cn('flex flex-col items-center gap-4', className)}>
      <div className="relative w-full max-w-64">
        <svg viewBox="0 0 200 110" className="w-full" aria-hidden>
          <path
            d={`M20,100 A${R},${R} 0 0 1 180,100`}
            fill="none"
            strokeWidth="16"
            className="stroke-paper-200"
          />
          {segments.map((s, i) => (
            <path
              key={s.label}
              d={`M20,100 A${R},${R} 0 0 1 180,100`}
              fill="none"
              strokeWidth="16"
              strokeDasharray={`${lengths[i]} ${ARC}`}
              strokeDashoffset={-(offsets[i] ?? 0)}
              className={s.className}
            />
          ))}
        </svg>
        <div className="absolute inset-x-0 bottom-0 text-center">
          <p className="tabular font-display text-4xl leading-none text-fg">{centerLabel}</p>
          {centerCaption ? <p className="text-xs text-muted">{centerCaption}</p> : null}
        </div>
      </div>
      <figcaption>
        <ul className="flex flex-wrap justify-center gap-x-4 gap-y-1 text-sm">
          {segments.map((s) => (
            <li key={s.label} className="flex items-center gap-1.5">
              <svg viewBox="0 0 10 10" className="size-2.5" aria-hidden>
                <circle
                  cx="5"
                  cy="5"
                  r="4"
                  strokeWidth="2"
                  className={cn('fill-none', s.className)}
                />
              </svg>
              <span className="text-muted">{s.label}</span>
              <span className="tabular font-medium text-fg">{s.value}</span>
            </li>
          ))}
        </ul>
      </figcaption>
    </figure>
  )
}
