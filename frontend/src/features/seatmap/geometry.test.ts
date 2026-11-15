import { describe, expect, it } from 'vitest'
import {
  clampView,
  fitView,
  MIN_ZOOM_WIDTH,
  neighbour,
  panBy,
  pathBounds,
  zoomAt,
} from './geometry'

const plan = { width: 1000, height: 700 }

describe('viewport', () => {
  it('fits the whole plan', () => {
    expect(fitView(plan)).toEqual({ x: 0, y: 0, w: 1000, h: 700 })
  })

  it('zooms around the pointer and keeps the plan aspect ratio', () => {
    const view = zoomAt(fitView(plan), plan, 2, 500, 350)
    expect(view).toEqual({ x: 250, y: 175, w: 500, h: 350 })
  })

  it('never zooms in past the minimum width nor out past the plan', () => {
    expect(zoomAt(fitView(plan), plan, 100, 500, 350).w).toBe(MIN_ZOOM_WIDTH)
    expect(zoomAt(fitView(plan), plan, 0.01, 500, 350).w).toBe(1080)
  })

  it('pans within the plan bounds', () => {
    const zoomed = zoomAt(fitView(plan), plan, 2, 500, 350)
    expect(panBy(zoomed, plan, 100, 0).x).toBe(150)
    expect(panBy(zoomed, plan, 9999, 0).x).toBe(-40)
    expect(clampView({ x: 9999, y: 9999, w: 500, h: 350 }, plan)).toEqual({
      x: 540,
      y: 390,
      w: 500,
      h: 350,
    })
  })
})

describe('neighbour', () => {
  // Two rows of three, the second row slightly curved.
  const seats = [
    { id: 'A1', x: 0, y: 0 },
    { id: 'A2', x: 30, y: 0 },
    { id: 'A3', x: 60, y: 0 },
    { id: 'B1', x: 0, y: 32 },
    { id: 'B2', x: 30, y: 30 },
    { id: 'B3', x: 60, y: 32 },
  ]
  const at = (id: string) => {
    const seat = seats.find((s) => s.id === id)
    if (!seat) throw new Error(id)
    return seat
  }

  it('moves along the row and between rows', () => {
    expect(neighbour(seats, at('A1'), 'right')?.id).toBe('A2')
    expect(neighbour(seats, at('A2'), 'left')?.id).toBe('A1')
    expect(neighbour(seats, at('A2'), 'down')?.id).toBe('B2')
    expect(neighbour(seats, at('B3'), 'up')?.id).toBe('A3')
  })

  it('returns null at the edge', () => {
    expect(neighbour(seats, at('A3'), 'right')).toBeNull()
    expect(neighbour(seats, at('A1'), 'up')).toBeNull()
  })
})

describe('pathBounds', () => {
  it('measures the paths emitted by the layout generator', () => {
    expect(pathBounds('M150 470 Q500 430 850 470 V670 H150 Z')).toEqual({
      x: 150,
      y: 430,
      w: 700,
      h: 240,
    })
  })
})
