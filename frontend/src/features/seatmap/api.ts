import { useQuery } from '@tanstack/react-query'
import type { Availability, SeatMap, ZonePrice } from '../../lib/api/types'
import { useApi } from '../../lib/useApi'

export const POLL_INTERVAL_MS = 5000

export const seatMapKeys = {
  map: (id: string, channel: string) => ['seatmap', id, channel] as const,
  availability: (id: string) => ['availability', id] as const,
}

export function useSeatMap(performanceId: string, channel: 'ONLINE' | 'BOX_OFFICE' = 'ONLINE') {
  const api = useApi()
  return useQuery({
    queryKey: seatMapKeys.map(performanceId, channel),
    queryFn: ({ signal }) =>
      api.get<SeatMap>(`/performances/${performanceId}/seatmap`, { signal, query: { channel } }),
    staleTime: 60_000,
  })
}

/**
 * Polled every 5 s. The endpoint sends an ETag with `no-cache`: the browser revalidates
 * each poll and gets a 304 when nothing moved, and structural sharing means React only
 * re-renders seats whose state changed.
 */
export function useAvailability(performanceId: string, enabled = true) {
  const api = useApi()
  return useQuery({
    queryKey: seatMapKeys.availability(performanceId),
    queryFn: ({ signal }) =>
      api.get<Availability>(`/performances/${performanceId}/availability`, { signal }),
    refetchInterval: POLL_INTERVAL_MS,
    enabled,
  })
}

/** Cheapest single-ticket paid price in a zone (the "dès" price on the plan). */
export function zoneFromPrice(prices: ZonePrice[]): number | null {
  const amounts = prices
    .filter((p) => p.amountCents > 0 && p.minQuantity <= 1)
    .map((p) => p.amountCents)
  return amounts.length > 0 ? Math.min(...amounts) : null
}

/** Full-price ticket in a zone (indicative basket total), falling back to the cheapest. */
export function zoneFullPrice(prices: ZonePrice[]): number | null {
  return prices.find((p) => p.tariffCode === 'FULL')?.amountCents ?? zoneFromPrice(prices)
}

/** Zone colour tokens come from the legacy palette names; map them to the pop palette. */
const ZONE_COLORS: Record<string, string> = {
  'bordeaux-700': 'var(--pop-red)',
  'gilt-400': 'var(--pop-yellow)',
  'sepia-600': 'var(--pop-orange)',
  'olive-600': 'var(--pop-teal)',
}

export function zoneColor(token: string): string {
  return ZONE_COLORS[token] ?? `var(--${token}, var(--pop-blue))`
}
