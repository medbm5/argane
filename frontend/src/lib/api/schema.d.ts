/**
 * Generated from the Symfony OpenAPI document by scripts/api-types.mjs.
 * Do not edit by hand: run `npm run api:types`.
 */

export interface paths {
    "/api/orders/{id}/cancel": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_order_cancel"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
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
    "/api/box-office/performances": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_box_office_performances"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/box-office/holds/{id}/sell": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_box_office_sell"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/box-office/orders": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_box_office_order_lookup"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/box-office/orders/{id}/cancel": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_box_office_cancel"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/box-office/refunds": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_box_office_refunds"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/box-office/refunds/{id}/settle": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_box_office_refund_settle"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/performances/{id}/cancel": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_performance_cancel"];
        delete?: never;
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
    "/api/studio/posters": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_poster_upload"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/posters/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_poster"];
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
    "/api/studio/events": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_studio_events"];
        put?: never;
        post: operations["post_api_studio_event_create"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/events/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_studio_event"];
        put: operations["put_api_studio_event_update"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/events/{id}/publish": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_event_publish"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/events/{id}/archive": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_event_archive"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/events/{id}/performances": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_performance_schedule"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/performances/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put: operations["put_api_studio_performance_update"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/performances/{id}/publish": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_performance_publish"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/performances/{id}/open-sales": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_performance_open"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/performances/{id}/close-sales": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_performance_close"];
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
    "/api/studio/integrations/sheets": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_integration_sheets"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/integrations/sheets/sync": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_integration_sheets_sync"];
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
    "/api/studio/tariffs": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_studio_tariffs"];
        put?: never;
        post: operations["post_api_studio_tariff_create"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/tariffs/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put: operations["put_api_studio_tariff_update"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/tariffs/{id}/{state}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_tariff_activate"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/performances/{id}/prices": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_studio_prices"];
        put: operations["put_api_studio_prices_update"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/performances/{id}/prices/apply-to-event": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_prices_apply"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/analytics/overview": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_analytics_overview"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/analytics/fill": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_analytics_fill"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/analytics/revenue": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_analytics_revenue"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/analytics/timeseries": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_analytics_timeseries"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/analytics/booking-curve": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_analytics_booking_curve"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/analytics/heatmap": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_analytics_heatmap"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/analytics/at-risk": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_analytics_at_risk"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/sales": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_studio_sales"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/sales.csv": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_studio_sales_csv"];
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
    "/api/check-in/performances/{id}/scan": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_check_in_scan"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/performances/{id}/counts": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_check_in_counts"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/me/orders": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_wallet"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/orders/{id}/tickets.pdf": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_order_pdf"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/tickets/{id}.pdf": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_ticket_pdf"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/layouts": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_studio_layouts"];
        put?: never;
        post: operations["post_api_studio_layout_create"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/layouts/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["get_api_studio_layout"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/layouts/preview": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_layout_preview"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/layouts/{id}/clone": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_layout_clone"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/studio/layouts/{id}/archive": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["post_api_studio_layout_archive"];
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
        CounterSaleRequest: {
            /** @default [] */
            seats: {
                [key: string]: string;
            }[];
            /** @default [] */
            standing: {
                [key: string]: number | string;
            }[];
            /**
             * CASH or CARD_TERMINAL; ignored when nothing is due (invitations).
             * @default CASH
             * @enum {string}
             */
            method: "CASH" | "CARD_TERMINAL" | "NONE";
            /**
             * Required for invitations.
             * @default null
             */
            reason: string | null;
            /** @default null */
            buyerName: string | null;
            /** @default null */
            buyerEmail: string | null;
        };
        EventInput: {
            /** @default  */
            title: string;
            /** @default null */
            subtitle: string | null;
            /**
             * @default THEATRE
             * @enum {string}
             */
            category: "CONCERT" | "THEATRE" | "CONFERENCE" | "DANCE" | "COMEDY";
            /** @default  */
            description: string;
            /** @default 90 */
            durationMinutes: number;
            /** @default null */
            ageRating: number | null;
            /** @default null */
            posterPath: string | null;
        };
        PerformanceInput: {
            /** @default  */
            startsAt: string;
            /** @default  */
            salesOpenAt: string;
            /**
             * Default: at curtain time.
             * @default null
             */
            salesCloseAt: string | null;
            /** @default 30 */
            doorsMinutesBefore: number;
            /** @default  */
            layoutId: string;
        };
        TariffInput: {
            /** @default  */
            code: string;
            /** @default  */
            label: string;
            /**
             * @default [
             *       "ONLINE",
             *       "BOX_OFFICE"
             *     ]
             */
            channels: string[];
            /** @default [] */
            allowedRoles: string[];
            /** @default 1 */
            minQuantity: number;
            /** @default null */
            maxQuantity: number | null;
            /** @default false */
            requiresProofAtEntry: boolean;
            /** @default false */
            isFree: boolean;
            /** @default null */
            description: string | null;
            /** @default 0 */
            sortOrder: number;
        };
        LayoutSpecInput: {
            /** @default  */
            name: string;
            /** @default [] */
            zones: {
                [key: string]: boolean | number | string[] | string | (never | null);
            }[];
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
        CounterPerformanceView: {
            id: string;
            eventTitle: string;
            eventSlug: string;
            category: string;
            posterUrl?: string | null;
            startsAt: string;
            status: string;
            sellable: boolean;
            onlineOpen: boolean;
            remaining: number;
            capacity: number;
            layoutName: string;
        };
        PendingRefundView: {
            id: string;
            orderId: string;
            reference: string;
            eventTitle: string;
            startsAt: string;
            amountCents: number;
            currency: string;
            paidWith?: string | null;
            buyerName?: string | null;
            buyerEmail?: string | null;
            reason: string;
            createdAt: string;
            tickets: number;
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
            months: string[];
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
        StudioPerformanceView: {
            id: string;
            startsAt: string;
            doorsAt: string;
            salesOpenAt: string;
            salesCloseAt: string;
            /** SCHEDULED, ON_SALE, SALES_CLOSED, CANCELLED, PAST */
            status: string;
            published: boolean;
            cancelReason?: string | null;
            layoutId: string;
            layoutName: string;
            sold: number;
            capacity: number;
            revenueCents: number;
            pricedCells: number;
        };
        StudioEventView: {
            id: string;
            slug: string;
            title: string;
            subtitle?: string | null;
            category: string;
            categoryLabel: string;
            description: string;
            durationMinutes: number;
            ageRating?: number | null;
            posterPath?: string | null;
            status: string;
            sold: number;
            capacity: number;
            revenueCents: number;
            performances: components["schemas"]["StudioPerformanceView"][];
        };
        MeResponse: {
            id: string;
            email: string;
            displayName: string;
            roles: string[];
            isBoxOffice: boolean;
            isOrganizer: boolean;
        };
        TariffView: {
            id: string;
            code: string;
            label: string;
            description?: string | null;
            channels: string[];
            allowedRoles: string[];
            minQuantity: number;
            maxQuantity?: number | null;
            requiresProofAtEntry: boolean;
            isFree: boolean;
            active: boolean;
            sortOrder: number;
        };
        PriceGridView: {
            performanceId: string;
            currency: string;
            zones: {
                [key: string]: string;
            }[];
            tariffs: components["schemas"]["TariffView"][];
            cells: {
                [key: string]: number | string;
            }[];
        };
        SalesPageView: {
            rows: {
                [key: string]: number | string | (never | null);
            }[];
            page: number;
            perPage: number;
            total: number;
            paidOrders: number;
            ticketsSold: number;
            revenueCents: number;
            currency: string;
        };
        CheckInResult: {
            verdict: string;
            message: string;
            /** @default null */
            ticketId: string | null;
            /** @default null */
            reference: string | null;
            /** @default null */
            zone: string | null;
            /** @default null */
            seat: string | null;
            /** @default null */
            tariff: string | null;
            /** @default false */
            requiresProof: boolean;
            /** @default null */
            holder: string | null;
            /**
             * When it was first scanned (ALREADY_USED).
             * @default null
             */
            checkedInAt: string | null;
            /**
             * The ticket's real performance (WRONG_PERFORMANCE).
             * @default null
             */
            ticketPerformanceStartsAt: string | null;
            /** @default null */
            ticketEventTitle: string | null;
            /** @default 0 */
            admitted: number;
            /** @default 0 */
            expected: number;
        };
        LayoutView: {
            id?: string | null;
            name: string;
            isDefault: boolean;
            locked: boolean;
            archived: boolean;
            clonedFromName?: string | null;
            capacity: number;
            createdAt?: string | null;
            viewBox: {
                [key: string]: number;
            };
            stage: {
                [key: string]: number;
            };
            zones: {
                [key: string]: number | string;
            }[];
            seats: {
                [key: string]: boolean | number | string;
            }[];
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
    post_api_order_cancel: {
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
            /** @description Order cancelled, places back on sale, refund issued (REFUNDED once the money is back) */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["OrderView"];
                };
            };
            /** @description ORDER_NOT_CANCELLABLE, PERFORMANCE_CANCELLED, ALREADY_CHECKED_IN */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description CANCELLATION_TOO_LATE (less than 48 h before), COUNTER_ORDER */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
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
            /** @description TOO_MANY_CHECKOUTS */
            429: {
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
    get_api_box_office_performances: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Today and the next 14 days, soonest first */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["CounterPerformanceView"][];
                };
            };
        };
    };
    post_api_box_office_sell: {
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
                "application/json": components["schemas"]["CounterSaleRequest"];
            };
        };
        responses: {
            /** @description Paid counter order (tickets valid, places sold) */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["OrderView"];
                };
            };
        };
    };
    get_api_box_office_order_lookup: {
        parameters: {
            query: {
                reference: string;
            };
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Order found by reference (ARG-26-000123) */
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
    post_api_box_office_cancel: {
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
            /** @description Counter sale cancelled, money handed back, places back on sale */
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
    get_api_box_office_refunds: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Refunds to hand back at the counter, oldest first */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["PendingRefundView"][];
                };
            };
        };
    };
    post_api_box_office_refund_settle: {
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
            /** @description Money handed back; the order is REFUNDED */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["PendingRefundView"];
                };
            };
        };
    };
    post_api_studio_performance_cancel: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                id: string;
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": {
                    reason: string;
                };
            };
        };
        responses: {
            /** @description Sales stopped; refunds run in the background */
            200: {
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
    post_api_studio_poster_upload: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: {
            content: {
                "multipart/form-data": {
                    /** Format: binary */
                    file: string;
                };
            };
        };
        responses: {
            /** @description Stored; use `path` as the event poster path */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        path: string;
                    };
                };
            };
            /** @description POSTER_INVALID or POSTER_TOO_LARGE */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_poster: {
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
            /** @description The image, cached for a year (posters never change; a new upload gets a new id) */
            200: {
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
    get_api_studio_events: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Every event, newest first, with sales totals */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["StudioEventView"][];
                };
            };
        };
    };
    post_api_studio_event_create: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["EventInput"];
            };
        };
        responses: {
            /** @description Draft event */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["StudioEventView"];
                };
            };
        };
    };
    get_api_studio_event: {
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
            /** @description Event with all its performances */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["StudioEventView"];
                };
            };
        };
    };
    put_api_studio_event_update: {
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
                "application/json": components["schemas"]["EventInput"];
            };
        };
        responses: {
            /** @description Updated event */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["StudioEventView"];
                };
            };
        };
    };
    post_api_studio_event_publish: {
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
            /** @description Event visible in the programme */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["StudioEventView"];
                };
            };
        };
    };
    post_api_studio_event_archive: {
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
            /** @description Event archived */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["StudioEventView"];
                };
            };
        };
    };
    post_api_studio_performance_schedule: {
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
                "application/json": components["schemas"]["PerformanceInput"];
            };
        };
        responses: {
            /** @description Performance scheduled (unpublished) */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["StudioEventView"];
                };
            };
        };
    };
    put_api_studio_performance_update: {
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
                "application/json": components["schemas"]["PerformanceInput"];
            };
        };
        responses: {
            /** @description Rescheduled (layout changes only before publication) */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["StudioEventView"];
                };
            };
        };
    };
    post_api_studio_performance_publish: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                id: string;
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": {
                    openSales?: boolean;
                };
            };
        };
        responses: {
            /** @description Published, inventory generated */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["StudioEventView"];
                };
            };
        };
    };
    post_api_studio_performance_open: {
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
            /** @description On sale */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["StudioEventView"];
                };
            };
        };
    };
    post_api_studio_performance_close: {
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
            /** @description Online and counter sales closed */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["StudioEventView"];
                };
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
    get_api_integration_sheets: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description configured, url, lastSyncAt, lastError, rows */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_integration_sheets_sync: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Synced now; the new status */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description SHEETS_NOT_CONFIGURED */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description SHEETS_SYNC_FAILED (Google refused or is unreachable) */
            502: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
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
    get_api_studio_tariffs: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description All tariffs, inactive included */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["TariffView"][];
                };
            };
        };
    };
    post_api_studio_tariff_create: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["TariffInput"];
            };
        };
        responses: {
            /** @description Tariff created */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["TariffView"];
                };
            };
        };
    };
    put_api_studio_tariff_update: {
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
                "application/json": components["schemas"]["TariffInput"];
            };
        };
        responses: {
            /** @description Tariff updated (code is permanent) */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["TariffView"];
                };
            };
        };
    };
    post_api_studio_tariff_activate: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                id: string;
                state: "activate" | "deactivate";
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Active state changed */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["TariffView"];
                };
            };
        };
    };
    get_api_studio_prices: {
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
            /** @description Zones × tariffs grid */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["PriceGridView"];
                };
            };
        };
    };
    put_api_studio_prices_update: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                id: string;
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": {
                    cells?: {
                        zoneId?: string;
                        tariffId?: string;
                        amountCents?: number | null;
                    }[];
                };
            };
        };
        responses: {
            /** @description Grid replaced (null cells removed); sold tickets keep their price */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["PriceGridView"];
                };
            };
        };
    };
    post_api_studio_prices_apply: {
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
            /** @description Grid copied to the other performances of the event */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        updated?: number;
                    };
                };
            };
        };
    };
    get_api_analytics_overview: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description KPIs (revenue, tickets, orders, average basket, fill rate, online share, refunds) with previous-period deltas, and the daily series */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_analytics_fill: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Fill per performance and zone (performances starting in the range) */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_analytics_revenue: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Net revenue by tariff and by channel */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_analytics_timeseries: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Tickets and revenue per venue day */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_analytics_booking_curve: {
        parameters: {
            query?: {
                performance?: string;
            };
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Cumulative share sold N days before the show: past average and, optionally, one performance */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_analytics_heatmap: {
        parameters: {
            query: {
                layout: string;
            };
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Sell-through rate per seat of a layout */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_analytics_at_risk: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Upcoming performances selling slower than past ones at the same distance from the show */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_studio_sales: {
        parameters: {
            query?: {
                event?: string;
                performance?: string;
                channel?: "ONLINE" | "BOX_OFFICE";
                status?: "PAID" | "CANCELLED" | "REFUNDED" | "PARTIALLY_REFUNDED";
                /** @description YYYY-MM-DD (venue day) */
                from?: string;
                /** @description YYYY-MM-DD inclusive */
                to?: string;
                /** @description Reference, name or email */
                q?: string;
                page?: number;
            };
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Orders, newest first, with totals */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["SalesPageView"];
                };
            };
        };
    };
    get_api_studio_sales_csv: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description One line per ticket (same filters), semicolon-separated UTF-8 with BOM for Excel */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "text/csv": unknown;
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
    post_api_check_in_scan: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                id: string;
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": {
                    qr: string;
                };
            };
        };
        responses: {
            /** @description A verdict for the scanner (admitted, already used, wrong date, not valid, invalid) */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["CheckInResult"];
                };
            };
        };
    };
    get_api_check_in_counts: {
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
            /** @description Admitted so far / tickets expected */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        admitted?: number;
                        expected?: number;
                    };
                };
            };
        };
    };
    get_api_wallet: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The caller's ticketed orders, soonest first, with QR payloads for live tickets */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["OrderView"][];
                };
            };
        };
    };
    get_api_order_pdf: {
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
            /** @description All live tickets of the order, one page each */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/pdf": unknown;
                };
            };
        };
    };
    get_api_ticket_pdf: {
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
            /** @description One ticket */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/pdf": unknown;
                };
            };
        };
    };
    get_api_studio_layouts: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description All configurations (archived included), without seats */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["LayoutView"][];
                };
            };
        };
    };
    post_api_studio_layout_create: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["LayoutSpecInput"];
            };
        };
        responses: {
            /** @description Saved configuration */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["LayoutView"];
                };
            };
        };
    };
    get_api_studio_layout: {
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
            /** @description One configuration with its seats */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["LayoutView"];
                };
            };
        };
    };
    post_api_studio_layout_preview: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["LayoutSpecInput"];
            };
        };
        responses: {
            /** @description The generated plan, not saved */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["LayoutView"];
                };
            };
        };
    };
    post_api_studio_layout_clone: {
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
            /** @description An editable copy (locked layouts are cloned to be changed) */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["LayoutView"];
                };
            };
        };
    };
    post_api_studio_layout_archive: {
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
            /** @description Hidden from scheduling */
            204: {
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
