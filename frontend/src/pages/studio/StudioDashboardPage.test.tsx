import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderRoutes } from '../../test/renderWithProviders'
import { StudioDashboardPage } from './StudioDashboardPage'

const kpi = (value: number, previous: number) => ({
  value,
  previous,
  delta: previous ? (value - previous) / previous : null,
})

const responses: Record<string, unknown> = {
  overview: {
    from: '2026-10-17T00:00:00+00:00',
    to: '2026-11-15T00:00:00+00:00',
    kpis: {
      revenueCents: kpi(20_754_000, 18_000_000),
      tickets: kpi(1154, 1218),
      orders: kpi(450, 470),
      averageBasketCents: kpi(46_120, 38_300),
      fillRate: kpi(0.82, 0.8),
      onlineShare: kpi(0.66, 0.6),
      refundedOrders: kpi(56, 3),
    },
    daily: [
      { date: '2026-11-14', tickets: 30, revenueCents: 600_000 },
      { date: '2026-11-15', tickets: 12, revenueCents: 240_000 },
    ],
  },
  revenue: {
    byTariff: [{ tariff: 'Plein', tickets: 664, revenueCents: 14_892_000 }],
    byChannel: [{ channel: 'ONLINE', tickets: 763, revenueCents: 14_476_000 }],
  },
  fill: [
    {
      performanceId: 'p1',
      eventTitle: 'Le Misanthrope',
      startsAt: '2099-11-20T19:00:00+00:00',
      status: 'ON_SALE',
      sold: 238,
      capacity: 398,
      zones: [{ code: 'O', name: 'Orchestre', sold: 200, capacity: 240 }],
    },
  ],
  'at-risk': [
    {
      performanceId: 'p2',
      eventTitle: 'Ahwach',
      startsAt: '2099-12-01T19:00:00+00:00',
      daysLeft: 16,
      rate: 0.22,
      expected: 0.51,
      sold: 87,
      capacity: 398,
    },
  ],
  'booking-curve': {
    reference: [{ daysBefore: 0, rate: 0.8 }],
    performance: [{ daysBefore: 0, rate: 0.6 }],
  },
  heatmap: {
    layoutId: 'l1',
    performances: 13,
    seats: [{ label: 'A-1', zone: 'O', x: 100, y: 100, rate: 0.9 }],
  },
}

const asked: string[] = []

beforeEach(() => {
  asked.length = 0
  vi.stubGlobal(
    'fetch',
    vi.fn<typeof fetch>((input) => {
      const url = input instanceof Request ? input.url : input.toString()
      asked.push(url)
      if (url.includes('/studio/layouts'))
        return Promise.resolve(
          Response.json([
            { id: 'l1', name: 'Configuration théâtre', isDefault: true, zones: [], seats: [] },
          ]),
        )
      const name = Object.keys(responses).find((k) => url.includes(`/analytics/${k}`))
      return Promise.resolve(Response.json(name ? responses[name] : {}))
    }),
  )
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('StudioDashboardPage', () => {
  it('shows KPIs with deltas, the watch list and the breakdowns', async () => {
    renderRoutes([{ path: '/', element: <StudioDashboardPage /> }])

    const revenue = await screen.findByRole('region', { name: 'Recettes' })
    expect(await within(revenue).findByText(/207\s540/)).toBeInTheDocument()
    expect(within(revenue).getByText(/\+15,3/)).toBeInTheDocument()

    expect(await screen.findByText(/Ahwach/)).toBeInTheDocument()
    expect(screen.getByText(/J-16 : 22 % vendu/)).toBeInTheDocument()
    expect(await screen.findByText('Plein')).toBeInTheDocument()
    expect(screen.getByText('238 / 398 places')).toBeInTheDocument()
  })

  it('reloads the figures for another period', async () => {
    const user = userEvent.setup()
    renderRoutes([{ path: '/', element: <StudioDashboardPage /> }])

    await screen.findByRole('region', { name: 'Recettes' })
    await user.click(screen.getByRole('button', { name: '7 jours' }))

    expect(screen.getByRole('button', { name: '7 jours' })).toHaveAttribute('aria-pressed', 'true')
    expect(asked.filter((u) => u.includes('/analytics/overview')).length).toBeGreaterThanOrEqual(2)
  })
})
