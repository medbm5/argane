/**
 * Generated from the Symfony OpenAPI document by scripts/api-types.mjs.
 * Do not edit by hand: run `npm run api:types`.
 */

export interface paths {
    "/api/performances/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_performance"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/events": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_events"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/events/{slug}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_event"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/me": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_me"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/health": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_health"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/venue": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_venue"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
}
export type webhooks = Record<string, never>;
export interface components {
    schemas: {
        PerformanceDetailView: {
            id: string;
            eventId: string;
            eventSlug: string;
            eventTitle: string;
            eventSubtitle?: string | null;
            category: string;
            categoryLabel: string;
            posterUrl?: string | null;
            durationMinutes: number;
            ageRating?: number | null;
            startsAt: string;
            doorsAt: string;
            endsAt: string;
            salesOpenAt: string;
            salesCloseAt: string;
            status: string;
            isOnSale: boolean;
            cancelReason?: string | null;
            remaining: number;
            capacity: number;
            fromPriceCents?: number | null;
            currency: string;
            layoutId: string;
            layoutName: string;
        };
        PerformanceSummaryView: {
            id: string;
            /** ISO 8601, UTC */
            startsAt: string;
            doorsAt: string;
            /** SCHEDULED | ON_SALE | SALES_CLOSED | CANCELLED | PAST | SOLD_OUT */
            status: string;
            isOnSale: boolean;
            remaining: number;
            capacity: number;
            fromPriceCents?: number | null;
            layoutName: string;
        };
        EventListItemView: {
            id: string;
            slug: string;
            title: string;
            subtitle?: string | null;
            category: string;
            categoryLabel: string;
            posterUrl?: string | null;
            durationMinutes: number;
            ageRating?: number | null;
            performanceCount: number;
            fromPriceCents?: number | null;
            currency: string;
            nextPerformance?: components["schemas"]["PerformanceSummaryView"] | (never | null);
        };
        EventDetailView: {
            id: string;
            slug: string;
            title: string;
            subtitle?: string | null;
            category: string;
            categoryLabel: string;
            description: string;
            posterUrl?: string | null;
            durationMinutes: number;
            ageRating?: number | null;
            fromPriceCents?: number | null;
            currency: string;
            performances: components["schemas"]["PerformanceSummaryView"][];
        };
        MeResponse: {
            id: string;
            email: string;
            displayName: string;
            roles: string[];
            isBoxOffice: boolean;
            isOrganizer: boolean;
        };
        LayoutSummary: {
            id: string;
            name: string;
            capacity: number;
            isDefault: boolean;
        };
        VenueResponse: {
            name: string;
            slug: string;
            city: string;
            address: string;
            timezone: string;
            currency: string;
            tagline: string;
            since: number;
            capacity: number;
            layouts: components["schemas"]["LayoutSummary"][];
        };
    };
    responses: never;
    parameters: never;
    requestBodies: never;
    headers: never;
    pathItems: never;
}
export type $defs = Record<string, never>;
export interface operations {
    get_api_performance: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                id: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Performance detail with remaining places and the lowest single online price */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["PerformanceDetailView"];
                };
            };
            /** @description Unknown or unpublished performance */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_events: {
        parameters: {
            query?: {
                category?: "CONCERT" | "THEATRE" | "CONFERENCE" | "DANCE" | "COMEDY" | null;
                /** @description YYYY-MM, venue time zone */
                month?: string | null;
            };
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Published events with an upcoming performance */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["EventListItemView"][];
                };
            };
        };
    };
    get_api_event: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                slug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Event with its performances */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["EventDetailView"];
                };
            };
            /** @description Unknown or unpublished event */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_me: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The signed-in user and their effective roles */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["MeResponse"];
                };
            };
        };
    };
    get_api_health: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            default: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_venue: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Brand information for the landing page */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["VenueResponse"];
                };
            };
        };
    };
}
