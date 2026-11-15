import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { Toaster } from '../../design-system/primitives/Toaster'
import type { Order } from '../../lib/api/types'
import { renderRoutes } from '../../test/renderWithProviders'
import { CancelOrder } from './CancelOrder'

const order = (overrides: Partial<Order> = {}): Order => ({
  id: 'o1',
  reference: 'ARG-26-000001',
  status: 'PAID',
  channel: 'ONLINE',
  totalCents: 25000,
  currency: 'MAD',
  performanceId: 'p1',
  eventTitle: 'Le Misanthrope',
  eventSlug: 'x',
  posterUrl: null,
  startsAt: '2026-12-12T19:00:00+00:00',
  createdAt: '2026-11-01T10:00:00+00:00',
  paidAt: '2026-11-01T10:05:00+00:00',
  holdId: null,
  tickets: [],
  ...overrides,
})

const NOW = new Date('2026-11-15T10:00:00Z').getTime()

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('CancelOrder', () => {
  it('asks for confirmation, then cancels and confirms the refund', async () => {
    const user = userEvent.setup()
    const fetchMock = vi
      .fn<typeof fetch>()
      .mockResolvedValue(Response.json(order({ status: 'REFUNDED' })))
    vi.stubGlobal('fetch', fetchMock)
    renderRoutes([
      {
        path: '/',
        element: (
          <>
            <CancelOrder order={order()} now={NOW} />
            <Toaster />
          </>
        ),
      },
    ])

    await user.click(screen.getByRole('button', { name: 'Annuler la commande' }))
    expect(screen.getByRole('dialog', { name: 'Annuler cette commande ?' })).toHaveTextContent(
      /remboursé de 250\sMAD/,
    )
    await user.click(screen.getByRole('button', { name: 'Annuler et me rembourser' }))

    expect(await screen.findByText('Commande annulée et remboursée')).toBeInTheDocument()
    const [input, init] = fetchMock.mock.calls[0] ?? []
    expect(input instanceof Request ? input.url : input?.toString()).toContain('/orders/o1/cancel')
    expect(init?.method).toBe('POST')
  })

  it('explains when online cancellation is closed or not possible', () => {
    const late = new Date('2026-12-11T19:00:00Z').getTime()
    const { unmount } = renderRoutes([
      { path: '/', element: <CancelOrder order={order()} now={late} /> },
    ])
    expect(screen.getByText(/Annulation en ligne close/)).toBeInTheDocument()
    unmount()

    renderRoutes([
      { path: '/', element: <CancelOrder order={order({ channel: 'BOX_OFFICE' })} now={NOW} /> },
    ])
    expect(screen.getByText(/annulation au guichet/)).toBeInTheDocument()
  })
})
