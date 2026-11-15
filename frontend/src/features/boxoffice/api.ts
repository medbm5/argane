import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthState } from '../../app/auth/AuthContext'
import { apiBlob } from '../../lib/api'
import type { Order, Schemas } from '../../lib/api/types'
import { useApi } from '../../lib/useApi'
import type { CheckoutLines } from '../checkout/api'

export type CounterPerformance = Schemas['CounterPerformanceView']
export type PendingRefund = Schemas['PendingRefundView']
export type PaymentMethod = 'CASH' | 'CARD_TERMINAL' | 'NONE'

export const boxOfficeKeys = {
  performances: ['box-office', 'performances'] as const,
  refunds: ['box-office', 'refunds'] as const,
}

export function useCounterPerformances() {
  const api = useApi()
  return useQuery({
    queryKey: boxOfficeKeys.performances,
    queryFn: ({ signal }) => api.get<CounterPerformance[]>('/box-office/performances', { signal }),
    refetchInterval: 30_000,
  })
}

export interface CounterSale extends CheckoutLines {
  method: PaymentMethod
  buyerName?: string
  buyerEmail?: string
}

export function useCounterSale() {
  const api = useApi()
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ holdId, sale }: { holdId: string; sale: CounterSale }) =>
      api.post<Order>(`/box-office/holds/${holdId}/sell`, sale),
    onSettled: () => queryClient.invalidateQueries({ queryKey: boxOfficeKeys.performances }),
  })
}

export function usePendingRefunds() {
  const api = useApi()
  return useQuery({
    queryKey: boxOfficeKeys.refunds,
    queryFn: ({ signal }) => api.get<PendingRefund[]>('/box-office/refunds', { signal }),
  })
}

export function useSettleRefund() {
  const api = useApi()
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (refundId: string) =>
      api.post<PendingRefund>(`/box-office/refunds/${refundId}/settle`),
    onSettled: () => queryClient.invalidateQueries({ queryKey: boxOfficeKeys.refunds }),
  })
}

export function useOrderLookup() {
  const api = useApi()
  return useMutation({
    mutationFn: (reference: string) =>
      api.get<Order>('/box-office/orders', {
        query: { reference: reference.trim().toUpperCase() },
      }),
  })
}

export function useCounterCancel() {
  const api = useApi()
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (orderId: string) => api.post<Order>(`/box-office/orders/${orderId}/cancel`),
    onSettled: () => queryClient.invalidateQueries({ queryKey: boxOfficeKeys.performances }),
  })
}

/** Opens the order's tickets in a new tab for printing (falls back to a download). */
export function usePrintTickets() {
  const { getToken } = useAuthState()
  return async (orderId: string, reference: string) => {
    const blob = await apiBlob(`/orders/${orderId}/tickets.pdf`, getToken)
    const url = URL.createObjectURL(blob)
    const tab = window.open(url, '_blank', 'noopener')
    if (!tab) {
      const link = document.createElement('a')
      link.href = url
      link.download = `billets-${reference}.pdf`
      link.click()
    }
    setTimeout(() => {
      URL.revokeObjectURL(url)
    }, 60_000)
  }
}
