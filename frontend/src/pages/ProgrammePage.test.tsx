import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { SIGNED_OUT } from '../app/auth/AuthContext'
import type { EventListItem } from '../lib/api/types'
import { renderRoutes } from '../test/renderWithProviders'
import { ProgrammePage } from './ProgrammePage'

const tamra: EventListItem = {
  id: 'e2',
  slug: 'tamra',
  title: 'Tamra',
  subtitle: 'Nuits gnaoua',
  category: 'CONCERT',
  categoryLabel: 'Concert',
  posterUrl: null,
  durationMinutes: 100,
  ageRating: null,
  performanceCount: 2,
  fromPriceCents: 9000,
  currency: 'MAD',
  nextPerformance: null,
  months: [],
}

let fetchMock: ReturnType<typeof vi.fn<typeof fetch>>

beforeEach(() => {
  fetchMock = vi
    .fn<typeof fetch>()
    .mockImplementation(() => Promise.resolve(Response.json([tamra])))
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  vi.unstubAllGlobals()
})

const lastUrl = () => {
  const input = fetchMock.mock.calls.at(-1)?.[0]
  return input instanceof Request ? input.url : (input?.toString() ?? '')
}

describe('ProgrammePage', () => {
  it('reads filters from the URL and sends them to the API', async () => {
    renderRoutes([{ path: '/programme', element: <ProgrammePage /> }], {
      auth: SIGNED_OUT,
      path: '/programme?categorie=CONCERT&mois=2026-12',
    })

    expect(await screen.findByRole('link', { name: /Tamra/ })).toHaveAttribute(
      'href',
      '/spectacles/tamra',
    )
    expect(screen.getByRole('button', { name: /Concert/, pressed: true })).toBeInTheDocument()
    expect(lastUrl()).toContain('category=CONCERT')
    expect(lastUrl()).toContain('month=2026-12')
  })

  it('updates the URL when a category is chosen', async () => {
    const user = userEvent.setup()
    const { router } = renderRoutes([{ path: '/programme', element: <ProgrammePage /> }], {
      auth: SIGNED_OUT,
      path: '/programme',
    })

    await screen.findByText('1 spectacle')
    await user.click(screen.getByRole('button', { name: /Danse/ }))

    expect(router.state.location.search).toBe('?categorie=DANCE')
    expect(lastUrl()).toContain('category=DANCE')
  })
})
