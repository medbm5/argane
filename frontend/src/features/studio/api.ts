import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { Schemas } from '../../lib/api/types'
import { useApi } from '../../lib/useApi'

export type StudioEvent = Schemas['StudioEventView']
export type StudioPerformance = StudioEvent['performances'][number]
export type Tariff = Schemas['TariffView']
export type PriceGrid = Omit<Schemas['PriceGridView'], 'zones' | 'cells'> & {
  zones: { id: string; code: string; name: string; kind: string }[]
  cells: { zoneId: string; tariffId: string; amountCents: number }[]
}
export type Layout = Omit<Schemas['LayoutView'], 'zones' | 'seats' | 'viewBox' | 'stage'> & {
  viewBox: { width: number; height: number }
  stage: { x: number; y: number; width: number; height: number }
  zones: {
    code: string
    name: string
    kind: string
    colorToken: string
    shape: string
    capacity: number
    seats: number
  }[]
  seats: {
    /** Null only in a generator preview (not saved yet). */
    id: string | null
    zone: string
    row: string
    number: number
    label: string
    x: number
    y: number
    accessible: boolean
    blocked: boolean
  }[]
}
export type SalesRow = {
  id: string
  reference: string
  createdAt: string
  paidAt: string | null
  status: string
  channel: string
  eventTitle: string
  performanceStartsAt: string
  buyer: string | null
  buyerEmail: string | null
  seller: string | null
  tickets: number
  totalCents: number
}
export type SalesPage = Omit<Schemas['SalesPageView'], 'rows'> & { rows: SalesRow[] }

export interface EventForm {
  title: string
  subtitle: string | null
  category: string
  description: string
  durationMinutes: number
  ageRating: number | null
  posterPath: string | null
}

export interface PerformanceForm {
  startsAt: string
  salesOpenAt: string
  salesCloseAt: string | null
  doorsMinutesBefore: number
  layoutId: string
}

export interface ZoneForm {
  code: string
  name: string
  kind: 'SEATED' | 'STANDING'
  x: number
  y: number
  width: number
  height: number
  colorToken: string
  rowFrom?: string
  rowTo?: string
  skipConfusableRows?: boolean
  seatsPerRow?: number
  arrangement?: 'GRID' | 'BOXES'
  aisleAfter?: number | null
  curve?: number
  blockedSeats?: string[]
  accessibleSeats?: string[]
  capacity?: number | null
}

export const studioKeys = {
  events: ['studio', 'events'] as const,
  event: (id: string) => ['studio', 'event', id] as const,
  layouts: ['studio', 'layouts'] as const,
  tariffs: ['studio', 'tariffs'] as const,
  grid: (performanceId: string) => ['studio', 'grid', performanceId] as const,
  sales: (filters: Record<string, string>) => ['studio', 'sales', filters] as const,
}

export function useStudioEvents() {
  const api = useApi()
  return useQuery({
    queryKey: studioKeys.events,
    queryFn: ({ signal }) => api.get<StudioEvent[]>('/studio/events', { signal }),
  })
}

export function useStudioEvent(id: string) {
  const api = useApi()
  return useQuery({
    queryKey: studioKeys.event(id),
    queryFn: ({ signal }) => api.get<StudioEvent>(`/studio/events/${id}`, { signal }),
  })
}

/** Mutations that return the refreshed event: the cache is updated in place. */
function useEventMutation<T>(fn: (input: T) => Promise<StudioEvent>) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: (event) => {
      queryClient.setQueryData(studioKeys.event(event.id), event)
      void queryClient.invalidateQueries({ queryKey: studioKeys.events })
    },
  })
}

export function useSaveEvent(id?: string) {
  const api = useApi()
  return useEventMutation((form: EventForm) =>
    id
      ? api.put<StudioEvent>(`/studio/events/${id}`, form)
      : api.post<StudioEvent>('/studio/events', form),
  )
}

/** Uploads a poster image; resolves to the path to store as the event's poster. */
export function useUploadPoster() {
  const api = useApi()
  return useMutation({
    mutationFn: (file: File) => {
      const body = new FormData()
      body.append('file', file)
      return api.post<{ path: string }>('/studio/posters', body)
    },
  })
}

export function useEventAction(eventId: string) {
  const api = useApi()
  return useEventMutation((action: 'publish' | 'archive') =>
    api.post<StudioEvent>(`/studio/events/${eventId}/${action}`),
  )
}

export function useSchedulePerformance(eventId: string) {
  const api = useApi()
  return useEventMutation(({ id, form }: { id?: string; form: PerformanceForm }) =>
    id
      ? api.put<StudioEvent>(`/studio/performances/${id}`, form)
      : api.post<StudioEvent>(`/studio/events/${eventId}/performances`, form),
  )
}

