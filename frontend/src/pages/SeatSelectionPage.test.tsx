import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { SIGNED_OUT } from '../app/auth/AuthContext'
import type { Availability, PerformanceDetail, SeatMap } from '../lib/api/types'
import { renderRoutes } from '../test/renderWithProviders'
import { SeatSelectionPage } from './SeatSelectionPage'

const performance: PerformanceDetail = {
  id: 'p1',
  eventId: 'e1',
  eventSlug: 'le-misanthrope',
  eventTitle: 'Le Misanthrope',
  eventSubtitle: null,
  category: 'THEATRE',
  categoryLabel: 'Théâtre',
  posterUrl: null,
  durationMinutes: 140,
  ageRating: null,
  startsAt: '2026-12-12T19:00:00+00:00',
  doorsAt: '2026-12-12T18:30:00+00:00',
  endsAt: '2026-12-12T21:20:00+00:00',
  salesOpenAt: '2026-11-01T09:00:00+00:00',
  salesCloseAt: '2026-12-12T18:00:00+00:00',
  status: 'ON_SALE',
  isOnSale: true,
  cancelReason: null,
  remaining: 3,
  capacity: 3,
  fromPriceCents: 25000,
  currency: 'MAD',
  layoutId: 'l1',
  layoutName: 'Configuration test',
}

const map: SeatMap = {
  performanceId: 'p1',
  layoutId: 'l1',
  layoutName: 'Configuration test',
  channel: 'ONLINE',
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
      prices: [],
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
    {
      id: 's2',
      zoneId: 'z1',
      row: 'A',
      number: 2,
      label: 'A-2',
      x: 150,
      y: 120,
      accessible: false,
    },
  ],
}

const availability: Availability = {
  performanceId: 'p1',
  status: 'ON_SALE',
  isOnSale: true,
  remaining: 2,
  held: [],
  mine: [],
  sold: [],
  blocked: [],
  standing: [],
}

let holdResponse: () => Response

beforeEach(() => {
  sessionStorage.clear()
  holdResponse = () => Response.json({})
  vi.stubGlobal(
    'fetch',
    vi.fn<typeof fetch>((input, init) => {
      const url = input instanceof Request ? input.url : input.toString()
      if (url.includes('/holds') && init?.method === 'POST') return Promise.resolve(holdResponse())
      if (url.includes('/seatmap')) return Promise.resolve(Response.json(map))
      if (url.includes('/availability')) return Promise.resolve(Response.json(availability))
      if (url.includes('/performances/')) return Promise.resolve(Response.json(performance))
      return Promise.resolve(Response.json({ performances: [] }))
    }),
  )
})

afterEach(() => {
  vi.unstubAllGlobals()
})

const routes = [
  { path: '/representations/:id', element: <SeatSelectionPage /> },
  { path: '/connexion', element: <p>Connexion</p> },
]

describe('SeatSelectionPage', () => {
  it('keeps the selection and sends a signed-out buyer to sign in', async () => {
    const user = userEvent.setup()
    const { router } = renderRoutes(routes, { auth: SIGNED_OUT, path: '/representations/p1' })

    await user.click(await screen.findByRole('checkbox', { name: /place 1/ }))
    await user.click(screen.getByRole('button', { name: 'Réserver ces places' }))

    expect(router.state.location.pathname).toBe('/connexion')
    expect(router.state.location.search).toBe('?redirect=%2Frepresentations%2Fp1')
    expect(sessionStorage.getItem('argane.selection.p1')).toContain('s1')
  })

  it('holds the seats and starts the countdown', async () => {
    const user = userEvent.setup()
    holdResponse = () =>
      Response.json(
        {
          id: 'h1',
          performanceId: 'p1',
          status: 'ACTIVE',
          channel: 'ONLINE',
          expiresAt: new Date(Date.now() + 600_000).toISOString(),
          secondsLeft: 600,
          seatIds: ['s1'],
          standing: [],
        },
        { status: 201 },
      )
    renderRoutes(routes, { path: '/representations/p1' })

    await user.click(await screen.findByRole('checkbox', { name: /place 1/ }))
    await user.click(screen.getByRole('button', { name: 'Réserver ces places' }))

    expect(await screen.findByText('Places réservées pour vous')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Passer au paiement' })).toBeEnabled()
    expect(screen.getByRole('button', { name: 'Modifier ma sélection' })).toBeInTheDocument()
  })

  it('drops and flags a seat lost to another buyer', async () => {
    const user = userEvent.setup()
    holdResponse = () =>
      Response.json(
        {
          type: 'about:blank',
          title: 'Cette place vient d’être prise.',
          status: 409,
          code: 'SEATS_UNAVAILABLE',
          seatIds: ['s2'],
        },
        { status: 409 },
      )
    renderRoutes(routes, { path: '/representations/p1' })

    await user.click(await screen.findByRole('checkbox', { name: /place 1/ }))
    await user.click(screen.getByRole('checkbox', { name: /place 2/ }))
    await user.click(screen.getByRole('button', { name: 'Réserver ces places' }))

    expect(await screen.findByRole('checkbox', { name: /place 2/ })).toHaveAttribute(
      'aria-checked',
      'false',
    )
    expect(screen.getByRole('checkbox', { name: /place 1/ })).toHaveAttribute(
      'aria-checked',
      'true',
    )
    expect(screen.getByText('1 / 10')).toBeInTheDocument()
  })
})
