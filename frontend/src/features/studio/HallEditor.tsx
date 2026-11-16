import { Ban, RotateCcw, X } from 'lucide-react'
import { useMemo, useState, type KeyboardEvent } from 'react'
import { StatePanel } from '../../design-system/components/StatePanel'
import { Button } from '../../design-system/primitives/Button'
import { toast } from '../../design-system/toast-store'
import { ApiError } from '../../lib/api'
import { zoneColor } from '../seatmap/api'
import { useLayout, useSetSeatsBlocked, type Layout } from './api'

type Seat = Layout['seats'][number] & { id: string }

const plural = (n: number, one: string, many: string) => `${String(n)} ${n > 1 ? many : one}`

function summary(seatsUpdated: number, seatsKept: number, blocked: boolean): string {
  const done = blocked ? 'retirée' : 'remise en vente'
  const parts = [
    seatsUpdated > 0
      ? `${plural(seatsUpdated, `place ${done}`, `places ${blocked ? 'retirées' : 'remises en vente'}`)} sur les dates à venir`
      : 'Aucune date à venir à modifier',
  ]
  if (seatsKept > 0)
    parts.push(
      plural(
        seatsKept,
        'place déjà vendue ou en cours d’achat conservée',
        'places déjà vendues ou en cours d’achat conservées',
      ),
    )
  return `${parts.join(' · ')}.`
}

/**
 * The organizer's plan of the hall: tap seats (or a row letter for the whole row), then
 * switch them off or back on. Upcoming dates follow; sold seats are never taken back.
 */
