import { screen } from '@testing-library/react'
import axe from 'axe-core'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { SIGNED_OUT } from '../app/auth/AuthContext'
import { App } from '../App'
import { render } from '@testing-library/react'
import type { EventListItem } from '../lib/api/types'

/**
 * Automated accessibility checks (axe-core) on whole screens. Colour contrast is
 * excluded here because jsdom cannot compute layout or colours; the palette's
 * contrast ratios are checked by hand (ADR-005) and the text-role red was darkened.
 */
async function violations(container: Element) {
  const result = await axe.run(container, {
    rules: { 'color-contrast': { enabled: false }, region: { enabled: false } },
  })
  return result.violations.map((v) => `${v.id}: ${v.help} (${String(v.nodes.length)})`)
}

const event: EventListItem = {
  id: 'e1',
  slug: 'le-misanthrope',
  title: 'Le Misanthrope',
  subtitle: 'Molière',
  category: 'THEATRE',
  categoryLabel: 'Théâtre',
  posterUrl: '/posters/le-misanthrope.svg',
  durationMinutes: 140,
  ageRating: 10,
  performanceCount: 1,
  fromPriceCents: 11000,
  currency: 'MAD',
  nextPerformance: {
    id: 'p1',
    startsAt: '2099-12-12T19:00:00+00:00',
    doorsAt: '2099-12-12T18:30:00+00:00',
    status: 'ON_SALE',
    isOnSale: true,
    remaining: 20,
    capacity: 398,
    fromPriceCents: 11000,
    layoutName: 'Configuration théâtre',
  },
}

afterEach(() => {
  vi.unstubAllGlobals()
  window.history.replaceState(null, '', '/')
})

describe('accessibility', () => {
  it('the landing page has no detectable violations', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>(() => Promise.resolve(Response.json([event]))),
    )
    const { container } = render(<App />)
    await screen.findByRole('link', { name: 'À l’affiche : Le Misanthrope' })

    expect(await violations(container)).toEqual([])
    expect(SIGNED_OUT.isSignedIn).toBe(false)
  })

  it('the programme page has no detectable violations', async () => {
    window.history.replaceState(null, '', '/programme')
    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>(() => Promise.resolve(Response.json([event]))),
    )
    const { container } = render(<App />)
    await screen.findByRole('heading', { name: 'Programmation' })
    await screen.findByText('1 spectacle')

    expect(await violations(container)).toEqual([])
  })
})
