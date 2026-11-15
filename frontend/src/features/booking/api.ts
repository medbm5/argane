import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { HoldInfo } from '../../lib/api/types'
import { useApi } from '../../lib/useApi'
import { seatMapKeys } from '../seatmap/api'
import type { Selection } from '../seatmap/selection'

export type Hold = HoldInfo

export const holdKeys = {
  hold: (id: string) => ['hold', id] as const,
}

export function useHold(holdId: string) {
  const api = useApi()
  return useQuery({
    queryKey: holdKeys.hold(holdId),
    queryFn: ({ signal }) => api.get<Hold>(`/holds/${holdId}`, { signal }),
  })
}

/** Holds the current selection; refreshes availability so our seats show as ours. */
export function useCreateHold(performanceId: string, channel: 'ONLINE' | 'BOX_OFFICE' = 'ONLINE') {
  const api = useApi()
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (selection: Selection) =>
      api.post<Hold>(`/performances/${performanceId}/holds`, {
        seatIds: selection.seatIds,
        standing: Object.entries(selection.standing).map(([zoneId, quantity]) => ({
          zoneId,
          quantity,
        })),
        channel,
      }),
    onSuccess: (hold) => {
      queryClient.setQueryData(holdKeys.hold(hold.id), hold)
    },
    onSettled: () =>
      queryClient.invalidateQueries({ queryKey: seatMapKeys.availability(performanceId) }),
  })
}

export function useReleaseHold(performanceId: string) {
  const api = useApi()
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (holdId: string) => api.del<undefined>(`/holds/${holdId}`),
    onSettled: () =>
      queryClient.invalidateQueries({ queryKey: seatMapKeys.availability(performanceId) }),
  })
}

/** Remember an unfinished selection across the sign-in redirect (this tab only). */
const storageKey = (performanceId: string) => `argane.selection.${performanceId}`

export function saveSelection(performanceId: string, selection: Selection): void {
  try {
    sessionStorage.setItem(storageKey(performanceId), JSON.stringify(selection))
  } catch {
    // Private mode or storage disabled: the buyer simply picks again.
  }
}

export function clearSavedSelection(performanceId: string): void {
  try {
    sessionStorage.removeItem(storageKey(performanceId))
  } catch {
    // Nothing to clear.
  }
}

/** Pure read (safe in a reducer initialiser, which StrictMode may call twice). */
export function readSavedSelection(performanceId: string): Selection | null {
  try {
    const raw = sessionStorage.getItem(storageKey(performanceId))
    if (!raw) return null
    const parsed = JSON.parse(raw) as Partial<Selection>
    return Array.isArray(parsed.seatIds) && typeof parsed.standing === 'object'
      ? {
          seatIds: parsed.seatIds.filter((id) => typeof id === 'string'),
          standing: parsed.standing ?? {},
        }
      : null
  } catch {
    return null
  }
}
