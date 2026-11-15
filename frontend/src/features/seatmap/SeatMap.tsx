import { Maximize, Minus, Plus } from 'lucide-react'
import {
  memo,
  useEffect,
  useMemo,
  useRef,
  useState,
  type KeyboardEvent,
  type PointerEvent,
} from 'react'
import { formatMoney } from '../../lib/format'
import type {
  Availability,
  SeatMap as SeatMapData,
  SeatMapSeat,
  SeatMapZone,
} from '../../lib/api/types'
import { zoneColor, zoneFromPrice } from './api'
import {
  fitView,
  neighbour,
  panBy,
  pathBounds,
  zoomAt,
  zoomCenter,
  type Direction,
  type View,
} from './geometry'
import { seatStates, type SeatState, type Selection } from './selection'

const SEAT_R = 10

/** True on phone-width screens (false where matchMedia is missing, e.g. tests). */
function isNarrowScreen(): boolean {
  try {
    return window.matchMedia('(max-width: 640px)').matches
  } catch {
    return false
  }
}
const KEY_DIRECTIONS: Record<string, Direction> = {
  ArrowLeft: 'left',
  ArrowRight: 'right',
  ArrowUp: 'up',
  ArrowDown: 'down',
}
const STATE_LABEL: Record<SeatState, string> = {
  available: 'disponible',
  selected: 'sélectionnée',
  held: 'en cours de réservation',
  sold: 'vendue',
  blocked: 'indisponible',
}

export interface SeatMapProps {
  map: SeatMapData
  availability: Availability | undefined
  selection: Selection
  onToggleSeat: (seatId: string) => void
  onStandingZone: (zoneId: string) => void
  /** Seats just taken by someone else, flashed briefly. */
  lostSeatIds?: string[]
}

/** One seat: memoised so a poll only re-renders seats whose state changed. */
const Seat = memo(function Seat({
  seat,
  state,
  color,
  label,
  focusable,
  lost,
}: {
  seat: SeatMapSeat
  state: SeatState
  color: string
  label: string
  focusable: boolean
  lost: boolean
}) {
  const takeable = state === 'available' || state === 'selected'
  return (
    <g
      role="checkbox"
      aria-checked={state === 'selected'}
      aria-disabled={!takeable}
      aria-label={label}
      tabIndex={focusable ? 0 : -1}
      data-seat-id={seat.id}
      className={`seat outline-none ${takeable ? 'cursor-pointer' : 'cursor-not-allowed'}`}
    >
      {state === 'blocked' ? (
        <path
          d={`M${seat.x - 5} ${seat.y - 5}l10 10m0 -10l-10 10`}
          stroke="var(--night-600)"
          strokeWidth={2}
        />
      ) : (
        <circle
          cx={seat.x}
          cy={seat.y}
          r={state === 'sold' ? SEAT_R - 3 : SEAT_R}
          fill={
            state === 'available'
              ? color
              : state === 'selected'
                ? 'var(--paper-50)'
                : state === 'held'
                  ? 'url(#seat-held)'
                  : 'var(--night-800)'
          }
          stroke={state === 'selected' ? 'var(--pop-yellow)' : 'var(--ink-900)'}
          strokeWidth={state === 'selected' ? 4 : 2}
          className={`transition-[fill,r] duration-200 ${lost ? 'animate-[seat-lost_1.2s_ease-out_2]' : ''}`}
        />
      )}
      {state === 'selected' ? (
        <path
          d={`M${seat.x - 4.5} ${seat.y}l3 3.5l6 -7`}
          fill="none"
          stroke="var(--ink-900)"
          strokeWidth={2.5}
          strokeLinecap="round"
          strokeLinejoin="round"
          pointerEvents="none"
        />
      ) : seat.accessible && state === 'available' ? (
        <text
          x={seat.x}
          y={seat.y + 3.5}
          textAnchor="middle"
          fontSize={10}
          fontWeight={700}
          fill="var(--ink-900)"
          pointerEvents="none"
          aria-hidden
        >
          ♿
        </text>
      ) : null}
      <circle className="seat-focus" cx={seat.x} cy={seat.y} r={SEAT_R + 5} fill="none" />
    </g>
  )
})

