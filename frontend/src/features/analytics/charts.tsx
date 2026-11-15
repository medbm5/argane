import { formatMoney } from '../../lib/format'

const POP = [
  'var(--pop-red)',
  'var(--pop-blue)',
  'var(--pop-yellow)',
  'var(--pop-teal)',
  'var(--pop-pink)',
  'var(--pop-orange)',
]

/** Daily revenue as hard-edged bars with a baseline; label every ~7th day. */
export function DailyBars({
  series,
  currency,
}: {
  series: { date: string; revenueCents: number; tickets: number }[]
  currency: string
}) {
  const width = 640
  const height = 200
  const max = Math.max(1, ...series.map((d) => d.revenueCents))
  const step = width / Math.max(1, series.length)
  const every = Math.max(1, Math.round(series.length / 6))
  const total = series.reduce((n, d) => n + d.revenueCents, 0)

  return (
    <figure>
      <svg
        viewBox={`0 0 ${String(width)} ${String(height + 24)}`}
        role="img"
        aria-label={`Recettes par jour, total ${formatMoney(total, currency)}`}
        className="h-auto w-full"
      >
        {series.map((d, i) => {
          const h = (d.revenueCents / max) * height
          return (
            <g key={d.date}>
              <rect
                x={i * step + 1}
                y={height - h}
                width={Math.max(1, step - 2)}
                height={h}
                fill="var(--pop-red)"
                stroke="var(--ink-900)"
                strokeWidth={h > 0 ? 1.5 : 0}
              >
                <title>{`${d.date} : ${formatMoney(d.revenueCents, currency)} · ${String(d.tickets)} billets`}</title>
              </rect>
              {i % every === 0 ? (
                <text
                  x={i * step + step / 2}
                  y={height + 18}
                  textAnchor="middle"
                  fontSize={11}
                  fill="currentColor"
                >
                  {d.date.slice(8)}/{d.date.slice(5, 7)}
                </text>
              ) : null}
            </g>
          )
        })}
        <line x1={0} x2={width} y1={height} y2={height} stroke="var(--ink-900)" strokeWidth={2} />
      </svg>
    </figure>
  )
}

/** Ranked horizontal bars (tariffs, channels): label, bar, value. */
export function RankBars({
  rows,
  currency,
}: {
  rows: { label: string; value: number; detail: string }[]
  currency: string
}) {
  const max = Math.max(1, ...rows.map((r) => r.value))
  return (
    <ul className="space-y-3">
      {rows.map((row, i) => (
        <li key={row.label}>
          <div className="flex items-baseline justify-between gap-3 text-sm">
            <span className="font-bold">{row.label}</span>
            <span className="tabular">{formatMoney(row.value, currency)}</span>
          </div>
          <div className="mt-1 h-4 border-2 border-ink-900 bg-paper-50" aria-hidden>
            <div
              className="h-full"
              style={{
                width: `${String((row.value / max) * 100)}%`,
                background: POP[i % POP.length],
              }}
            />
          </div>
          <p className="mt-0.5 text-xs text-muted">{row.detail}</p>
        </li>
      ))}
    </ul>
  )
}

/** Booking curve: reference (past average) dashed, selected performance solid. */
export function CurveChart({
  reference,
  performance,
}: {
  reference: { daysBefore: number; rate: number }[]
  performance: { daysBefore: number; rate: number }[]
}) {
  const width = 640
  const height = 220
  const maxDays = 60
  const maxRate = Math.max(0.1, ...reference.map((p) => p.rate), ...performance.map((p) => p.rate))
  const x = (d: number) => width - (d / maxDays) * width
  const y = (r: number) => height - (r / maxRate) * height
  const path = (points: { daysBefore: number; rate: number }[]) =>
    points
      .map((p, i) => `${i === 0 ? 'M' : 'L'}${x(p.daysBefore).toFixed(1)} ${y(p.rate).toFixed(1)}`)
      .join(' ')

  return (
    <figure>
      <svg
        viewBox={`0 0 ${String(width)} ${String(height + 24)}`}
        role="img"
        aria-label="Courbe de réservation : part des places vendues selon les jours avant le spectacle"
        className="h-auto w-full"
      >
        {[0.25, 0.5, 0.75, 1].map((f) => (
          <line
            key={f}
            x1={0}
            x2={width}
            y1={y(maxRate * f)}
            y2={y(maxRate * f)}
            stroke="var(--ink-900)"
            strokeOpacity={0.1}
          />
        ))}
        {reference.length > 0 ? (
          <path
            d={path(reference)}
            fill="none"
            stroke="var(--ink-900)"
            strokeWidth={2.5}
            strokeDasharray="6 5"
          />
        ) : null}
        {performance.length > 0 ? (
          <path d={path(performance)} fill="none" stroke="var(--pop-red)" strokeWidth={4} />
        ) : null}
        <line x1={0} x2={width} y1={height} y2={height} stroke="var(--ink-900)" strokeWidth={2} />
        {[60, 45, 30, 15, 0].map((d) => (
          <text
            key={d}
            x={x(d)}
            y={height + 18}
            textAnchor={d === 0 ? 'end' : d === 60 ? 'start' : 'middle'}
            fontSize={11}
            fill="currentColor"
          >
            {d === 0 ? 'J' : `J-${String(d)}`}
          </text>
        ))}
        <text x={4} y={14} fontSize={11} fill="currentColor">
          {Math.round(maxRate * 100)} %
        </text>
      </svg>
      <figcaption className="mt-2 flex flex-wrap gap-4 text-xs">
        <span className="flex items-center gap-2">
          <span aria-hidden className="inline-block w-6 border-t-2 border-dashed border-ink-900" />
          Moyenne des spectacles passés
        </span>
        <span className="flex items-center gap-2">
          <span aria-hidden className="inline-block h-1 w-6 bg-pop-red" />
          Date sélectionnée
        </span>
      </figcaption>
    </figure>
  )
}

/** Seats coloured by sell-through: pale (rarely sold) to red (always sold). */
export function SeatHeat({
  seats,
}: {
  seats: { label: string; x: number; y: number; rate: number }[]
}) {
  return (
    <svg
      viewBox="0 0 1000 700"
      role="img"
      aria-label="Carte de chaleur des fauteuils"
      className="h-auto w-full bg-night-950"
    >
      <rect
        x={250}
        y={18}
        width={500}
        height={56}
        fill="var(--pop-yellow)"
        stroke="var(--ink-900)"
        strokeWidth={3}
      />
      {seats.map((s) => (
        <circle
          key={s.label}
          cx={s.x}
          cy={s.y}
          r={9}
          fill="var(--pop-red)"
          fillOpacity={0.12 + s.rate * 0.88}
          stroke="var(--ink-900)"
          strokeWidth={1}
        >
          <title>{`${s.label} : ${String(Math.round(s.rate * 100))} %`}</title>
        </circle>
      ))}
    </svg>
  )
}
