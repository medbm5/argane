import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useApi } from '../../lib/useApi'

export interface Kpi {
  value: number
  previous: number
  delta: number | null
}

export interface Overview {
  from: string
  to: string
  kpis: Record<
    | 'revenueCents'
    | 'tickets'
    | 'orders'
    | 'averageBasketCents'
    | 'fillRate'
    | 'onlineShare'
    | 'refundedOrders',
    Kpi
  >
  daily: { date: string; tickets: number; revenueCents: number }[]
}

export interface PerformanceFill {
  performanceId: string
  eventTitle: string
  startsAt: string
  status: string
  sold: number
  capacity: number
  zones: { code: string; name: string; sold: number; capacity: number }[]
}

export interface RevenueBreakdown {
  byTariff: { tariff: string; tickets: number; revenueCents: number }[]
  byChannel: { channel: string; tickets: number; revenueCents: number }[]
}

export interface CurvePoint {
  daysBefore: number
  rate: number
}

export interface Heatmap {
  layoutId: string
  performances: number
  seats: { label: string; zone: string; x: number; y: number; rate: number }[]
}

export interface AtRisk {
  performanceId: string
  eventTitle: string
  startsAt: string
  daysLeft: number
  rate: number
  expected: number
  sold: number
  capacity: number
}

export interface Range {
  from: string
  to: string
}

const key = (name: string, params: object) => ['analytics', name, params] as const

function useAnalytics<T>(name: string, params: Record<string, string>, enabled = true) {
  const api = useApi()
  return useQuery({
    queryKey: key(name, params),
    queryFn: ({ signal }) => api.get<T>(`/studio/analytics/${name}`, { signal, query: params }),
    placeholderData: keepPreviousData,
    enabled,
  })
}

export const useOverview = (range: Range) => useAnalytics<Overview>('overview', { ...range })
export const useRevenue = (range: Range) => useAnalytics<RevenueBreakdown>('revenue', { ...range })
export const useFill = () => useAnalytics<PerformanceFill[]>('fill', {})
export const useAtRisk = () => useAnalytics<AtRisk[]>('at-risk', {})
export const useBookingCurve = (performanceId: string | null) =>
  useAnalytics<{ reference: CurvePoint[]; performance: CurvePoint[] }>(
    'booking-curve',
    performanceId ? { performance: performanceId } : {},
  )
export const useHeatmap = (layoutId: string | null) =>
  useAnalytics<Heatmap>('heatmap', { layout: layoutId ?? '' }, layoutId !== null)

/** "2026-11-15" for date inputs and the API, in venue days. */
export function dayString(date: Date): string {
  return new Intl.DateTimeFormat('sv-SE', { timeZone: 'Africa/Casablanca' }).format(date)
}

export function rangeOfDays(days: number, now: Date): Range {
  return {
    from: dayString(new Date(now.getTime() - (days - 1) * 86_400_000)),
    to: dayString(now),
  }
}
