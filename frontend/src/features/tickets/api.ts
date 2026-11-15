import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useState } from 'react'
import { useAuthState } from '../../app/auth/AuthContext'
import { toast } from '../../design-system/toast-store'
import { apiBlob, ApiError } from '../../lib/api'
import type { Order } from '../../lib/api/types'
import { useApi } from '../../lib/useApi'

export const walletKeys = { wallet: ['wallet'] as const }

/** The buyer's ticketed orders, soonest performance first (with QR payloads). */
export function useWallet() {
  const api = useApi()
  return useQuery({
    queryKey: walletKeys.wallet,
    queryFn: ({ signal }) => api.get<Order[]>('/me/orders', { signal }),
  })
}

/** Downloads a PDF from the API with the session token, as a named file. */
export function useDownload() {
  const { getToken } = useAuthState()
  const [busy, setBusy] = useState<string | null>(null)

  const download = useCallback(
    async (path: string, filename: string) => {
      setBusy(path)
      try {
        const blob = await apiBlob(path, getToken)
        const url = URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = filename
        link.click()
        setTimeout(() => {
          URL.revokeObjectURL(url)
        }, 10_000)
      } catch (error) {
        toast(error instanceof ApiError ? error.message : 'Le téléchargement a échoué.', {
          tone: 'danger',
        })
      } finally {
        setBusy(null)
      }
    },
    [getToken],
  )

  return { download, busy }
}

/** Online cancellation closes this long before the show (mirrors the API policy). */
export const CANCELLATION_NOTICE_HOURS = 48

export function cancellableUntil(order: Order): Date {
  return new Date(new Date(order.startsAt).getTime() - CANCELLATION_NOTICE_HOURS * 3_600_000)
}

export function useCancelOrder() {
  const api = useApi()
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (orderId: string) => api.post<Order>(`/orders/${orderId}/cancel`),
    onSettled: () => queryClient.invalidateQueries({ queryKey: walletKeys.wallet }),
  })
}
