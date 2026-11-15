import { Select } from '../../design-system/primitives/Select'
import { Stepper } from '../../design-system/primitives/Stepper'
import { formatMoney } from '../../lib/format'
import type { HoldInfo, SeatMap } from '../../lib/api/types'
import { zoneColor } from '../seatmap/api'
import type { TariffChoice } from './tariffs'

/** One tariff per held seat, and a per-tariff split of each standing quantity. */
export function TariffStep({
  hold,
  map,
  choice,
  onChange,
}: {
  hold: HoldInfo
  map: SeatMap
  choice: TariffChoice
  onChange: (choice: TariffChoice) => void
}) {
  const zones = new Map(map.zones.map((z) => [z.id, z]))
  const seats = new Map(map.seats.map((s) => [s.id, s]))
  const money = (cents: number) => formatMoney(cents, map.currency)

  return (
    <div className="space-y-4">
      {hold.seatIds.map((seatId) => {
        const seat = seats.get(seatId)
        const zone = zones.get(seat?.zoneId ?? '')
        if (!seat || !zone) return null
        return (
          <div
            key={seatId}
            className="grid items-end gap-3 border-2 border-fg bg-surface p-4 sm:grid-cols-[1fr_16rem]"
          >
            <div className="flex items-center gap-3">
              <span
                aria-hidden
                className="size-4 border-2 border-ink-900"
                style={{ background: zoneColor(zone.colorToken) }}
              />
              <div>
                <p className="font-display text-2xl leading-none">
                  Rang {seat.row} · place {seat.number}
                </p>
                <p className="text-sm text-muted">{zone.name}</p>
              </div>
            </div>
            <Select
              label={`Tarif, rang ${seat.row} place ${String(seat.number)}`}
              hideLabel
              value={choice.seats[seatId]}
              onValueChange={(tariffId) => {
                onChange({ ...choice, seats: { ...choice.seats, [seatId]: tariffId } })
              }}
              options={zone.prices.map((p) => ({
                value: p.tariffId,
                label: `${p.label} · ${money(p.amountCents)}`,
                description: p.requiresProofAtEntry
                  ? 'Justificatif à l’entrée'
                  : p.minQuantity > 1
                    ? `À partir de ${String(p.minQuantity)} places`
                    : (p.description ?? undefined),
              }))}
            />
          </div>
        )
      })}

      {hold.standing.map((line) => {
        const zone = zones.get(line.zoneId)
        if (!zone) return null
        const split = choice.standing[line.zoneId] ?? {}
        const assigned = Object.values(split).reduce((sum, n) => sum + n, 0)
        return (
          <fieldset key={line.zoneId} className="border-2 border-fg bg-surface p-4">
            <legend className="px-2 font-display text-2xl">
              {zone.name} · {line.quantity} places debout
            </legend>
            <p
              className={`text-sm ${assigned === line.quantity ? 'text-muted' : 'font-bold text-danger'}`}
              aria-live="polite"
            >
              {assigned} / {line.quantity} places réparties
            </p>
            <div className="mt-3 space-y-3">
              {zone.prices.map((p) => {
                const value = split[p.tariffId] ?? 0
                return (
                  <div key={p.tariffId} className="flex items-center justify-between gap-4">
                    <div>
                      <p className="font-bold">{p.label}</p>
                      <p className="text-xs text-muted">
                        {money(p.amountCents)}
                        {p.requiresProofAtEntry ? ' · justificatif à l’entrée' : ''}
                      </p>
                    </div>
                    <Stepper
                      label={`${zone.name}, ${p.label}`}
                      value={value}
                      min={0}
                      max={value + (line.quantity - assigned)}
                      onChange={(quantity) => {
                        onChange({
                          ...choice,
                          standing: {
                            ...choice.standing,
                            [line.zoneId]: { ...split, [p.tariffId]: quantity },
                          },
                        })
                      }}
                    />
                  </div>
                )
              })}
            </div>
          </fieldset>
        )
      })}
    </div>
  )
}
