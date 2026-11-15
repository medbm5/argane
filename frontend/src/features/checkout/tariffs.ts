import type { HoldInfo, SeatMap, ZonePrice } from '../../lib/api/types'
import type { CheckoutLines } from './api'

/** Tariff picked for each held seat, and the split of each standing quantity by tariff. */
export interface TariffChoice {
  seats: Record<string, string>
  standing: Record<string, Record<string, number>>
}

function defaultTariff(prices: ZonePrice[]): ZonePrice | undefined {
  return prices.find((p) => p.tariffCode === 'FULL') ?? prices.find((p) => p.minQuantity <= 1)
}

/** Everyone starts at full price; standing quantities all on full price. */
export function initialChoice(hold: HoldInfo, map: SeatMap): TariffChoice {
  const zones = new Map(map.zones.map((z) => [z.id, z]))
  const seatZone = new Map(map.seats.map((s) => [s.id, s.zoneId]))
  const seats: Record<string, string> = {}
  for (const seatId of hold.seatIds) {
    const tariff = defaultTariff(zones.get(seatZone.get(seatId) ?? '')?.prices ?? [])
    if (tariff) seats[seatId] = tariff.tariffId
  }
  const standing: Record<string, Record<string, number>> = {}
  for (const line of hold.standing) {
    const zone = line.zoneId
    const tariff = defaultTariff(zones.get(zone)?.prices ?? [])
    if (tariff) standing[zone] = { [tariff.tariffId]: line.quantity }
  }
  return { seats, standing }
}

export function toLines(choice: TariffChoice, reason?: string): CheckoutLines {
  return {
    seats: Object.entries(choice.seats).map(([seatId, tariffId]) => ({ seatId, tariffId })),
    standing: Object.entries(choice.standing).flatMap(([zoneId, split]) =>
      Object.entries(split)
        .filter(([, quantity]) => quantity > 0)
        .map(([tariffId, quantity]) => ({ zoneId, tariffId, quantity })),
    ),
    ...(reason ? { reason } : {}),
  }
}

/** Live total of the current choice, from the zone price grid. */
export function choiceTotal(choice: TariffChoice, map: SeatMap): number {
  const zones = new Map(map.zones.map((z) => [z.id, z]))
  const seatZone = new Map(map.seats.map((s) => [s.id, s.zoneId]))
  const price = (zoneId: string, tariffId: string) =>
    zones.get(zoneId)?.prices.find((p) => p.tariffId === tariffId)?.amountCents ?? 0
  let total = 0
  for (const [seatId, tariffId] of Object.entries(choice.seats)) {
    total += price(seatZone.get(seatId) ?? '', tariffId)
  }
  for (const [zoneId, split] of Object.entries(choice.standing)) {
    for (const [tariffId, quantity] of Object.entries(split))
      total += price(zoneId, tariffId) * quantity
  }
  return total
}

/** Standing splits must add up to what was held. */
export function standingBalanced(choice: TariffChoice, hold: HoldInfo): boolean {
  return hold.standing.every((line) => {
    const split = choice.standing[line.zoneId] ?? {}
    return Object.values(split).reduce((sum, n) => sum + n, 0) === line.quantity
  })
}

export function needsProof(choice: TariffChoice, map: SeatMap): boolean {
  const all = map.zones.flatMap((z) => z.prices)
  const used = new Set([
    ...Object.values(choice.seats),
    ...Object.values(choice.standing).flatMap((split) =>
      Object.entries(split)
        .filter(([, n]) => n > 0)
        .map(([id]) => id),
    ),
  ])
  return all.some((p) => used.has(p.tariffId) && p.requiresProofAtEntry)
}
