import { zoneColor } from '../seatmap/api'
import type { Layout } from './api'

/** Read-only drawing of a layout: stage, zones, seats (blocked crossed, accessible ringed). */
export function PlanPreview({ layout, label }: { layout: Layout; label: string }) {
  const zones = new Map(layout.zones.map((z) => [z.code, z]))
  return (
    <svg
      viewBox={`0 0 ${String(layout.viewBox.width)} ${String(layout.viewBox.height)}`}
      role="img"
      aria-label={label}
      className="h-auto w-full bg-night-950"
    >
      <rect
        x={layout.stage.x}
        y={layout.stage.y}
        width={layout.stage.width}
        height={layout.stage.height}
        fill="var(--pop-yellow)"
        stroke="var(--ink-900)"
        strokeWidth={3}
      />
      <text
        x={layout.stage.x + layout.stage.width / 2}
        y={layout.stage.y + layout.stage.height / 2 + 10}
        textAnchor="middle"
        className="font-display"
        fontSize={28}
        letterSpacing={8}
        fill="var(--ink-900)"
      >
        SCÈNE
      </text>
      {layout.zones.map((z) => (
        <path
          key={z.code}
          d={z.shape}
          fill={zoneColor(z.colorToken)}
          fillOpacity={z.kind === 'STANDING' ? 0.4 : 0.1}
          stroke={zoneColor(z.colorToken)}
          strokeWidth={2}
          strokeDasharray={z.kind === 'STANDING' ? undefined : '6 6'}
        />
      ))}
      {layout.seats.map((s) =>
        s.blocked ? (
          <path
            key={`${s.zone}-${s.label}`}
            d={`M${String(s.x - 5)} ${String(s.y - 5)}l10 10m0 -10l-10 10`}
            stroke="var(--paper-100)"
            strokeWidth={2}
          />
        ) : (
          <circle
            key={`${s.zone}-${s.label}`}
            cx={s.x}
            cy={s.y}
            r={9}
            fill={zoneColor(zones.get(s.zone)?.colorToken ?? '')}
            stroke={s.accessible ? 'var(--paper-50)' : 'var(--ink-900)'}
            strokeWidth={s.accessible ? 3 : 1.5}
          />
        ),
      )}
    </svg>
  )
}
