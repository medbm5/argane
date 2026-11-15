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

  it('shows instants in Casablanca time', () => {
    // 19:00 UTC is 20:00 in Agadir (UTC+1).
    expect(formatTime('2026-12-12T19:00:00+00:00')).toBe('20:00')
    expect(formatLongDate('2026-12-12T19:00:00+00:00')).toBe('Samedi 12 décembre 2026')
    expect(dateTile('2026-12-12T19:00:00+00:00')).toEqual({ day: '12', month: 'déc' })
  })

  it('computes the venue month across midnight UTC', () => {
    expect(venueMonth('2026-12-31T23:30:00+00:00')).toBe('2027-01')
  })

  it('formats durations and age ratings', () => {
    expect(formatDuration(140)).toBe('2 h 20')
    expect(formatDuration(120)).toBe('2 h')
    expect(formatDuration(45)).toBe('45 min')
    expect(formatAgeRating(null)).toBe('Tout public')
    expect(formatAgeRating(10)).toBe('Dès 10 ans')
  })
})
