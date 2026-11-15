import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { EventListItem } from '../lib/api/types'
import { renderRoutes } from '../test/renderWithProviders'
import { SIGNED_OUT } from '../app/auth/AuthContext'
import { HomePage } from './HomePage'

const misanthrope: EventListItem = {
  id: 'e1',
  slug: 'le-misanthrope',
  title: 'Le Misanthrope',
  subtitle: 'Molière',
  category: 'THEATRE',
  categoryLabel: 'Théâtre',
  posterUrl: '/posters/le-misanthrope.svg',
  durationMinutes: 140,
  ageRating: 10,
  performanceCount: 4,
  fromPriceCents: 11000,
  currency: 'MAD',
  nextPerformance: {
    id: 'p1',
    startsAt: '2026-12-12T19:00:00+00:00',
    doorsAt: '2026-12-12T18:30:00+00:00',
    status: 'ON_SALE',
    isOnSale: true,
    remaining: 20,
    capacity: 398,
    fromPriceCents: 11000,
    layoutName: 'Configuration théâtre',
  },
}

beforeEach(() => {
  vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValue(Response.json([misanthrope])))
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('HomePage', () => {
  it('lists upcoming performances with their price and status', async () => {
    renderRoutes([{ path: '/', element: <HomePage /> }], { auth: SIGNED_OUT })

    const upcoming = await screen.findByRole('region', { name: 'Prochainement' })
    const row = await within(upcoming).findByRole('link', { name: /Le Misanthrope/ })
    expect(row).toHaveAttribute('href', '/spectacles/le-misanthrope')
    expect(within(row).getByText('En vente')).toBeInTheDocument()
    expect(within(row).getByText(/dès 110/)).toBeInTheDocument()
    expect(within(row).getByText('Dernières places')).toBeInTheDocument()
  })

  it('searches the programme by month and category', async () => {
    const user = userEvent.setup()
    const { router } = renderRoutes(
      [
        { path: '/', element: <HomePage /> },
        { path: '/programme', element: <p>Programme</p> },
      ],
      { auth: SIGNED_OUT },
    )

    await screen.findByRole('link', { name: 'À l’affiche : Le Misanthrope' })
    await user.click(screen.getByRole('combobox', { name: /Mois/ }))
    await user.click(await screen.findByRole('option', { name: 'décembre 2026' }))
    await user.click(screen.getByRole('combobox', { name: /Catégorie/ }))
    await user.click(await screen.findByRole('option', { name: 'Théâtre' }))
    expect(screen.getByRole('combobox', { name: /Catégorie/ })).toHaveTextContent('Théâtre')
    await user.click(screen.getByRole('button', { name: 'Voir la programmation' }))

    expect(router.state.location.pathname).toBe('/programme')
    expect(router.state.location.search).toBe('?mois=2026-12&categorie=THEATRE')
  })

  it('features a poster and names the invented partners', async () => {
    renderRoutes([{ path: '/', element: <HomePage /> }], { auth: SIGNED_OUT })

    expect(await screen.findByRole('link', { name: 'À l’affiche : Le Misanthrope' })).toBeVisible()
    expect(screen.getByText('Souss Radio')).toBeInTheDocument()
  })
})
