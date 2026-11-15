import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { Schemas } from '../../lib/api/types'
import { useApi } from '../../lib/useApi'

export type CheckInResult = Schemas['CheckInResult']

const countsKey = (performanceId: string) => ['check-in', performanceId, 'counts'] as const

export function useCheckInCounts(performanceId: string) {
  const api = useApi()
  return useQuery({
    queryKey: countsKey(performanceId),
    queryFn: ({ signal }) =>
      api.get<{ admitted: number; expected: number }>(
        `/check-in/performances/${performanceId}/counts`,
        { signal },
      ),
    refetchInterval: 15_000,
  })
}

export function useScan(performanceId: string) {
  const api = useApi()
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (qr: string) =>
      api.post<CheckInResult>(`/check-in/performances/${performanceId}/scan`, { qr }),
    onSuccess: (result) => {
      queryClient.setQueryData(countsKey(performanceId), {
        admitted: result.admitted,
        expected: result.expected,
      })
    },
  })
}