export function HallEditor({ layoutId, name }: { layoutId: string; name: string }) {
  const layout = useLayout(layoutId)
  const setBlocked = useSetSeatsBlocked(layoutId)
  const [selected, setSelected] = useState<Set<string>>(new Set())

  const seats = useMemo(
    () => (layout.data?.seats ?? []).filter((s): s is Seat => s.id !== null),
    [layout.data],
  )
  const rows = useMemo(() => {
    const byRow = new Map<string, Seat[]>()
    for (const seat of seats) {
      const key = `${seat.zone}|${seat.row}`
      byRow.set(key, [...(byRow.get(key) ?? []), seat])
    }
    return [...byRow.entries()].map(([key, rowSeats]) => {
      const first = rowSeats.reduce((a, b) => (a.x < b.x ? a : b))
      return {
        key,
        row: rowSeats[0]?.row ?? '',
        x: first.x,
        y: first.y,
        ids: rowSeats.map((s) => s.id),
      }
    })
  }, [seats])

  if (layout.isPending) return <p className="py-10 text-muted">Chargement du plan…</p>
  if (layout.isError)
    return (
      <StatePanel
        kind="error"
        kicker="plan de salle"
        title="Le plan n’a pas pu être chargé"
        headingLevel={2}
      >
        <Button variant="secondary" onClick={() => void layout.refetch()}>
          Réessayer
        </Button>
      </StatePanel>
    )

  const plan = layout.data
  const zones = new Map(plan.zones.map((z) => [z.code, z]))
  const blockedCount = seats.filter((s) => s.blocked).length
  const chosen = seats.filter((s) => selected.has(s.id))
  const canBlock = chosen.some((s) => !s.blocked)
  const canUnblock = chosen.some((s) => s.blocked)
  const standing = plan.zones.filter((z) => z.kind === 'STANDING')

  const toggle = (ids: string[]) => {
    setSelected((current) => {
      const next = new Set(current)
      const all = ids.every((id) => next.has(id))
      for (const id of ids) {
        if (all) next.delete(id)
        else next.add(id)
      }
      return next
    })
  }
  const onKey = (ids: string[]) => (e: KeyboardEvent) => {
    if (e.key === ' ' || e.key === 'Enter') {
      e.preventDefault()
      toggle(ids)
    }
  }
  const apply = (blocked: boolean) => {
    const ids = chosen.filter((s) => s.blocked !== blocked).map((s) => s.id)
    setBlocked.mutate(
      { seatIds: ids, blocked },
      {
        onSuccess: (result) => {
          setSelected(new Set())
          toast(
            blocked
              ? plural(ids.length, 'place désactivée', 'places désactivées')
              : plural(ids.length, 'place réactivée', 'places réactivées'),
            {
              tone: 'success',
              description: summary(result.seatsUpdated, result.seatsKept, blocked),
            },
          )
        },
        onError: (error) => {
          toast('La modification n’a pas été enregistrée', {
            tone: 'danger',
            description: error instanceof ApiError ? error.problem.title : undefined,
          })
        },
      },
    )
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <dl className="flex flex-wrap gap-x-8 gap-y-2">
          <div>
            <dt className="text-xs font-bold tracking-[0.12em] uppercase">
              Places assises actives
            </dt>
            <dd className="font-display text-4xl tabular-nums">{seats.length - blockedCount}</dd>
          </div>
          <div>
            <dt className="text-xs font-bold tracking-[0.12em] uppercase">Désactivées</dt>
            <dd className="font-display text-4xl text-accent tabular-nums">{blockedCount}</dd>
          </div>
          {standing.map((z) => (
            <div key={z.code}>
              <dt className="text-xs font-bold tracking-[0.12em] uppercase">{z.name} (debout)</dt>
              <dd className="font-display text-4xl tabular-nums">{z.capacity}</dd>
            </div>
          ))}
        </dl>
        <ul aria-label="Légende" className="flex flex-wrap gap-4 text-sm">
          <li className="flex items-center gap-2">
            <span
              aria-hidden
              className="size-4 rounded-full border-2 border-ink-900 bg-[conic-gradient(var(--pop-red)_0_33%,var(--pop-teal)_0_66%,var(--pop-yellow)_0)]"
            />
            Active (couleur de sa zone)
          </li>
          <li className="flex items-center gap-2">
            <span
              aria-hidden
              className="grid size-4 place-items-center rounded-full border-2 border-ink-900 bg-paper-200 text-[0.6rem] font-bold"
            >
              ×
            </span>
            Désactivée
          </li>
          <li className="flex items-center gap-2">
            <span
              aria-hidden
              className="size-4 rounded-full border-4 border-pop-yellow bg-ink-900"
            />{' '}
            Sélectionnée
          </li>
        </ul>
      </div>

      <div
        role="toolbar"
        aria-label="Places sélectionnées"
        className="sticky top-2 z-10 flex flex-wrap items-center gap-3 border-2 border-ink-900 bg-paper-50 p-3 shadow-[4px_4px_0_var(--ink-900)]"
      >
        <p className="mr-auto font-display text-lg tracking-[0.06em] uppercase" aria-live="polite">
          {chosen.length === 0
            ? 'Touchez des places ou une lettre de rangée'
            : plural(chosen.length, 'place sélectionnée', 'places sélectionnées')}
        </p>
        <Button
          size="sm"
          variant="primary"
          disabled={!canBlock}
          loading={setBlocked.isPending}
          leadingIcon={<Ban aria-hidden className="size-4" />}
          onClick={() => {
            apply(true)
          }}
        >
          Désactiver
        </Button>
        <Button
          size="sm"
          variant="secondary"
          disabled={!canUnblock || setBlocked.isPending}
          leadingIcon={<RotateCcw aria-hidden className="size-4" />}
          onClick={() => {
            apply(false)
          }}
        >
          Réactiver
        </Button>
        <Button
          size="sm"
          variant="ghost"
          disabled={chosen.length === 0}
          leadingIcon={<X aria-hidden className="size-4" />}
          onClick={() => {
            setSelected(new Set())
          }}
        >
          Effacer la sélection
        </Button>
      </div>

      <div className="overflow-x-auto border-2 border-ink-900">
        <svg
          viewBox={`-40 0 ${String(plan.viewBox.width + 40)} ${String(plan.viewBox.height)}`}
          aria-label={`Plan ${name}`}
          className="h-auto w-full min-w-[40rem] bg-night-950"
        >
          <rect
            x={plan.stage.x}
            y={plan.stage.y}
            width={plan.stage.width}
            height={plan.stage.height}
            fill="var(--pop-yellow)"
            stroke="var(--ink-900)"
            strokeWidth={3}
          />
          <text
            x={plan.stage.x + plan.stage.width / 2}
            y={plan.stage.y + plan.stage.height / 2 + 10}
            textAnchor="middle"
            className="font-display"
            fontSize={28}
            letterSpacing={8}
            fill="var(--ink-900)"
          >
            SCÈNE
          </text>
          {plan.zones.map((z) => (
            <path
              key={z.code}
              d={z.shape}
              fill={zoneColor(z.colorToken)}
              fillOpacity={z.kind === 'STANDING' ? 0.35 : 0.08}
              stroke={zoneColor(z.colorToken)}
              strokeWidth={2}
              strokeDasharray={z.kind === 'STANDING' ? undefined : '6 6'}
            />
          ))}
          {rows.map((r) => {
            const all = r.ids.every((id) => selected.has(id))
            return (
              <text
                key={r.key}
                role="button"
                tabIndex={0}
                aria-label={`Rangée ${r.row} : ${all ? 'désélectionner' : 'sélectionner'} toute la rangée`}
                x={r.x - 26}
                y={r.y + 6}
                textAnchor="middle"
                fontSize={18}
                className="cursor-pointer font-display outline-none focus-visible:fill-pop-yellow"
                fill={all ? 'var(--pop-yellow)' : 'var(--paper-100)'}
                onClick={() => {
                  toggle(r.ids)
                }}
                onKeyDown={onKey(r.ids)}
              >
                {r.row}
              </text>
            )
          })}
          {seats.map((s) => {
            const isSelected = selected.has(s.id)
            return (
              <g
                key={s.id}
                role="checkbox"
                tabIndex={0}
                aria-checked={isSelected}
                aria-label={`Place ${s.label}${s.blocked ? ', désactivée' : ''}${s.accessible ? ', accessible PMR' : ''}`}
                className="cursor-pointer outline-none [&:focus-visible>circle]:stroke-pop-blue"
                onClick={() => {
                  toggle([s.id])
                }}
                onKeyDown={onKey([s.id])}
              >
                <circle
                  cx={s.x}
                  cy={s.y}
                  r={10}
                  fill={
                    s.blocked ? 'var(--paper-200)' : zoneColor(zones.get(s.zone)?.colorToken ?? '')
                  }
                  fillOpacity={s.blocked ? 0.35 : 1}
                  stroke={
                    isSelected
                      ? 'var(--pop-yellow)'
                      : s.accessible
                        ? 'var(--paper-50)'
                        : 'var(--ink-900)'
                  }
                  strokeWidth={isSelected ? 4 : s.accessible ? 3 : 1.5}
                />
                {s.blocked ? (
                  <path
                    d={`M${String(s.x - 5)} ${String(s.y - 5)}l10 10m0 -10l-10 10`}
                    stroke="var(--paper-50)"
                    strokeWidth={2}
                    pointerEvents="none"
                  />
                ) : null}
              </g>
            )
          })}
        </svg>
      </div>
      <p className="text-sm text-muted">
        Les dates déjà programmées suivent le plan : une place désactivée n’est plus en vente sur
        les dates à venir. Une place déjà vendue, ou en cours d’achat, n’est jamais retirée.
      </p>
    </div>
  )
}
