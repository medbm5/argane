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
        'ticket-stub relative flex flex-col border-2 border-ink-900 bg-paper-50 text-ink-900 shadow-[6px_6px_0_var(--ink-900)] sm:flex-row',
        cancelled && 'opacity-60 grayscale',
        className,
      )}
    >
      <div className="ticket-body flex-1 p-6">
        <div className="flex items-start justify-between gap-3">
          <p className="text-xs font-bold tracking-[0.2em] text-pop-red uppercase">
            Théâtre Argane
          </p>
          {status ? <StatusBadge status={status} /> : null}
        </div>
        <h3 className="mt-2 font-display text-4xl leading-none">{eventTitle}</h3>
        <p className="mt-1 text-sm text-sepia-800">
          <time>{dateLabel}</time> · <time>{timeLabel}</time>
        </p>

        <dl className="mt-5 grid grid-cols-3 gap-4 border-t-2 border-dashed border-ink-900/30 pt-4">
          <div>
            <dt className="text-[0.65rem] font-bold tracking-[0.14em] text-sepia-800 uppercase">
              Zone
            </dt>
            <dd className="font-display text-xl">{zoneLabel}</dd>
          </div>
          <div>
            <dt className="text-[0.65rem] font-bold tracking-[0.14em] text-sepia-800 uppercase">
              Place
            </dt>
            <dd className="font-display text-xl">{seatLabel ?? 'Debout'}</dd>
          </div>
          <div>
            <dt className="text-[0.65rem] font-bold tracking-[0.14em] text-sepia-800 uppercase">
              Tarif
            </dt>
            <dd className="font-display text-xl">{tariffLabel}</dd>
          </div>
        </dl>

        <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
          <span className="tabular text-sepia-800">{reference}</span>
          <span className="tabular font-medium">{priceLabel}</span>
        </div>
        {requiresProof ? (
          <p className="mt-3 inline-block border-2 border-ink-900 bg-pop-yellow px-2 py-1 text-xs font-bold">
            Justificatif à présenter à l’entrée (tarif réduit).
          </p>
        ) : null}
        {actions ? <div className="mt-4 flex flex-wrap gap-2">{actions}</div> : null}
      </div>

      <div
        aria-hidden
        className="ticket-perforation relative h-0 border-t-2 border-dashed border-ink-900 sm:h-auto sm:w-0 sm:border-t-0 sm:border-l-2"
      />

      <div className="ticket-stub-side flex flex-col items-center justify-center gap-2 p-5 sm:w-52">
        <div className="relative grid size-40 place-items-center border-2 border-ink-900 bg-paper-50 p-1">
          {qr ?? <div className="size-full bg-paper-200" />}
          {cancelled ? (
            <span className="absolute inset-0 grid place-items-center bg-paper-50/80 font-display text-lg text-rust-700">
              Annulé
            </span>
          ) : null}
        </div>
        <p className="text-center text-xs font-bold tracking-[0.14em] uppercase">
          {seatLabel ? `Place ${seatLabel}` : zoneLabel}
        </p>
        <span aria-hidden className="flex h-2 w-24">
          <span className="flex-1 bg-pop-yellow" />
          <span className="flex-1 bg-pop-blue" />
          <span className="flex-1 bg-pop-pink" />
          <span className="flex-1 bg-pop-teal" />
        </span>
      </div>
    </article>
  )
}
