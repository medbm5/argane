/**
 * Generated from the Symfony OpenAPI document by scripts/api-types.mjs.
 * Do not edit by hand: run `npm run api:types`.
 */

export interface paths {
    "/api/holds/{id}/checkout": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_checkout"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/orders/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_order_show"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/performances/{id}/holds": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_hold_create"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/holds/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_hold_show"];
        put?: never;
        post?: never;
        delete: operations["delete_api_hold_release"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
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
    "/api/performances/{id}/seatmap": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_performance_seatmap"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/performances/{id}/availability": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_performance_availability"];
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
    "/api/webhooks/stripe": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_webhook_stripe"];
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
        CheckoutRequest: {
            /** @default [] */
            seats: {
                [key: string]: string;
            }[];
            /** @default [] */
            standing: {
                [key: string]: number | string;
            }[];
            /**
             * Required for invitations (box office).
             * @default null
             */
            reason: string | null;
        };
        HoldRequest: {
            /** @default [] */
            seatIds: string[];
            /** @default [] */
            standing: {
                [key: string]: number | string;
            }[];
            /**
             * @default ONLINE
             * @enum {string}
             */
            channel: "ONLINE" | "BOX_OFFICE";
        };
        OrderView: {
            id: string;
            reference: string;
            status: string;
            channel: string;
            totalCents: number;
            currency: string;
            performanceId: string;
            eventTitle: string;
            eventSlug: string;
            posterUrl?: string | null;
            startsAt: string;
            createdAt: string;
            paidAt?: string | null;
            holdId?: string | null;
            tickets: {
                [key: string]: boolean | number | string | (never | null);
            }[];
        };
        CheckoutView: {
            order: components["schemas"]["OrderView"];
            /** Null when nothing is due (all invitations): the order is already paid. */
            clientSecret?: string | null;
            holdExpiresAt: string;
        };
        HoldView: {
            id: string;
            performanceId: string;
            status: string;
            channel: string;
            expiresAt: string;
            secondsLeft: number;
            seatIds: string[];
            standing: {
                [key: string]: number | string;
            }[];
        };
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
        ZonePriceView: {
            tariffId: string;
            tariffCode: string;
            label: string;
            description?: string | null;
            amountCents: number;
            minQuantity: number;
            maxQuantity?: number | null;
            requiresProofAtEntry: boolean;
            requiresReason: boolean;
        };
        SeatMapZoneView: {
            id: string;
            code: string;
            name: string;
            kind: string;
            colorToken: string;
            sortOrder: number;
            /** SVG path in plan coordinates */
            shape: string;
            /** Standing zones only */
            capacity?: number | null;
            prices: components["schemas"]["ZonePriceView"][];
        };
        SeatMapSeatView: {
            id: string;
            zoneId: string;
            row: string;
            number: number;
            label: string;
            /** Format: float */
            x: number;
            /** Format: float */
            y: number;
            accessible: boolean;
        };
        SeatMapView: {
            performanceId: string;
            layoutId: string;
            layoutName: string;
            channel: string;
            currency: string;
            viewBox: {
                [key: string]: number;
            };
            stage: {
                [key: string]: number;
            };
            maxSeatsPerOrder: number;
            zones: components["schemas"]["SeatMapZoneView"][];
            seats: components["schemas"]["SeatMapSeatView"][];
        };
        StandingAvailabilityView: {
            zoneId: string;
            remaining: number;
            capacity: number;
        };
        AvailabilityView: {
            performanceId: string;
            status: string;
            isOnSale: boolean;
            remaining: number;
            held: string[];
            mine: string[];
            sold: string[];
            blocked: string[];
            standing: components["schemas"]["StandingAvailabilityView"][];
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
    post_api_checkout: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                id: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["CheckoutRequest"];
            };
        };
        responses: {
            /** @description Order awaiting payment, with the Stripe client secret */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["CheckoutView"];
                };
            };
            /** @description HOLD_EXPIRED */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description CHECKOUT_MISMATCH, TARIFF_NOT_PRICED, TARIFF_MIN_QUANTITY, … */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_order_show: {
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
            /** @description The caller's order; reconciles with Stripe while unpaid */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["OrderView"];
                };
            };
        };
    };
    post_api_hold_create: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                id: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["HoldRequest"];
            };
        };
        responses: {
            /** @description Places held for 10 minutes; replaces the caller's previous hold on this performance */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["HoldView"];
                };
            };
            /** @description SEATS_UNAVAILABLE (context.seatIds) or STANDING_UNAVAILABLE */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description EMPTY_HOLD, TOO_MANY_PLACES, NOT_ON_SALE, UNKNOWN_ZONE */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Too many hold attempts */
            429: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_hold_show: {
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
            /** @description The caller's hold */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["HoldView"];
                };
            };
        };
    };
    delete_api_hold_release: {
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
            /** @description Places released (idempotent) */
            204: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
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
    get_api_performance_seatmap: {
        parameters: {
            query?: {
                /** @description BOX_OFFICE requires the box office role */
                channel?: "ONLINE" | "BOX_OFFICE";
            };
            header?: never;
            path: {
                id: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Plan geometry and the prices the caller may buy */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["SeatMapView"];
                };
            };
            /** @description Box office channel without the role */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
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
    get_api_performance_availability: {
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
            /** @description Seats that are not available, plus standing stock. Polled; supports If-None-Match. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["AvailabilityView"];
                };
            };
            /** @description Unchanged since the given ETag */
            304: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
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
    post_api_webhook_stripe: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Processed, ignored, or already seen */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Invalid signature or payload */
            400: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
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
