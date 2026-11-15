import { CheckCircle2, CircleAlert, LoaderCircle } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link, useParams } from 'react-router'
import { Button } from '../design-system/primitives/Button'
import { useOrder } from '../features/checkout/api'
import { formatLongDate, formatMoney, formatTime } from '../lib/format'

/** /commandes/:orderId/confirmation — waits for the payment to settle, then celebrates. */
export function OrderConfirmationPage() {
  const { orderId = '' } = useParams()
  const order = useOrder(orderId)

  if (order.isError) {
    return (
      <Shell icon={<CircleAlert className="size-12 text-danger" />} title="Commande introuvable">
        <p>Cette commande n’existe pas ou ne vous appartient pas.</p>
      </Shell>
    )
  }
  const data = order.data
  if (!data || data.status === 'PENDING_PAYMENT') {
    return (
      <Shell
        icon={<LoaderCircle className="size-12 animate-spin text-accent" />}
        title="Paiement en cours…"
      >
        <p aria-live="polite">
          Nous attendons la confirmation de votre banque. Ne fermez pas la page.
        </p>
      </Shell>
    )
  }
  if (data.status === 'FAILED') {
    return (
      <Shell icon={<CircleAlert className="size-12 text-danger" />} title="Paiement refusé">
        <p>Votre carte a été refusée. Vos places restent réservées le temps restant.</p>
        {data.holdId ? (
          <Button asChild variant="gilt" size="lg" className="mt-6">
            <Link to={`/commande/${data.holdId}`}>Réessayer</Link>
          </Button>
        ) : null}
      </Shell>
    )
  }
  if (data.status !== 'PAID') {
    return (
      <Shell icon={<CircleAlert className="size-12 text-danger" />} title="Commande non aboutie">
        <p>
          Le délai de réservation était dépassé et certaines places ont été prises entre-temps. Si
          vous avez été débité, le remboursement est déjà en route.
        </p>
        <Button asChild variant="gilt" size="lg" className="mt-6">
          <Link to={`/representations/${data.performanceId}`}>Choisir d’autres places</Link>
        </Button>
      </Shell>
    )
  }

  return (
    <Shell icon={<CheckCircle2 className="size-12 text-success" />} title="C’est réservé !">
      <p className="-rotate-1 font-marker text-2xl text-accent">rendez-vous au théâtre</p>
      <div className="brutal mt-8 bg-surface p-6 text-left">
        <p className="text-sm font-bold tracking-[0.12em] uppercase">Commande {data.reference}</p>
        <p className="mt-2 font-display text-4xl leading-none">{data.eventTitle}</p>
        <p className="mt-1 text-muted">
          {formatLongDate(data.startsAt)} · {formatTime(data.startsAt)}
        </p>
        <ul className="mt-5 divide-y-2 divide-fg/10">
          {data.tickets.map((ticket) => (
            <li key={ticket.id} className="flex items-center justify-between gap-3 py-2">
              <span>
                <span className="font-bold">{ticket.seat ? `Place ${ticket.seat}` : 'Debout'}</span>{' '}
                <span className="text-sm text-muted">
                  · {ticket.zone} · {ticket.tariff}
                </span>
              </span>
              <span className="tabular">{formatMoney(ticket.priceCents, data.currency)}</span>
            </li>
          ))}
        </ul>
        <p className="mt-4 flex justify-between border-t-2 border-fg pt-3 font-display text-2xl">
          <span>Total</span>
          <span className="tabular">{formatMoney(data.totalCents, data.currency)}</span>
        </p>
      </div>
      <p className="mt-6 text-sm">Vos billets avec QR code arrivent aussi par email.</p>
      <Button asChild variant="gilt" size="lg" className="mt-6">
        <Link to="/mes-billets">Voir mes billets</Link>
      </Button>
    </Shell>
  )
}

function Shell({ icon, title, children }: { icon: ReactNode; title: string; children: ReactNode }) {
  return (
    <div className="mx-auto max-w-2xl px-4 py-16 text-center">
      <div className="flex justify-center">{icon}</div>
      <h1 className="mt-4 font-display text-6xl">{title}</h1>
      <div className="mt-4">{children}</div>
    </div>
  )
}