function StandingZone({
  zone,
  remaining,
  quantity,
  currency,
  onSelect,
}: {
  zone: SeatMapZone
  remaining: number
  quantity: number
  currency: string
  onSelect: () => void
}) {
  const bounds = pathBounds(zone.shape)
  const color = zoneColor(zone.colorToken)
  const from = zoneFromPrice(zone.prices)
  const soldOut = remaining === 0
  const label = `${zone.name}, places debout, ${soldOut ? 'complet' : `${String(remaining)} places restantes`}${from != null ? `, dès ${formatMoney(from, currency)}` : ''}${quantity > 0 ? `, ${String(quantity)} sélectionnées` : ''}`
  return (
    <g
      role="button"
      tabIndex={0}
      aria-label={label}
      aria-disabled={soldOut}
      className={`standing-zone outline-none ${soldOut ? 'cursor-not-allowed' : 'cursor-pointer'}`}
      onClick={soldOut ? undefined : onSelect}
      onKeyDown={(e) => {
        if (!soldOut && (e.key === 'Enter' || e.key === ' ')) {
          e.preventDefault()
          onSelect()
        }
      }}
    >
      <path
        d={zone.shape}
        fill={color}
        fillOpacity={soldOut ? 0.12 : quantity > 0 ? 0.55 : 0.32}
        stroke={quantity > 0 ? 'var(--pop-yellow)' : color}
        strokeWidth={quantity > 0 ? 5 : 3}
        className="hover:fill-opacity-50 transition-[fill-opacity] duration-200"
      />
      <text
        x={bounds.x + bounds.w / 2}
        y={bounds.y + bounds.h / 2 - 4}
        textAnchor="middle"
        className="font-display uppercase"
        fontSize={34}
        fill="var(--paper-50)"
        pointerEvents="none"
        aria-hidden
      >
        {zone.name}
      </text>
      <text
        x={bounds.x + bounds.w / 2}
        y={bounds.y + bounds.h / 2 + 22}
        textAnchor="middle"
        fontSize={14}
        fontWeight={700}
        fill="var(--paper-50)"
        pointerEvents="none"
        aria-hidden
      >
        {soldOut
          ? 'COMPLET'
          : `DEBOUT · ${String(remaining)} PLACES${quantity > 0 ? ` · ${String(quantity)} CHOISIES` : ''}`}
      </text>
    </g>
  )
}

/**
 * The hall plan in SVG: stage at the top with a warm glow, zones, seats.
 * Wheel or buttons zoom, drag pans; arrow keys move between seats (roving focus)
 * and Space/Enter picks.
 */
