import { describe, expect, it } from 'vitest'
import type { HoldInfo, SeatMap, ZonePrice } from '../../lib/api/types'
import { choiceTotal, initialChoice, needsProof, standingBalanced, toLines } from './tariffs'

const price = (
  tariffId: string,
  code: string,
  amountCents: number,
  extra: Partial<ZonePrice> = {},
) => ({
  tariffId,
  tariffCode: code,
  label: code,
  description: null,
  amountCents,
  minQuantity: 1,
  maxQuantity: null,
  requiresProofAtEntry: false,
  requiresReason: false,
  ...extra,
})

const map: SeatMap = {
  performanceId: 'p',
  layoutId: 'l',
  layoutName: 'L',
  channel: 'ONLINE',
  currency: 'MAD',
  viewBox: { width: 1000, height: 700 },
  stage: { x: 0, y: 0, width: 1, height: 1 },
  maxSeatsPerOrder: 10,
  zones: [
    {
      id: 'orch',
      code: 'O',
      name: 'Orchestre',
      kind: 'SEATED',
      colorToken: 'x',
      sortOrder: 0,
      shape: 'M0 0',
      capacity: null,
      prices: [
        price('red', 'REDUCED', 15000, { requiresProofAtEntry: true }),
        price('full', 'FULL', 25000),
      ],
    },
    {
      id: 'fosse',
      code: 'F',
      name: 'Fosse',
      kind: 'STANDING',
      colorToken: 'x',
      sortOrder: 1,
      shape: 'M0 0',
      capacity: 300,
      prices: [
        price('full', 'FULL', 9000),
        price('red', 'REDUCED', 6000, { requiresProofAtEntry: true }),
      ],
    },
  ],
  seats: [
    { id: 's1', zoneId: 'orch', row: 'A', number: 1, label: 'A-1', x: 0, y: 0, accessible: false },
  ],
}

const hold: HoldInfo = {
  id: 'h',
  performanceId: 'p',
  status: 'ACTIVE',
  channel: 'ONLINE',
  expiresAt: '2026-11-15T10:10:00Z',
  secondsLeft: 600,
  seatIds: ['s1'],
  standing: [{ zoneId: 'fosse', quantity: 3 }],
}

describe('tariff choice', () => {
  it('starts everyone at full price', () => {
    const choice = initialChoice(hold, map)
    expect(choice).toEqual({ seats: { s1: 'full' }, standing: { fosse: { full: 3 } } })
    expect(choiceTotal(choice, map)).toBe(25000 + 3 * 9000)
    expect(needsProof(choice, map)).toBe(false)
  })

  it('prices a mixed split and turns it into request lines', () => {
    const choice = { seats: { s1: 'red' }, standing: { fosse: { full: 1, red: 2, other: 0 } } }
    expect(choiceTotal(choice, map)).toBe(15000 + 9000 + 2 * 6000)
    expect(standingBalanced(choice, hold)).toBe(true)
    expect(needsProof(choice, map)).toBe(true)
    expect(toLines(choice)).toEqual({
      seats: [{ seatId: 's1', tariffId: 'red' }],
      standing: [
        { zoneId: 'fosse', tariffId: 'full', quantity: 1 },
        { zoneId: 'fosse', tariffId: 'red', quantity: 2 },
      ],
    })
  })

  it('detects an unbalanced standing split', () => {
    expect(standingBalanced({ seats: {}, standing: { fosse: { full: 2 } } }, hold)).toBe(false)
  })
})
