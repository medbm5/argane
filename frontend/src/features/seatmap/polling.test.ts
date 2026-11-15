import { describe, expect, it } from 'vitest'
import { availabilityInterval } from './api'

describe('availabilityInterval', () => {
  it('polls every 5 s and backs off exponentially on errors, capped at a minute', () => {
    expect([0, 1, 2, 3, 4, 10].map(availabilityInterval)).toEqual([
      5000, 10_000, 20_000, 40_000, 60_000, 60_000,
    ])
  })
})
