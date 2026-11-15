/** Plan-space helpers: the visible window (viewBox) and arrow-key seat neighbours. */

export interface View {
  x: number
  y: number
  w: number
  h: number
}

export interface Plan {
  width: number
  height: number
}

export const MIN_ZOOM_WIDTH = 220
const PAD = 40

export function fitView(plan: Plan): View {
  return { x: 0, y: 0, w: plan.width, h: plan.height }
}

/** Keep the window inside the plan (with a little air) and within zoom bounds. */
export function clampView(view: View, plan: Plan): View {
  const ratio = plan.height / plan.width
  const w = Math.min(plan.width + 2 * PAD, Math.max(MIN_ZOOM_WIDTH, view.w))
  const h = w * ratio
  const x = Math.min(plan.width + PAD - w, Math.max(-PAD, view.x))
  const y = Math.min(plan.height + PAD - h, Math.max(-PAD, view.y))
  return { x, y, w, h }
}

/** Zoom by `factor` (>1 zooms in) keeping the plan point (px, py) under the pointer. */
export function zoomAt(view: View, plan: Plan, factor: number, px: number, py: number): View {
  const w = view.w / factor
  const scale = w / view.w
  return clampView(
    { x: px - (px - view.x) * scale, y: py - (py - view.y) * scale, w, h: view.h * scale },
    plan,
  )
}

export function zoomCenter(view: View, plan: Plan, factor: number): View {
  return zoomAt(view, plan, factor, view.x + view.w / 2, view.y + view.h / 2)
}

/** Pan by a delta expressed in plan units. */
export function panBy(view: View, plan: Plan, dx: number, dy: number): View {
  return clampView({ ...view, x: view.x - dx, y: view.y - dy }, plan)
}

export type Direction = 'left' | 'right' | 'up' | 'down'

interface Point {
  id: string
  x: number
  y: number
}

/**
 * Nearest seat in a direction. Distance along the axis counts once, sideways drift
 * counts three times, so "right" stays on the row even when rows curve.
 */
export function neighbour<T extends Point>(seats: T[], from: T, direction: Direction): T | null {
  let best: T | null = null
  let bestScore = Number.POSITIVE_INFINITY
  for (const seat of seats) {
    if (seat.id === from.id) continue
    const dx = seat.x - from.x
    const dy = seat.y - from.y
    const along = { left: -dx, right: dx, up: -dy, down: dy }[direction]
    if (along <= 1) continue
    const across = direction === 'left' || direction === 'right' ? Math.abs(dy) : Math.abs(dx)
    const score = along + 3 * across
    if (score < bestScore) {
      bestScore = score
      best = seat
    }
  }
  return best
}

/**
 * Bounding box of an absolute SVG path made of M/L/H/V/Q/C/Z (what the layout generator
 * emits). Control points are included, which is close enough for placing a label.
 */
export function pathBounds(d: string): { x: number; y: number; w: number; h: number } {
  const tokens = d.match(/[MLHVQCZ]|-?\d*\.?\d+(?:e-?\d+)?/gi) ?? []
  let cx = 0
  let cy = 0
  let command = 'M'
  const xs: number[] = []
  const ys: number[] = []
  let i = 0
  const num = () => Number(tokens[i++])
  while (i < tokens.length) {
    const token = tokens[i] ?? ''
    if (/[A-Z]/i.test(token)) {
      command = token.toUpperCase()
      i++
      if (command === 'Z') continue
    }
    switch (command) {
      case 'H':
        cx = num()
        break
      case 'V':
        cy = num()
        break
      case 'Q':
        xs.push(num())
        ys.push(num())
        cx = num()
        cy = num()
        break
      case 'C':
        for (let k = 0; k < 2; k++) {
          xs.push(num())
          ys.push(num())
        }
        cx = num()
        cy = num()
        break
      default:
        cx = num()
        cy = num()
    }
    xs.push(cx)
    ys.push(cy)
  }
  const minX = Math.min(...xs)
  const minY = Math.min(...ys)
  return { x: minX, y: minY, w: Math.max(...xs) - minX, h: Math.max(...ys) - minY }
}
