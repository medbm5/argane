import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useReducer } from 'react'
import { describe, expect, it } from 'vitest'
import type { Availability, SeatMap as SeatMapData } from '../../lib/api/types'
import { SeatMap } from './SeatMap'
import { EMPTY_SELECTION, selectionReducer } from './selection'

const map: SeatMapData = {
  performanceId: 'p1',
  layoutId: 'l1',
  layoutName: 'Configuration test',
  channel: 'ONLINE',
  currency: 'MAD',
  viewBox: { width: 1000, height: 700 },
  stage: { x: 250, y: 18, width: 500, height: 56 },
  maxSeatsPerOrder: 2,
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
          tariffId: 't1',
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
    {
      id: 'z2',
      code: 'FOSSE',
      name: 'Fosse',
      kind: 'STANDING',
      colorToken: 'olive-600',
      sortOrder: 1,
      shape: 'M500 100 H800 V300 H500 Z',
      capacity: 50,
      prices: [],
    },
  ],
  seats: [
    { id: 's1', zoneId: 'z1', row: 'A', number: 1, label: 'A-1', x: 120, y: 120, accessible: true },
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
    {
      id: 's3',
      zoneId: 'z1',
      row: 'A',
      number: 3,
      label: 'A-3',
      x: 180,
      y: 120,
      accessible: false,
    },
  ],
}

const availability: Availability = {
  performanceId: 'p1',
  status: 'ON_SALE',
  isOnSale: true,
  remaining: 52,
  held: [],
  mine: [],
  sold: ['s2'],
  blocked: [],
  standing: [{ zoneId: 'z2', remaining: 50, capacity: 50 }],
}

function Harness({ onStanding = () => undefined }: { onStanding?: (id: string) => void }) {
  const [selection, dispatch] = useReducer(selectionReducer(2), EMPTY_SELECTION)
  return (
    <SeatMap
      map={map}
      availability={availability}
      selection={selection}
      onToggleSeat={(seatId) => {
        dispatch({ type: 'toggleSeat', seatId })
      }}
      onStandingZone={onStanding}
    />
  )
}

describe('SeatMap', () => {
  it('labels every seat for screen readers with zone, row, number, state and price', () => {
    render(<Harness />)
    expect(
      screen.getByRole('checkbox', {
        name: /^Orchestre, rang A, place 1, accès fauteuil roulant, disponible, 250\sMAD$/,
      }),
    ).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: /place 2, vendue/ })).toHaveAttribute(
      'aria-disabled',
      'true',
    )
  })

  it('moves between seats with the arrows and picks with Space', async () => {
    const user = userEvent.setup()
    render(<Harness />)

    const first = screen.getByRole('checkbox', { name: /place 1/ })
    first.focus()
    await user.keyboard(' ')
    expect(first).toHaveAttribute('aria-checked', 'true')

    await user.keyboard('{ArrowRight}')
    const sold = screen.getByRole('checkbox', { name: /place 2/ })
    expect(sold).toHaveFocus()
    await user.keyboard(' ')
    expect(sold).toHaveAttribute('aria-checked', 'false')

    await user.keyboard('{ArrowRight} ')
    expect(screen.getByRole('checkbox', { name: /place 3/ })).toHaveAttribute(
      'aria-checked',
      'true',
    )
  })

  it('opens the standing picker from the zone', async () => {
    const user = userEvent.setup()
    const picked: string[] = []
    render(<Harness onStanding={(id) => picked.push(id)} />)

    await user.click(
      screen.getByRole('button', { name: /Fosse, places debout, 50 places restantes/ }),
    )
    expect(picked).toEqual(['z2'])
  })

  it('has zoom controls', () => {
    render(<Harness />)
    expect(screen.getByRole('button', { name: 'Zoomer' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Voir toute la salle' })).toBeInTheDocument()
  })
})
