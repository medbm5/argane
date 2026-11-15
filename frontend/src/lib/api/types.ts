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
export type SeatMap = Schemas['SeatMapView']
export type SeatMapZone = Schemas['SeatMapZoneView']
export type SeatMapSeat = Schemas['SeatMapSeatView']
export type ZonePrice = Schemas['ZonePriceView']
export type Availability = Schemas['AvailabilityView']
export type StandingAvailability = Schemas['StandingAvailabilityView']
