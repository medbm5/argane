import type { Availability } from '../../lib/api/types'

/** What the buyer picked on the plan: numbered seats plus standing quantities per zone. */
export interface Selection {
  seatIds: string[]
  standing: Record<string, number>
}

export const EMPTY_SELECTION: Selection = { seatIds: [], standing: {} }

export type SelectionAction =
  | { type: 'toggleSeat'; seatId: string }
  | { type: 'setStanding'; zoneId: string; quantity: number }
  | { type: 'dropSeats'; seatIds: string[] }
  | { type: 'clear' }

export function selectedCount(selection: Selection): number {
  return selection.seatIds.length + Object.values(selection.standing).reduce((sum, n) => sum + n, 0)
}

/**
 * Pure reducer with the per-order limit. Exceeding the limit leaves the state untouched
 * (same reference), which callers use to tell the buyer why nothing happened.
 */
export function selectionReducer(max: number) {
  return (state: Selection, action: SelectionAction): Selection => {
    switch (action.type) {
      case 'toggleSeat': {
        if (state.seatIds.includes(action.seatId)) {
          return { ...state, seatIds: state.seatIds.filter((id) => id !== action.seatId) }
        }
        if (selectedCount(state) >= max) return state
        return { ...state, seatIds: [...state.seatIds, action.seatId] }
      }
      case 'setStanding': {
        const others = selectedCount(state) - (state.standing[action.zoneId] ?? 0)
        const quantity = Math.max(0, Math.min(action.quantity, max - others))
        const standing = { ...state.standing, [action.zoneId]: quantity }
        if (quantity === 0) {
          // eslint-disable-next-line @typescript-eslint/no-dynamic-delete
          delete standing[action.zoneId]
        }
        return { ...state, standing }
      }
      case 'dropSeats': {
        const lost = new Set(action.seatIds)
        const seatIds = state.seatIds.filter((id) => !lost.has(id))
        return seatIds.length === state.seatIds.length ? state : { ...state, seatIds }
      }
      case 'clear':
        return EMPTY_SELECTION
    }
  }
}

export type SeatState = 'available' | 'selected' | 'held' | 'sold' | 'blocked'

/** Effective state per seat id; absent means available. Local picks win over the server. */
export function seatStates(
  availability: Availability | undefined,
  selection: Selection,
): Map<string, SeatState> {
  const states = new Map<string, SeatState>()
  if (availability) {
    for (const id of availability.blocked) states.set(id, 'blocked')
    for (const id of availability.sold) states.set(id, 'sold')
    for (const id of availability.held) states.set(id, 'held')
    for (const id of availability.mine) states.set(id, 'selected')
  }
  for (const id of selection.seatIds) states.set(id, 'selected')
  return states
}
