/**
 * Friendly names for the generated OpenAPI schemas (see schema.d.ts).
 */
import type { components } from './schema'

export type Schemas = components['schemas']

export type EventListItem = Schemas['EventListItemView']
export type EventDetail = Schemas['EventDetailView']
export type PerformanceSummary = Schemas['PerformanceSummaryView']
export type PerformanceDetail = Schemas['PerformanceDetailView']
export type VenueInfo = Schemas['VenueResponse']

export type EventCategory = 'CONCERT' | 'THEATRE' | 'CONFERENCE' | 'DANCE' | 'COMEDY'
/** PHP array shapes come out optional in OpenAPI; the API always sends them. */
export type SeatMap = Omit<Schemas['SeatMapView'], 'viewBox' | 'stage'> & {
  viewBox: { width: number; height: number }
  stage: { x: number; y: number; width: number; height: number }
}
export type SeatMapZone = Schemas['SeatMapZoneView']
export type SeatMapSeat = Schemas['SeatMapSeatView']
export type ZonePrice = Schemas['ZonePriceView']
export type Availability = Schemas['AvailabilityView']
export type StandingAvailability = Schemas['StandingAvailabilityView']

export interface OrderTicket {
  id: string
  zone: string
  seat: string | null
  tariff: string
  priceCents: number
  status: 'PENDING' | 'VALID' | 'CHECKED_IN' | 'CANCELLED' | 'REFUNDED'
  requiresProof: boolean
  /** Signed QR payload, wallet only, live tickets only. */
  qr?: string | null
}
export type Order = Omit<Schemas['OrderView'], 'tickets'> & { tickets: OrderTicket[] }
export type Checkout = Omit<Schemas['CheckoutView'], 'order'> & { order: Order }
export type HoldInfo = Omit<Schemas['HoldView'], 'standing'> & {
  standing: { zoneId: string; quantity: number }[]
}
