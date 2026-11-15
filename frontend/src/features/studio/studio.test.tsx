import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { StudioEventsPage } from '../../pages/studio/StudioEventsPage'
import { renderRoutes } from '../../test/renderWithProviders'
import { parseAmount } from './prices'

describe('parseAmount', () => {
  it('reads French and dotted amounts into minor units', () => {
    expect(parseAmount('125,50')).toBe(12550)
    expect(parseAmount(' 1 250 ')).toBe(125000)
    expect(parseAmount('90.5')).toBe(9050)
    expect(parseAmount('')).toBeNull()
    expect(parseAmount('12,345')).toBe('invalid')
    expect(parseAmount('abc')).toBe('invalid')
  })
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('StudioEventsPage', () => {
  it('lists events with fill and revenue', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockResolvedValue(
        Response.json([
          {
            id: 'e1',
            slug: 'tamra',
            title: 'Tamra',
            subtitle: null,
            category: 'CONCERT',
            categoryLabel: 'Concert',
            description: '',
            durationMinutes: 100,
            ageRating: null,
            posterPath: null,
            status: 'PUBLISHED',
            sold: 406,
            capacity: 458,
            revenueCents: 6_090_000,
            performances: [],
          },
        ]),
      ),
    )
    renderRoutes([{ path: '/', element: <StudioEventsPage /> }])

    expect(await screen.findByRole('link', { name: 'Tamra' })).toHaveAttribute(
      'href',
      '/studio/spectacles/e1',
    )
    expect(screen.getByText(/406 \/ 458/)).toBeInTheDocument()
    expect(screen.getByText('(89 %)')).toBeInTheDocument()
    expect(screen.getByText(/60\s900/)).toBeInTheDocument()
  })
})
