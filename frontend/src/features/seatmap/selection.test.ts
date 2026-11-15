import { describe, expect, it } from 'vitest'
import { EMPTY_SELECTION, seatStates, selectedCount, selectionReducer } from './selection'

const reduce = selectionReducer(3)

describe('selectionReducer', () => {
  it('toggles seats on and off', () => {
    const one = reduce(EMPTY_SELECTION, { type: 'toggleSeat', seatId: 'a' })
    expect(one.seatIds).toEqual(['a'])
    expect(reduce(one, { type: 'toggleSeat', seatId: 'a' }).seatIds).toEqual([])
  })

  it('refuses a seat beyond the limit by returning the same state', () => {
    let state = EMPTY_SELECTION
    for (const id of ['a', 'b', 'c']) state = reduce(state, { type: 'toggleSeat', seatId: id })
    expect(reduce(state, { type: 'toggleSeat', seatId: 'd' })).toBe(state)
  })

  it('caps standing quantities with the seats already chosen', () => {
    const one = reduce(EMPTY_SELECTION, { type: 'toggleSeat', seatId: 'a' })
    const standing = reduce(one, { type: 'setStanding', zoneId: 'fosse', quantity: 5 })
    expect(standing.standing).toEqual({ fosse: 2 })
    expect(selectedCount(standing)).toBe(3)
    expect(
      reduce(standing, { type: 'setStanding', zoneId: 'fosse', quantity: 0 }).standing,
    ).toEqual({})
  })

  it('drops seats lost to another buyer', () => {
    const state = { seatIds: ['a', 'b'], standing: {} }
    expect(reduce(state, { type: 'dropSeats', seatIds: ['b', 'z'] }).seatIds).toEqual(['a'])
    expect(reduce(state, { type: 'dropSeats', seatIds: ['z'] })).toBe(state)
  })
})

describe('seatStates', () => {
  it('merges server states with local picks', () => {
    const states = seatStates(
      {
        performanceId: 'p',
        status: 'ON_SALE',
        isOnSale: true,
        remaining: 9,
        held: ['h'],
        mine: ['m'],
        sold: ['s'],
        blocked: ['b'],
        standing: [],
      },
      { seatIds: ['x'], standing: {} },
    )
    expect(Object.fromEntries(states)).toEqual({
      b: 'blocked',
      s: 'sold',
      h: 'held',
      m: 'selected',
      x: 'selected',
    })
  })
})
