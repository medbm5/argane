import { ArrowLeft, Download } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { StatusBadge } from '../design-system/primitives/Badge'
import { Button } from '../design-system/primitives/Button'
import { CancelOrder } from '../features/tickets/CancelOrder'
import { OrderTickets } from '../features/tickets/OrderTickets'
import { useDownload, useWallet } from '../features/tickets/api'
import { formatLongDate, formatMoney, formatTime } from '../lib/format'
import { NotFoundPage } from './NotFoundPage'

/** /mes-billets/:orderId — one order: its tickets with QR codes and downloads. */
export function OrderDetailPage() {
  const { orderId = '' } = useParams()
  const wallet = useWallet()
  const { download, busy } = useDownload()
  const [now] = useState(() => Date.now())

  if (wallet.isPending) {
    return <p className="px-4 py-20 text-center font-display text-3xl">Chargement…</p>
  }
  const order = wallet.data?.find((o) => o.id === orderId)
  if (!order) return <NotFoundPage />

  const path = `/orders/${order.id}/tickets.pdf`
  const live = order.tickets.some((t) => t.status === 'VALID' || t.status === 'CHECKED_IN')

  return (
    <div className="mx-auto max-w-4xl px-4 py-10 sm:px-8">
      <Link
        to="/mes-billets"
        className="inline-flex items-center gap-2 text-sm font-bold tracking-[0.12em] uppercase hover:text-accent"
      >
        <ArrowLeft aria-hidden className="size-4" /> Mes billets
      </Link>

      <header className="mt-6 flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="-rotate-1 font-marker text-lg text-accent">commande {order.reference}</p>
          <h1 className="font-display text-[clamp(2.5rem,6vw,4.5rem)] leading-none">
            <Link to={`/spectacles/${order.eventSlug}`} className="hover:text-accent">
              {order.eventTitle}
            </Link>
          </h1>
          <p className="mt-2 text-muted">
            {formatLongDate(order.startsAt)} · {formatTime(order.startsAt)}
          </p>
          <p className="mt-3 flex items-center gap-3">
            <StatusBadge status={order.status} />
            <span className="tabular font-bold">
              {formatMoney(order.totalCents, order.currency)}
            </span>
          </p>
        </div>
        {live ? (
          <Button
            variant="gilt"
            size="lg"
            loading={busy === path}
            leadingIcon={<Download aria-hidden className="size-5" />}
            onClick={() => void download(path, `billets-${order.reference}.pdf`)}
          >
            Télécharger les billets
          </Button>
        ) : null}
      </header>

      <div className="mt-10">
        <OrderTickets order={order} />
      </div>
      <div className="mt-10 border-t-2 border-fg pt-6">
        <CancelOrder order={order} now={now} />
      </div>
      <p className="mt-8 text-sm text-muted">
        Présentez le QR code à l’entrée, sur téléphone ou imprimé. Chaque billet n’est valable
        qu’une fois.
      </p>
    </div>
  )
}
