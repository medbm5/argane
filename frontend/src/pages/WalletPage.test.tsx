import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { Order } from '../lib/api/types'
import { renderRoutes } from '../test/renderWithProviders'
import { OrderDetailPage } from './OrderDetailPage'
import { WalletPage } from './WalletPage'

const order = (id: string, startsAt: string, status = 'PAID'): Order => ({
  id,
  reference: `ARG-26-00000${id}`,
  status,
  channel: 'ONLINE',
  totalCents: 25000,
  currency: 'MAD',
  performanceId: 'p1',
  eventTitle: id === '1' ? 'Le Misanthrope' : 'Tamra',
  eventSlug: 'x',
  posterUrl: null,
  startsAt,
  createdAt: '2026-11-01T10:00:00+00:00',
  paidAt: '2026-11-01T10:05:00+00:00',
  holdId: null,
  tickets: [
    {
      id: `t${id}`,
      zone: 'Orchestre',
      seat: 'A-1',
      tariff: 'Plein',
      priceCents: 25000,
      status: status === 'PAID' ? 'VALID' : 'REFUNDED',
      requiresProof: false,
      qr: status === 'PAID' ? 'ARG1.abc.def' : null,
    },
  ],
})

afterEach(() => {
  vi.unstubAllGlobals()
})

const routes = [
  { path: '/mes-billets', element: <WalletPage /> },
  { path: '/mes-billets/:orderId', element: <OrderDetailPage /> },
]

describe('Wallet', () => {
  it('splits upcoming and past orders and opens an order with its QR codes', async () => {
    const user = userEvent.setup()
    vi.stubGlobal(
      'fetch',
      vi
        .fn<typeof fetch>()
        .mockImplementation(() =>
          Promise.resolve(
            Response.json([
              order('2', '2020-01-01T19:00:00+00:00', 'REFUNDED'),
              order('1', '2099-12-12T19:00:00+00:00'),
            ]),
          ),
        ),
    )
    const { router } = renderRoutes(routes, { path: '/mes-billets' })

    const upcoming = await screen.findByRole('region', { name: 'À venir' })
    expect(within(upcoming).getByText('Le Misanthrope')).toBeInTheDocument()
    expect(
      within(screen.getByRole('region', { name: 'Passés' })).getByText('Tamra'),
    ).toBeInTheDocument()

    await user.click(within(upcoming).getByRole('link', { name: 'Voir les billets' }))
    expect(router.state.location.pathname).toBe('/mes-billets/1')
    expect(
      await screen.findByRole('img', { name: 'QR code du billet place A-1' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Télécharger les billets/ })).toBeInTheDocument()
  })

  it('invites to the programme when there are no tickets yet', async () => {
    vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValue(Response.json([])))
    renderRoutes(routes, { path: '/mes-billets' })

    expect(await screen.findByText('Pas encore de billets')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Voir la programmation/ })).toHaveAttribute(
      'href',
      '/programme',
    )
  })
})
