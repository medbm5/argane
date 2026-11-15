import { Download } from 'lucide-react'
import { TicketStub } from '../../design-system/components/TicketStub'
import { Button } from '../../design-system/primitives/Button'
import type { Order } from '../../lib/api/types'
import { formatLongDate, formatMoney, formatTime } from '../../lib/format'
import { QrCode } from './QrCode'
import { useDownload } from './api'

/** An order's tickets as stubs with their QR codes, plus per-ticket downloads. */
export function OrderTickets({ order }: { order: Order }) {
  const { download, busy } = useDownload()

  return (
    <ul className="space-y-6">
      {order.tickets.map((ticket) => {
        const live = ticket.status === 'VALID' || ticket.status === 'CHECKED_IN'
        const path = `/tickets/${ticket.id}.pdf`
        return (
          <li key={ticket.id}>
            <TicketStub
              eventTitle={order.eventTitle}
              dateLabel={formatLongDate(order.startsAt)}
              timeLabel={formatTime(order.startsAt)}
              zoneLabel={ticket.zone}
              seatLabel={ticket.seat}
              tariffLabel={ticket.tariff}
              priceLabel={formatMoney(ticket.priceCents, order.currency)}
              reference={order.reference}
              status={ticket.status === 'VALID' ? undefined : ticket.status}
              requiresProof={ticket.requiresProof}
              qr={
                ticket.qr && live ? (
                  <QrCode
                    value={ticket.qr}
                    label={`QR code du billet ${ticket.seat ? `place ${ticket.seat}` : ticket.zone}`}
                  />
                ) : undefined
              }
              actions={
                live ? (
                  <Button
                    variant="secondary"
                    size="sm"
                    loading={busy === path}
                    leadingIcon={<Download aria-hidden className="size-4" />}
                    onClick={() =>
                      void download(
                        path,
                        `billet-${order.reference}-${ticket.seat ?? 'debout'}.pdf`,
                      )
                    }
                  >
                    PDF
                  </Button>
                ) : undefined
              }
            />
          </li>
        )
      })}
    </ul>
  )
}
