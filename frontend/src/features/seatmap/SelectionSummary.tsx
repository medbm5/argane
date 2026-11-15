import { X } from 'lucide-react'
import { Button } from '../../design-system/primitives/Button'
import { Stepper } from '../../design-system/primitives/Stepper'
import { formatMoney } from '../../lib/format'
import type { Availability, SeatMap } from '../../lib/api/types'
import { zoneColor, zoneFullPrice } from './api'
import { selectedCount, type Selection, type SelectionAction } from './selection'

/**
 * The basket beside the plan: chosen seats, standing quantities (with the picker for the
 * zone just clicked), an indicative total at the lowest full price, and the next step.
 */
export function SelectionSummary({
  map,
  availability,
  selection,
  dispatch,
  activeStandingZoneId,
  onContinue,
  continueLabel = 'Continuer',
  busy = false,
  locked = false,
  onModify,
}: {
  map: SeatMap
  availability: Availability | undefined
  selection: Selection
  dispatch: (action: SelectionAction) => void
  activeStandingZoneId: string | null
  onContinue: () => void
  continueLabel?: string
  busy?: boolean
  /** Places are held: no edits until the buyer chooses to modify. */
  locked?: boolean
  onModify?: () => void
}) {
  const count = selectedCount(selection)
  const max = map.maxSeatsPerOrder
  const zones = new Map(map.zones.map((z) => [z.id, z]))
  const seats = new Map(map.seats.map((s) => [s.id, s]))

  const standingZones = map.zones.filter(
    (z) => z.kind === 'STANDING' && (z.id === activeStandingZoneId || selection.standing[z.id]),
  )
  let total = 0
  for (const id of selection.seatIds) {
    const seat = seats.get(id)
    total += zoneFullPrice(zones.get(seat?.zoneId ?? '')?.prices ?? []) ?? 0
  }
  for (const [zoneId, qty] of Object.entries(selection.standing)) {
    total += (zoneFullPrice(zones.get(zoneId)?.prices ?? []) ?? 0) * qty
  }

  return (
    <section
      aria-labelledby="selection-title"
      className="flex flex-col border-2 border-paper-100 bg-night-900 p-5 shadow-[6px_6px_0_var(--pop-yellow)]"
    >
      <div className="flex items-baseline justify-between gap-3">
        <h2 id="selection-title" className="font-display text-3xl">
          Vos places
        </h2>
        <span className="tabular text-sm font-bold" aria-live="polite">
          {count} / {max}
        </span>
      </div>

      {count === 0 && standingZones.length === 0 ? (
        <p className="mt-4 text-sm text-muted">
          Touchez un fauteuil sur le plan, ou une zone debout pour choisir un nombre de places.
        </p>
      ) : null}

      <ul className="mt-4 space-y-2">
        {selection.seatIds.map((id) => {
          const seat = seats.get(id)
          const zone = zones.get(seat?.zoneId ?? '')
          if (!seat || !zone) return null
          const from = zoneFullPrice(zone.prices)
          return (
            <li key={id} className="flex items-center gap-3 border-b border-paper-100/15 pb-2">
              <span
                aria-hidden
                className="size-3.5 shrink-0 border-2 border-ink-900"
                style={{ background: zoneColor(zone.colorToken) }}
              />
              <span className="flex-1">
                <span className="font-bold">
                  Rang {seat.row} · place {seat.number}
                </span>
                <span className="block text-xs text-muted">{zone.name}</span>
              </span>
              {from != null ? (
                <span className="tabular text-sm">{formatMoney(from, map.currency)}</span>
              ) : null}
              <button
                type="button"
                hidden={locked}
                aria-label={`Retirer rang ${seat.row} place ${String(seat.number)}`}
                onClick={() => {
                  dispatch({ type: 'toggleSeat', seatId: id })
                }}
                className="grid size-8 place-items-center hover:bg-pop-red hover:text-paper-50"
              >
                <X aria-hidden className="size-4" />
              </button>
            </li>
          )
        })}
      </ul>

      {standingZones.map((zone) => {
        const quantity = selection.standing[zone.id] ?? 0
        const remaining =
          availability?.standing.find((s) => s.zoneId === zone.id)?.remaining ?? zone.capacity ?? 0
        const room = max - (count - quantity)
        const from = zoneFullPrice(zone.prices)
        return (
          <div key={zone.id} className="mt-4 border-2 border-paper-100/40 p-3">
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="font-bold">{zone.name}</p>
                <p className="text-xs text-muted">
                  {remaining} places restantes
                  {from != null ? ` · ${formatMoney(from, map.currency)} la place` : ''}
                </p>
              </div>
              <Stepper
                className={locked ? 'pointer-events-none opacity-60' : undefined}
                label={zone.name}
                value={quantity}
                min={0}
                max={Math.min(room, remaining)}
                onChange={(value) => {
                  dispatch({ type: 'setStanding', zoneId: zone.id, quantity: value })
                }}
              />
            </div>
          </div>
        )
      })}

      <div className="mt-6 flex items-baseline justify-between border-t-2 border-paper-100 pt-4">
        <span className="text-sm font-bold tracking-[0.12em] uppercase">Total indicatif</span>
        <span className="tabular font-display text-3xl">{formatMoney(total, map.currency)}</span>
      </div>
      <p className="mt-1 text-xs text-muted">
        Plein tarif. Tarifs réduits et groupes au moment de payer.
      </p>
      <Button
        variant="gilt"
        size="lg"
        block
        className="mt-5"
        disabled={count === 0}
        loading={busy}
        onClick={onContinue}
      >
        {continueLabel}
      </Button>
      {onModify ? (
        <Button variant="ghost" block className="mt-2 text-paper-100" onClick={onModify}>
          Modifier ma sélection
        </Button>
      ) : null}
      {count >= max ? (
        <p className="mt-3 text-xs font-bold text-pop-yellow">{max} places maximum par commande.</p>
      ) : null}
    </section>
  )
}
