import { describe, expect, it } from 'vitest'
import {
  dateTile,
  formatAgeRating,
  formatDuration,
  formatLongDate,
  formatMoney,
  formatTime,
  venueMonth,
} from './format'

const nbsp = /[\u00a0\u202f]/g

describe('format', () => {
  it('formats MAD without useless decimals', () => {
    expect(formatMoney(45000).replace(nbsp, ' ')).toBe('450 MAD')
    expect(formatMoney(12050).replace(nbsp, ' ')).toBe('120,50 MAD')
    expect(formatMoney(125000).replace(nbsp, ' ')).toBe('1 250 MAD')
  })

  // Morocco moved from UTC+1 to UTC+0 on 2026-09-20 (tzdata 2026e). These dates sit
  // before the switch, where every tzdata version agrees on UTC+1, so the tests do not
  // depend on how fresh the machine's time zone database is (ADR-013).
  it('shows instants in Casablanca time', () => {
    // 19:00 UTC is 20:00 in Agadir (UTC+1 in June 2026).
    expect(formatTime('2026-06-12T19:00:00+00:00')).toBe('20:00')
    expect(formatLongDate('2026-06-12T19:00:00+00:00')).toBe('Vendredi 12 juin 2026')
    expect(dateTile('2026-06-12T19:00:00+00:00')).toEqual({ day: '12', month: 'juin' })
  })

  it('computes the venue month across midnight UTC', () => {
    expect(venueMonth('2026-06-30T23:30:00+00:00')).toBe('2026-07')
  })

  it('formats durations and age ratings', () => {
    expect(formatDuration(140)).toBe('2 h 20')
    expect(formatDuration(120)).toBe('2 h')
    expect(formatDuration(45)).toBe('45 min')
    expect(formatAgeRating(null)).toBe('Tout public')
    expect(formatAgeRating(10)).toBe('Dès 10 ans')
  })
})