export type PerformanceAction = 'publish' | 'publish-open' | 'open-sales' | 'close-sales'

export function usePerformanceAction() {
  const api = useApi()
  return useEventMutation(({ id, action }: { id: string; action: PerformanceAction }) =>
    action === 'publish' || action === 'publish-open'
      ? api.post<StudioEvent>(`/studio/performances/${id}/publish`, {
          openSales: action === 'publish-open',
        })
      : api.post<StudioEvent>(`/studio/performances/${id}/${action}`),
  )
}

export function useCancelPerformance(eventId: string) {
  const api = useApi()
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string }) =>
      api.post<{ status: string }>(`/studio/performances/${id}/cancel`, { reason }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: studioKeys.event(eventId) }),
  })
}

export function useLayouts() {
  const api = useApi()
  return useQuery({
    queryKey: studioKeys.layouts,
    queryFn: ({ signal }) => api.get<Layout[]>('/studio/layouts', { signal }),
  })
}

/** One plan with all its seats (the Studio's "Salle" page). */
export function useLayout(id: string | undefined) {
  const api = useApi()
  return useQuery({
    queryKey: [...studioKeys.layouts, id],
    queryFn: ({ signal }) => api.get<Layout>(`/studio/layouts/${id ?? ''}`, { signal }),
    enabled: id !== undefined,
  })
}

export interface SeatBlockingResult {
  layout: Layout
  /** Seats changed across upcoming dates. */
  seatsUpdated: number
  /** Seats left as they were because sold or being paid for. */
  seatsKept: number
}

/** Switches seats of a plan on or off; upcoming dates follow where the seat is free. */
export function useSetSeatsBlocked(layoutId: string) {
  const api = useApi()
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ seatIds, blocked }: { seatIds: string[]; blocked: boolean }) =>
      api.put<SeatBlockingResult>(`/studio/layouts/${layoutId}/seats`, { seatIds, blocked }),
    onSuccess: (result) => {
      queryClient.setQueryData([...studioKeys.layouts, layoutId], result.layout)
      void queryClient.invalidateQueries({ queryKey: studioKeys.layouts, exact: true })
    },
  })
}

export function useTariffs() {
  const api = useApi()
  return useQuery({
    queryKey: studioKeys.tariffs,
    queryFn: ({ signal }) => api.get<Tariff[]>('/studio/tariffs', { signal }),
  })
}

export type TariffForm = Omit<Tariff, 'id' | 'active'>

export function useTariffMutations() {
  const api = useApi()
  const queryClient = useQueryClient()
  const refresh = () => queryClient.invalidateQueries({ queryKey: studioKeys.tariffs })
  return {
    save: useMutation({
      mutationFn: ({ id, form }: { id?: string; form: TariffForm }) =>
        id
          ? api.put<Tariff>(`/studio/tariffs/${id}`, form)
          : api.post<Tariff>('/studio/tariffs', form),
      onSuccess: refresh,
    }),
    toggle: useMutation({
      mutationFn: (tariff: Tariff) =>
        api.post<Tariff>(
          `/studio/tariffs/${tariff.id}/${tariff.active ? 'deactivate' : 'activate'}`,
        ),
      onSuccess: refresh,
    }),
  }
}

export function usePriceGrid(performanceId: string) {
  const api = useApi()
  return useQuery({
    queryKey: studioKeys.grid(performanceId),
    queryFn: ({ signal }) =>
      api.get<PriceGrid>(`/studio/performances/${performanceId}/prices`, { signal }),
  })
}

export function usePriceGridMutations(performanceId: string) {
  const api = useApi()
  const queryClient = useQueryClient()
  return {
    save: useMutation({
      mutationFn: (cells: { zoneId: string; tariffId: string; amountCents: number | null }[]) =>
        api.put<PriceGrid>(`/studio/performances/${performanceId}/prices`, { cells }),
      onSuccess: (grid) => {
        queryClient.setQueryData(studioKeys.grid(performanceId), grid)
      },
    }),
    applyToEvent: useMutation({
      mutationFn: () =>
        api.post<{ updated: number }>(
          `/studio/performances/${performanceId}/prices/apply-to-event`,
        ),
      onSuccess: () => queryClient.invalidateQueries({ queryKey: ['studio'] }),
    }),
  }
}

export function useSales(filters: Record<string, string>) {
  const api = useApi()
  return useQuery({
    queryKey: studioKeys.sales(filters),
    queryFn: ({ signal }) => api.get<SalesPage>('/studio/sales', { signal, query: filters }),
    placeholderData: keepPreviousData,
  })
}