export function SeatMap({
  map,
  availability,
  selection,
  onToggleSeat,
  onStandingZone,
  lostSeatIds = [],
}: SeatMapProps) {
  const plan = useMemo(
    () => ({ width: map.viewBox.width, height: map.viewBox.height }),
    [map.viewBox.width, map.viewBox.height],
  )
  // Phones: seats would be ~10 px across, too small to tap. Open zoomed on the front
  // of the hall; zoom out / fit stay one tap away.
  const [view, setView] = useState<View>(() =>
    isNarrowScreen()
      ? zoomAt(fitView(plan), plan, 1.8, plan.width / 2, plan.height * 0.35)
      : fitView(plan),
  )
  const svgRef = useRef<SVGSVGElement>(null)
  const drag = useRef<{ x: number; y: number; moved: number; id: number } | null>(null)

  const states = useMemo(() => seatStates(availability, selection), [availability, selection])
  const zonesById = useMemo(() => new Map(map.zones.map((z) => [z.id, z])), [map.zones])
  const lost = useMemo(() => new Set(lostSeatIds), [lostSeatIds])
  const navigable = useMemo(
    () => map.seats.filter((s) => states.get(s.id) !== 'blocked'),
    [map.seats, states],
  )
  const [focusId, setFocusId] = useState<string | null>(null)
  const rovingId =
    focusId ??
    navigable.find((s) => (states.get(s.id) ?? 'available') === 'available')?.id ??
    navigable[0]?.id

  // Wheel zoom needs a non-passive listener to stop the page from scrolling.
  useEffect(() => {
    const svg = svgRef.current
    if (!svg) return
    const onWheel = (e: WheelEvent) => {
      e.preventDefault()
      const rect = svg.getBoundingClientRect()
      setView((v) => {
        const px = v.x + ((e.clientX - rect.left) / rect.width) * v.w
        const py = v.y + ((e.clientY - rect.top) / rect.height) * v.h
        return zoomAt(v, plan, e.deltaY < 0 ? 1.2 : 1 / 1.2, px, py)
      })
    }
    svg.addEventListener('wheel', onWheel, { passive: false })
    return () => {
      svg.removeEventListener('wheel', onWheel)
    }
  }, [plan])

  function seatFromEvent(target: EventTarget): string | null {
    const el = (target as Element).closest('[data-seat-id]')
    return el?.getAttribute('data-seat-id') ?? null
  }

  function onPointerDown(e: PointerEvent<SVGSVGElement>) {
    drag.current = { x: e.clientX, y: e.clientY, moved: 0, id: e.pointerId }
  }

  function onPointerMove(e: PointerEvent<SVGSVGElement>) {
    const d = drag.current
    const svg = svgRef.current
    if (!d || !svg || d.id !== e.pointerId) return
    const dx = e.clientX - d.x
    const dy = e.clientY - d.y
    d.moved += Math.abs(dx) + Math.abs(dy)
    if (d.moved > 6) {
      if (!svg.hasPointerCapture(e.pointerId)) svg.setPointerCapture(e.pointerId)
      const rect = svg.getBoundingClientRect()
      setView((v) => panBy(v, plan, (dx / rect.width) * v.w, (dy / rect.height) * v.h))
      d.x = e.clientX
      d.y = e.clientY
    }
  }

  function onPointerUp(e: PointerEvent<SVGSVGElement>) {
    const d = drag.current
    drag.current = null
    if (d && d.moved <= 6) pick(seatFromEvent(e.target))
  }

  function pick(seatId: string | null) {
    if (!seatId) return
    const state = states.get(seatId) ?? 'available'
    if (state === 'available' || state === 'selected') onToggleSeat(seatId)
    setFocusId(seatId)
  }

  function onKeyDown(e: KeyboardEvent<SVGSVGElement>) {
    const seatId = seatFromEvent(e.target)
    if (!seatId) return
    if (e.key === ' ' || e.key === 'Enter') {
      e.preventDefault()
      pick(seatId)
      return
    }
    const direction = KEY_DIRECTIONS[e.key]
    const current = navigable.find((s) => s.id === seatId)
    if (!direction || !current) return
    e.preventDefault()
    const next = neighbour(navigable, current, direction)
    if (!next) return
    setFocusId(next.id)
    svgRef.current?.querySelector<SVGGElement>(`[data-seat-id="${next.id}"]`)?.focus()
  }

  const stage = map.stage
  const zoomed = view.w < plan.width - 1

  return (
    <div className="relative h-full w-full">
      <svg
        ref={svgRef}
        viewBox={`${String(view.x)} ${String(view.y)} ${String(view.w)} ${String(view.h)}`}
        role="group"
        aria-label={`Plan de salle, ${map.layoutName}. Flèches pour se déplacer entre les places, Espace pour choisir.`}
        className={`h-full w-full touch-none select-none ${zoomed ? 'cursor-grab active:cursor-grabbing' : ''}`}
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onPointerCancel={() => (drag.current = null)}
        onKeyDown={onKeyDown}
      >
        <defs>
          <radialGradient id="stage-glow" cx="50%" cy="0%" r="75%">
            <stop offset="0" stopColor="var(--stage-glow)" stopOpacity="0.55" />
            <stop offset="0.5" stopColor="var(--stage-glow)" stopOpacity="0.12" />
            <stop offset="1" stopColor="var(--stage-glow)" stopOpacity="0" />
          </radialGradient>
          <pattern
            id="seat-held"
            width="5"
            height="5"
            patternUnits="userSpaceOnUse"
            patternTransform="rotate(45)"
          >
            <rect width="5" height="5" fill="var(--night-800)" />
            <rect width="2" height="5" fill="var(--night-600)" />
          </pattern>
        </defs>

        <ellipse
          cx={stage.x + stage.width / 2}
          cy={stage.y}
          rx={stage.width * 0.95}
          ry={340}
          fill="url(#stage-glow)"
          className="animate-[stage-breathe_6s_ease-in-out_infinite_alternate]"
          aria-hidden
        />
        <g aria-hidden>
          <rect
            x={stage.x}
            y={stage.y}
            width={stage.width}
            height={stage.height}
            fill="var(--pop-yellow)"
            stroke="var(--ink-900)"
            strokeWidth={3}
          />
          <text
            x={stage.x + stage.width / 2}
            y={stage.y + stage.height / 2 + 11}
            textAnchor="middle"
            className="font-display"
            fontSize={32}
            letterSpacing={10}
            fill="var(--ink-900)"
          >
            SCÈNE
          </text>
        </g>

        {map.zones.map((zone) =>
          zone.kind === 'STANDING' ? (
            <StandingZone
              key={zone.id}
              zone={zone}
              remaining={
                availability?.standing.find((s) => s.zoneId === zone.id)?.remaining ??
                zone.capacity ??
                0
              }
              quantity={selection.standing[zone.id] ?? 0}
              currency={map.currency}
              onSelect={() => {
                onStandingZone(zone.id)
              }}
            />
          ) : (
            <ZoneOutline key={zone.id} zone={zone} />
          ),
        )}

        {map.zones
          .filter((z) => z.kind !== 'STANDING')
          .map((zone) => {
            const from = zoneFromPrice(zone.prices)
            const priceText = from != null ? `, ${formatMoney(from, map.currency)}` : ''
            const color = zoneColor(zone.colorToken)
            return (
              <g key={zone.id} role="group" aria-label={zone.name}>
                {map.seats
                  .filter((s) => s.zoneId === zone.id)
                  .map((seat) => {
                    const state = states.get(seat.id) ?? 'available'
                    return (
                      <Seat
                        key={seat.id}
                        seat={seat}
                        state={state}
                        color={color}
                        focusable={seat.id === rovingId}
                        lost={lost.has(seat.id)}
                        label={`${zonesById.get(seat.zoneId)?.name ?? ''}, rang ${seat.row}, place ${String(seat.number)}${seat.accessible ? ', accès fauteuil roulant' : ''}, ${STATE_LABEL[state]}${state === 'available' ? priceText : ''}`}
                      />
                    )
                  })}
              </g>
            )
          })}
      </svg>

      <div className="absolute top-3 right-3 flex flex-col gap-2">
        {[
          {
            label: 'Zoomer',
            icon: <Plus className="size-5" />,
            onClick: () => {
              setView((v) => zoomCenter(v, plan, 1.4))
            },
          },
          {
            label: 'Dézoomer',
            icon: <Minus className="size-5" />,
            onClick: () => {
              setView((v) => zoomCenter(v, plan, 1 / 1.4))
            },
          },
          {
            label: 'Voir toute la salle',
            icon: <Maximize className="size-5" />,
            onClick: () => {
              setView(fitView(plan))
            },
          },
        ].map((control) => (
          <button
            key={control.label}
            type="button"
            aria-label={control.label}
            title={control.label}
            onClick={control.onClick}
            className="flex size-10 items-center justify-center border-2 border-paper-100 bg-night-900 text-paper-100 shadow-[3px_3px_0_var(--pop-yellow)] transition-transform hover:translate-x-px hover:translate-y-px focus-visible:outline-2 focus-visible:outline-pop-yellow"
          >
            {control.icon}
          </button>
        ))}
      </div>
    </div>
  )
}

function ZoneOutline({ zone }: { zone: SeatMapZone }) {
  const bounds = pathBounds(zone.shape)
  const color = zoneColor(zone.colorToken)
  return (
    <g aria-hidden>
      <path
        d={zone.shape}
        fill={color}
        fillOpacity={0.08}
        stroke={color}
        strokeOpacity={0.5}
        strokeWidth={2}
        strokeDasharray="6 6"
      />
      <text
        x={bounds.x + 4}
        y={bounds.y - 8}
        className="font-display uppercase"
        fontSize={16}
        letterSpacing={3}
        fill={color}
      >
        {zone.name}
      </text>
    </g>
  )
}
