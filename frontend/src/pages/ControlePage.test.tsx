import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderRoutes } from '../test/renderWithProviders'
import { ControlePage } from './ControlePage'

// No camera in jsdom: a scanner that starts, pauses and never decodes.
vi.mock('qr-scanner', () => ({
  default: class {
    start() {
      return Promise.resolve()
    }
    pause() {
      return Promise.resolve(true)
    }
    destroy() {
      return undefined
    }
  },
}))

const performance = {
  id: 'p1',
  eventTitle: 'Le Misanthrope',
  eventSlug: 'x',
  category: 'Théâtre',
  posterUrl: null,
  startsAt: '2026-12-12T19:00:00+00:00',
  status: 'ON_SALE',
  sellable: true,
  onlineOpen: true,
  remaining: 10,
  capacity: 400,
  layoutName: 'Théâtre',
}

const verdicts = [
  {
    verdict: 'ADMITTED',
    message: 'Entrée validée : vérifiez le justificatif de tarif réduit.',
    zone: 'Orchestre',
    seat: 'A-1',
    tariff: 'Réduit',
    requiresProof: true,
    reference: 'ARG-26-000001',
    admitted: 1,
    expected: 2,
  },
  {
    verdict: 'ALREADY_USED',
    message: 'Déjà scanné à 18:45.',
    zone: 'Orchestre',
    seat: 'A-1',
    tariff: 'Réduit',
    requiresProof: true,
    checkedInAt: '2026-12-12T18:45:00+00:00',
    reference: 'ARG-26-000001',
    admitted: 1,
    expected: 2,
  },
]

beforeEach(() => {
  let scan = 0
  vi.stubGlobal(
    'fetch',
    vi.fn<typeof fetch>((input) => {
      const url = input instanceof Request ? input.url : input.toString()
      if (url.includes('/box-office/performances'))
        return Promise.resolve(Response.json([performance]))
      if (url.includes('/counts'))
        return Promise.resolve(Response.json({ admitted: 0, expected: 2 }))
      if (url.includes('/scan')) return Promise.resolve(Response.json(verdicts[scan++]))
      return Promise.resolve(Response.json({}))
    }),
  )
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('ControlePage', () => {
  it('admits with a proof reminder, then refuses the second scan', async () => {
    const user = userEvent.setup()
    renderRoutes([{ path: '/controle', element: <ControlePage /> }], { path: '/controle' })

    expect(await screen.findByText('0 / 2')).toBeInTheDocument()
    const field = screen.getByLabelText('Saisie manuelle du code')

    await user.type(field, 'ARG1.abc.def')
    await user.click(screen.getByRole('button', { name: 'Valider' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Justificatif !')
    expect(screen.getByRole('alert')).toHaveTextContent('A-1')
    expect(screen.getByText('1 / 2')).toBeInTheDocument()

    await user.type(field, 'ARG1.abc.def')
    await user.click(screen.getByRole('button', { name: 'Valider' }))
    expect(await screen.findByText('Déjà utilisé')).toBeInTheDocument()
  })
})
