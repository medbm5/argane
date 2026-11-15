import { keepPreviousData, useQuery } from '@tanstack/react-query'
import type {
  EventCategory,
  EventDetail,
  EventListItem,
  PerformanceDetail,
  VenueInfo,
} from '../../lib/api/types'
import { useApi } from '../../lib/useApi'

export interface EventFilters {
  category?: EventCategory | ''
  month?: string
}

export const catalogKeys = {
  events: (filters: EventFilters) => ['events', filters] as const,
  event: (slug: string) => ['event', slug] as const,
  performance: (id: string) => ['performance', id] as const,
  venue: ['venue'] as const,
}

export function useEvents(filters: EventFilters = {}) {
  const api = useApi()
  return useQuery({
    queryKey: catalogKeys.events(filters),
    queryFn: ({ signal }) =>
      api.get<EventListItem[]>('/events', {
        signal,
        query: { category: filters.category, month: filters.month },
      }),
    placeholderData: keepPreviousData,
  })
}

export function useEvent(slug: string) {
  const api = useApi()
  return useQuery({
    queryKey: catalogKeys.event(slug),
    queryFn: ({ signal }) => api.get<EventDetail>(`/events/${slug}`, { signal }),
  })
}

export function usePerformance(id: string) {
  const api = useApi()
  return useQuery({
    queryKey: catalogKeys.performance(id),
    queryFn: ({ signal }) => api.get<PerformanceDetail>(`/performances/${id}`, { signal }),
  })
}

export function useVenue() {
  const api = useApi()
  return useQuery({
    queryKey: catalogKeys.venue,
    queryFn: ({ signal }) => api.get<VenueInfo>('/venue', { signal }),
    staleTime: 10 * 60_000,
  })
}

/** Each category owns a pop colour (CSS custom property) used for blocks and swatches. */
export const CATEGORIES: { value: EventCategory; label: string; color: string }[] = [
  { value: 'THEATRE', label: 'Théâtre', color: 'var(--pop-red)' },
  { value: 'CONCERT', label: 'Concert', color: 'var(--pop-yellow)' },
  { value: 'DANCE', label: 'Danse', color: 'var(--pop-blue)' },
  { value: 'COMEDY', label: 'Humour', color: 'var(--pop-pink)' },
  { value: 'CONFERENCE', label: 'Conférence', color: 'var(--pop-teal)' },
]
