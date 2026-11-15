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
