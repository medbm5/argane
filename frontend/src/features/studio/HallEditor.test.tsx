import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderRoutes } from '../../test/renderWithProviders'
import type { Layout } from './api'
import { HallEditor } from './HallEditor'

const seat = (id: string, row: string, number: number, blocked = false) => ({
  id,
  zone: 'PARTERRE',
  row,
  number,
  label: `${row}-${String(number)}`,
  x: 100 + number * 30,
  y: row === 'A' ? 300 : 340,
  accessible: false,
  blocked,
})

const plan: Layout = {
  id: 'l1',
  name: 'Théâtre',
  isDefault: true,
  locked: true,
  archived: false,
  clonedFromName: null,
  capacity: 3,
  createdAt: null,
  viewBox: { width: 1000, height: 800 },
  stage: { x: 300, y: 40, width: 400, height: 80 },
  zones: [
    {
      code: 'PARTERRE',
      name: 'Parterre',
      kind: 'SEATED',
      colorToken: 'teal',
      shape: 'M0 0',
      capacity: 3,
      seats: 4,
    },
  ],
  seats: [seat('s1', 'A', 1), seat('s2', 'A', 2), seat('s3', 'B', 1), seat('s4', 'B', 2, true)],
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('HallEditor', () => {
  it('counts active seats, selects a whole row by its letter and deactivates it', async () => {
    const after = {
      ...plan,
      seats: plan.seats.map((s) => (s.row === 'A' ? { ...s, blocked: true } : s)),
    }
    const fetchSpy = vi
      .fn<typeof fetch>()
      .mockImplementation((_url, init) =>
        Promise.resolve(
          init?.method === 'PUT'
            ? Response.json({ layout: after, seatsUpdated: 24, seatsKept: 1 })
            : Response.json(plan),
        ),
      )
    vi.stubGlobal('fetch', fetchSpy)
    const user = userEvent.setup()
    renderRoutes([{ path: '/', element: <HallEditor layoutId="l1" name="Théâtre" /> }])

    const active = await screen.findByText('Places assises actives')
    expect(active.nextElementSibling).toHaveTextContent('3')

    await user.click(screen.getByRole('button', { name: /Rangée A : sélectionner/ }))
    expect(screen.getByRole('checkbox', { name: 'Place A-1' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Place A-2' })).toBeChecked()
    const toolbar = screen.getByRole('toolbar', { name: 'Places sélectionnées' })
    expect(within(toolbar).getByText('2 places sélectionnées')).toBeInTheDocument()
    expect(within(toolbar).getByRole('button', { name: 'Réactiver' })).toBeDisabled()

    await user.click(within(toolbar).getByRole('button', { name: 'Désactiver' }))

    const put = fetchSpy.mock.calls.find(([, init]) => init?.method === 'PUT')
    expect(put?.[0]).toMatch(/\/studio\/layouts\/l1\/seats$/)
    expect(JSON.parse(put?.[1]?.body as string)).toEqual({ seatIds: ['s1', 's2'], blocked: true })
    expect(await screen.findByRole('checkbox', { name: 'Place A-1, désactivée' })).not.toBeChecked()
    expect(screen.getByText('Désactivées').nextElementSibling).toHaveTextContent('3')
    expect(screen.getByText('Touchez des places ou une lettre de rangée')).toBeInTheDocument()
  })

  it('reactivates only the seats that are off', async () => {
    const fetchSpy = vi
      .fn<typeof fetch>()
      .mockImplementation((_url, init) =>
        Promise.resolve(
          init?.method === 'PUT'
            ? Response.json({ layout: plan, seatsUpdated: 1, seatsKept: 0 })
            : Response.json(plan),
        ),
      )
    vi.stubGlobal('fetch', fetchSpy)
    const user = userEvent.setup()
    renderRoutes([{ path: '/', element: <HallEditor layoutId="l1" name="Théâtre" /> }])

    await user.click(await screen.findByRole('button', { name: /Rangée B : sélectionner/ }))
    await user.click(screen.getByRole('button', { name: 'Réactiver' }))

    const put = fetchSpy.mock.calls.find(([, init]) => init?.method === 'PUT')
    expect(JSON.parse(put?.[1]?.body as string)).toEqual({ seatIds: ['s4'], blocked: false })
  })
})
