import { useMutation, useQuery } from '@tanstack/react-query'
import type { Checkout, Order } from '../../lib/api/types'
import { useApi } from '../../lib/useApi'

export interface CheckoutLines {
  seats: { seatId: string; tariffId: string }[]
  standing: { zoneId: string; tariffId: string; quantity: number }[]
  reason?: string
}

export const orderKeys = {
  order: (id: string) => ['order', id] as const,
}

export function useCheckout(holdId: string) {
  const api = useApi()
  return useMutation({
    mutationFn: (lines: CheckoutLines) => api.post<Checkout>(`/holds/${holdId}/checkout`, lines),
  })
}

const SETTLED = new Set(['PAID', 'EXPIRED', 'CANCELLED', 'REFUNDED', 'PARTIALLY_REFUNDED'])

/** An order; polled every 2 s while its payment is unsettled. */
export function useOrder(orderId: string) {
  const api = useApi()
  return useQuery({
    queryKey: orderKeys.order(orderId),
    queryFn: ({ signal }) => api.get<Order>(`/orders/${orderId}`, { signal }),
    refetchInterval: (query) => {
      const status = query.state.data?.status
      return status && SETTLED.has(status) ? false : 2000
    },
  })
}
