import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { SeatMap } from '../lib/api/types'
import { renderRoutes } from '../test/renderWithProviders'
import { GuichetPage } from './GuichetPage'

const map: SeatMap = {
  performanceId: 'p1',
  layoutId: 'l1',
  layoutName: 'Configuration test',
  channel: 'BOX_OFFICE',
  currency: 'MAD',
  viewBox: { width: 1000, height: 700 },
  stage: { x: 250, y: 18, width: 500, height: 56 },
  maxSeatsPerOrder: 10,
  zones: [
    {
      id: 'z1',
      code: 'ORCHESTRE',
      name: 'Orchestre',
      kind: 'SEATED',
      colorToken: 'bordeaux-700',
      sortOrder: 0,
      shape: 'M100 100 H400 V300 H100 Z',
      capacity: null,
      prices: [
        {
          tariffId: 'full',
          tariffCode: 'FULL',
          label: 'Plein',
          description: null,
          amountCents: 25000,
          minQuantity: 1,
          maxQuantity: null,
          requiresProofAtEntry: false,
          requiresReason: false,
        },
      ],
    },
  ],
  seats: [
    {
      id: 's1',
      zoneId: 'z1',
      row: 'A',
      number: 1,
      label: 'A-1',
      x: 120,
      y: 120,
      accessible: false,
    },
  ],
}

const performance = {
  id: 'p1',
  eventTitle: 'Le Misanthrope',
  eventSlug: 'le-misanthrope',
  category: 'Théâtre',
  posterUrl: null,
  startsAt: '2026-11-20T19:00:00+00:00',
  status: 'ON_SALE',
  sellable: true,
  onlineOpen: true,
  remaining: 160,
  capacity: 398,
  layoutName: 'Configuration théâtre',
}

const calls: { url: string; method: string; body: unknown }[] = []

beforeEach(() => {
  calls.length = 0
  vi.stubGlobal(
    'fetch',
    vi.fn<typeof fetch>((input, init) => {
      const url = input instanceof Request ? input.url : input.toString()
      const method = init?.method ?? 'GET'
      calls.push({
        url,
        method,
        body: typeof init?.body === 'string' ? (JSON.parse(init.body) as unknown) : null,
      })
      if (url.includes('/box-office/performances'))
        return Promise.resolve(Response.json([performance]))
      if (url.includes('/seatmap')) return Promise.resolve(Response.json(map))
      if (url.includes('/availability'))
        return Promise.resolve(
          Response.json({
            performanceId: 'p1',
            status: 'ON_SALE',
            isOnSale: true,
            remaining: 1,
            held: [],
            mine: [],
            sold: [],
            blocked: [],
            standing: [],
          }),
        )
      if (url.includes('/holds') && !url.includes('/sell') && method === 'POST')
        return Promise.resolve(
          Response.json(
            {
              id: 'h1',
              performanceId: 'p1',
              status: 'ACTIVE',
              channel: 'BOX_OFFICE',
              expiresAt: '2099-01-01T00:00:00Z',
              secondsLeft: 600,
              seatIds: ['s1'],
              standing: [],
            },
            { status: 201 },
          ),
        )
      if (url.includes('/sell'))
        return Promise.resolve(
          Response.json(
            {
              id: 'o1',
              reference: 'ARG-26-000777',
              status: 'PAID',
              channel: 'BOX_OFFICE',
              totalCents: 25000,
              currency: 'MAD',
              performanceId: 'p1',
              eventTitle: 'Le Misanthrope',
              eventSlug: 'le-misanthrope',
              posterUrl: null,
              startsAt: '2026-11-20T19:00:00+00:00',
              createdAt: '2026-11-15T10:00:00+00:00',
              paidAt: '2026-11-15T10:00:00+00:00',
              holdId: 'h1',
              tickets: [],
            },
            { status: 201 },
          ),
        )
      if (url.includes('/box-office/refunds')) return Promise.resolve(Response.json([]))
      return Promise.resolve(Response.json({}))
    }),
  )
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('GuichetPage', () => {
  it('sells a seat for cash and offers to print and start the next sale', async () => {
    const user = userEvent.setup()
    renderRoutes([{ path: '/guichet', element: <GuichetPage /> }], { path: '/guichet' })

    await user.click(await screen.findByRole('button', { name: /Le Misanthrope/ }))
    await user.click(await screen.findByRole('checkbox', { name: /place 1/ }))
    await user.click(screen.getByRole('button', { name: 'Bloquer ces places' }))

    expect(calls.find((c) => c.url.includes('/holds'))?.body).toMatchObject({
      channel: 'BOX_OFFICE',
      seatIds: ['s1'],
    })
    await user.click(await screen.findByRole('button', { name: /Encaisser 250/ }))

    expect(await screen.findByText('Vendu')).toBeInTheDocument()
    expect(screen.getByText('ARG-26-000777')).toBeInTheDocument()
    expect(calls.find((c) => c.url.includes('/sell'))?.body).toMatchObject({
      method: 'CASH',
      seats: [{ seatId: 's1', tariffId: 'full' }],
    })
    expect(screen.getByRole('button', { name: /Imprimer les billets/ })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Vente suivante/ }))
    expect(await screen.findByRole('button', { name: 'Bloquer ces places' })).toBeDisabled()
  })

  it('shows an empty refund queue', async () => {
    const user = userEvent.setup()
    renderRoutes([{ path: '/guichet', element: <GuichetPage /> }], { path: '/guichet' })

    await user.click(screen.getByRole('tab', { name: 'Remboursements' }))
    const panel = await screen.findByRole('tabpanel')
    expect(await within(panel).findByText('Rien à rendre pour l’instant.')).toBeInTheDocument()
  })
})
