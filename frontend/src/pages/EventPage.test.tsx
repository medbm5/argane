import { screen, within } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { SIGNED_OUT } from '../app/auth/AuthContext'
import type { EventDetail } from '../lib/api/types'
import { renderRoutes } from '../test/renderWithProviders'
import { EventPage } from './EventPage'

const performance = (id: string, status: string, startsAt: string) => ({
  id,
  startsAt,
  doorsAt: startsAt,
  status,
  isOnSale: status === 'ON_SALE',
  remaining: 120,
  capacity: 398,
  fromPriceCents: status === 'ON_SALE' ? 11000 : null,
  layoutName: 'Configuration théâtre',
})

const misanthrope: EventDetail = {
  id: 'e1',
  slug: 'le-misanthrope',
  title: 'Le Misanthrope',
  subtitle: 'Molière',
  category: 'THEATRE',
  categoryLabel: 'Théâtre',
  description: 'Alceste refuse les politesses.\n\nCélimène s’en amuse.',
  posterUrl: null,
  durationMinutes: 140,
  ageRating: 10,
  fromPriceCents: 11000,
  currency: 'MAD',
  performances: [
    performance('p0', 'PAST', '2026-10-01T19:00:00+00:00'),
    performance('p1', 'ON_SALE', '2026-12-12T19:00:00+00:00'),
    performance('p2', 'SOLD_OUT', '2026-12-13T19:00:00+00:00'),
  ],
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('EventPage', () => {
  it('shows the event with its upcoming performances and prices', async () => {
    vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValue(Response.json(misanthrope)))
    renderRoutes([{ path: '/spectacles/:slug', element: <EventPage /> }], {
      auth: SIGNED_OUT,
      path: '/spectacles/le-misanthrope',
    })

    expect(await screen.findByRole('heading', { level: 1, name: 'Le Misanthrope' })).toBeVisible()
    expect(screen.getByText('Célimène s’en amuse.')).toBeInTheDocument()

    const dates = screen.getByRole('region', { name: 'Les dates' })
    const rows = within(dates).getAllByRole('link')
    expect(rows).toHaveLength(2)
    expect(rows[0]).toHaveAttribute('href', '/representations/p1')
    expect(within(rows[0] as HTMLElement).getByText(/dès 110/)).toBeInTheDocument()
    expect(within(rows[1] as HTMLElement).getByText('Complet')).toBeInTheDocument()
    expect(screen.getByText(/1 date passée/)).toBeInTheDocument()
  })

  it('renders the not found page for an unknown slug', async () => {
    vi.stubGlobal(
      'fetch',
      vi
        .fn<typeof fetch>()
        .mockResolvedValue(
          Response.json(
            { type: 'about:blank', title: 'Introuvable', status: 404, code: 'NOT_FOUND' },
            { status: 404, headers: { 'content-type': 'application/problem+json' } },
          ),
        ),
    )
    renderRoutes([{ path: '/spectacles/:slug', element: <EventPage /> }], {
      auth: SIGNED_OUT,
      path: '/spectacles/nope',
    })

    expect(await screen.findByRole('heading', { level: 1 })).not.toHaveTextContent('nope')
  })
})
