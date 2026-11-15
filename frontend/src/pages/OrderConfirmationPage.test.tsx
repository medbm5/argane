import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { Order } from '../lib/api/types'
import { renderRoutes } from '../test/renderWithProviders'
import { OrderConfirmationPage } from './OrderConfirmationPage'

const order = (status: string): Order => ({
  id: 'o1',
  reference: 'ARG-26-000042',
  status,
  channel: 'ONLINE',
  totalCents: 25000,
  currency: 'MAD',
  performanceId: 'p1',
  eventTitle: 'Le Misanthrope',
  eventSlug: 'le-misanthrope',
  posterUrl: null,
  startsAt: '2026-12-12T19:00:00+00:00',
  createdAt: '2026-11-15T10:00:00+00:00',
  paidAt: null,
  holdId: 'h1',
  tickets: [
    {
      id: 't1',
      zone: 'Orchestre',
      seat: 'A-1',
      tariff: 'Plein',
      priceCents: 25000,
      status: 'VALID',
      requiresProof: false,
    },
  ],
})

afterEach(() => {
  vi.unstubAllGlobals()
})

const routes = [{ path: '/commandes/:orderId/confirmation', element: <OrderConfirmationPage /> }]

describe('OrderConfirmationPage', () => {
  it('waits for the payment, then shows the tickets', async () => {
    const fetchMock = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(Response.json(order('PENDING_PAYMENT')))
      .mockResolvedValue(Response.json(order('PAID')))
    vi.stubGlobal('fetch', fetchMock)
    renderRoutes(routes, { path: '/commandes/o1/confirmation' })

    expect(await screen.findByText('Paiement en cours…')).toBeInTheDocument()
    expect(await screen.findByText('C’est réservé !', {}, { timeout: 4000 })).toBeInTheDocument()
    expect(screen.getByText('Commande ARG-26-000042')).toBeInTheDocument()
    expect(screen.getByText('Place A-1')).toBeInTheDocument()
  })

  it('offers a retry after a declined card', async () => {
    vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValue(Response.json(order('FAILED'))))
    renderRoutes(routes, { path: '/commandes/o1/confirmation' })

    expect(await screen.findByText('Paiement refusé')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Réessayer' })).toHaveAttribute('href', '/commande/h1')
  })

  it('explains an expired, refunded late payment', async () => {
    vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValue(Response.json(order('EXPIRED'))))
    renderRoutes(routes, { path: '/commandes/o1/confirmation' })

    expect(await screen.findByText('Commande non aboutie')).toBeInTheDocument()
    expect(screen.getByText(/remboursement est déjà en route/)).toBeInTheDocument()
  })
})
