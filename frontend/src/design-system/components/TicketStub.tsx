import type { ReactNode } from 'react'
import { cn } from '../cn'
import { StatusBadge } from '../primitives/Badge'

export interface TicketStubProps {
  eventTitle: string
  dateLabel: string
  timeLabel: string
  zoneLabel: string
  seatLabel: string | null
  tariffLabel: string
  priceLabel: string
  reference: string
  status?: string
  qr?: ReactNode
  requiresProof?: boolean
  actions?: ReactNode
  className?: string
}

/**
 * A theatre ticket: main body + tear-off stub with the QR code,
 * separated by a perforated line with notches cut out of both edges.
 */
export function TicketStub({
  eventTitle,
  dateLabel,
  timeLabel,
  zoneLabel,
  seatLabel,
  tariffLabel,
  priceLabel,
  reference,
  status,
  qr,
  requiresProof = false,
  actions,
  className,
}: TicketStubProps) {
  const cancelled = status === 'CANCELLED' || status === 'REFUNDED'

  return (
    <article
      aria-label={`Billet ${eventTitle}, ${zoneLabel}${seatLabel ? `, place ${seatLabel}` : ''}`}
      className={cn(
        'ticket-stub relative flex flex-col overflow-visible text-ink-900 drop-shadow-[0_10px_18px_rgba(43,26,16,0.14)] sm:flex-row',
        cancelled && 'opacity-60 grayscale',
        className,
      )}
    >
      <div className="ticket-body flex-1 rounded-md bg-paper-50 p-6 sm:rounded-r-none">
        <div className="flex items-start justify-between gap-3">
          <p className="small-caps text-sm text-sepia-600">Théâtre Argane</p>
          {status ? <StatusBadge status={status} /> : null}
        </div>
        <h3 className="mt-2 font-display text-3xl leading-tight">{eventTitle}</h3>
        <p className="mt-1 text-sm text-sepia-800">
          <time>{dateLabel}</time> · <time>{timeLabel}</time>
        </p>

        <dl className="mt-5 grid grid-cols-3 gap-4 border-t border-dashed border-paper-300 pt-4">
          <div>
            <dt className="small-caps text-xs text-sepia-600">Zone</dt>
            <dd className="font-display text-xl">{zoneLabel}</dd>
          </div>
          <div>
            <dt className="small-caps text-xs text-sepia-600">Place</dt>
            <dd className="font-display text-xl">{seatLabel ?? 'Debout'}</dd>
          </div>
          <div>
            <dt className="small-caps text-xs text-sepia-600">Tarif</dt>
            <dd className="font-display text-xl">{tariffLabel}</dd>
          </div>
        </dl>

        <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
          <span className="tabular text-sepia-800">{reference}</span>
          <span className="tabular font-medium">{priceLabel}</span>
        </div>
        {requiresProof ? (
          <p className="mt-3 text-xs text-bordeaux-700">
            Justificatif à présenter à l’entrée (tarif réduit).
          </p>
        ) : null}
        {actions ? <div className="mt-4 flex flex-wrap gap-2">{actions}</div> : null}
      </div>

      <div
        aria-hidden
        className="ticket-perforation relative h-0 border-t-2 border-dashed border-paper-300 sm:h-auto sm:w-0 sm:border-t-0 sm:border-l-2"
      />

      <div className="ticket-stub-side flex flex-col items-center justify-center gap-2 rounded-md bg-paper-50 p-5 sm:w-48 sm:rounded-l-none">
        <div className="relative grid size-32 place-items-center rounded-sm bg-paper-50 p-1">
          {qr ?? <div className="size-full rounded-sm bg-paper-200" />}
          {cancelled ? (
            <span className="absolute inset-0 grid place-items-center bg-paper-50/80 font-display text-lg text-rust-700">
              Annulé
            </span>
          ) : null}
        </div>
        <p className="small-caps text-center text-xs text-sepia-600">
          {seatLabel ? `Place ${seatLabel}` : zoneLabel}
        </p>
        <span className="h-1 w-16 rounded-pill bg-gradient-to-r from-gilt-400 via-stage-glow to-gilt-400" />
      </div>
    </article>
  )
}
