import { useState } from 'react'
import { ArrowRight, Download } from 'lucide-react'
import { Link } from 'react-router'
import { StatePanel } from '../design-system/components/StatePanel'
import { StatusBadge } from '../design-system/primitives/Badge'
import { Button } from '../design-system/primitives/Button'
import { SectionTitle } from '../features/catalog/landing/LandingGrid'
import { useDownload, useWallet } from '../features/tickets/api'
import type { Order } from '../lib/api/types'
import { dateTile, formatLongDate, formatMoney, formatTime } from '../lib/format'

/** /mes-billets — upcoming evenings first, then past ones. */
export function WalletPage() {
  const wallet = useWallet()
  // "Now" as of opening the wallet: stable across re-renders.
  const [now] = useState(() => Date.now())
  const orders = wallet.data ?? []
  const upcoming = orders.filter((o) => new Date(o.startsAt).getTime() > now)
  const past = orders.filter((o) => new Date(o.startsAt).getTime() <= now).reverse()

  return (
    <div className="mx-auto max-w-5xl px-4 py-14 sm:px-8">
      <SectionTitle id="wallet-title" kicker="votre portefeuille" bar="bg-pop-teal">
        Mes billets
      </SectionTitle>

      {wallet.isPending ? (
        <div className="mt-10 space-y-6" aria-busy="true">
          {[0, 1].map((i) => (
            <div key={i} className="brutal h-32 animate-pulse bg-surface-alt" />
          ))}
        </div>
      ) : wallet.isError ? (
        <StatePanel
          kind="error"
          kicker="petit incident"
          title="Vos billets restent en coulisses"
          className="mt-10"
          actions={
            <Button variant="gilt" onClick={() => void wallet.refetch()}>
              Réessayer
            </Button>
          }
        >
          Vos billets n’ont pas pu être chargés. Réessayez dans un instant.
        </StatePanel>
      ) : orders.length === 0 ? (
        <StatePanel
          kind="empty"
          kicker="la scène vous attend"
          title="Pas encore de billets"
          className="mt-10"
          actions={
            <Button asChild variant="gilt" size="lg">
              <Link to="/programme">
                Voir la programmation <ArrowRight aria-hidden className="size-5" />
              </Link>
            </Button>
          }
        >
          Vos réservations apparaîtront ici, QR code compris.
        </StatePanel>
      ) : (
        <>
          <OrderList
            title="À venir"
            orders={upcoming}
            empty="Aucune sortie prévue pour l’instant."
          />
          {past.length > 0 ? <OrderList title="Passés" orders={past} muted /> : null}
        </>
      )}
    </div>
  )
}

function OrderList({
  title,
  orders,
  empty,
  muted = false,
}: {
  title: string
  orders: Order[]
  empty?: string
  muted?: boolean
}) {
  const { download, busy } = useDownload()
  return (
    <section aria-label={title} className="mt-12">
      <h2 className="font-display text-3xl">{title}</h2>
      {orders.length === 0 ? <p className="mt-3 text-muted">{empty}</p> : null}
      <ul className="mt-5 space-y-5">
        {orders.map((order) => {
          const tile = dateTile(order.startsAt)
          const path = `/orders/${order.id}/tickets.pdf`
          const live = order.tickets.filter(
            (t) => t.status === 'VALID' || t.status === 'CHECKED_IN',
          )
          return (
            <li
              key={order.id}
              className={`brutal grid gap-4 bg-surface p-5 sm:grid-cols-[5rem_1fr_auto] sm:items-center ${muted ? 'opacity-70' : ''}`}
            >
              <span className="flex flex-col items-center leading-none" aria-hidden>
                <span className="tabular font-display text-5xl">{tile.day}</span>
                <span className="small-caps text-xs">{tile.month}</span>
              </span>
              <div className="min-w-0">
                <Link
                  to={`/mes-billets/${order.id}`}
                  className="font-display text-3xl leading-none hover:text-accent"
                >
                  {order.eventTitle}
                </Link>
                <p className="mt-1 text-sm text-muted">
                  {formatLongDate(order.startsAt)} · {formatTime(order.startsAt)} ·{' '}
                  {order.reference}
                </p>
                <p className="mt-2 flex flex-wrap items-center gap-2 text-sm">
                  <StatusBadge status={order.status} />
                  <span>
                    {order.tickets.length} billet{order.tickets.length > 1 ? 's' : ''} ·{' '}
                    {formatMoney(order.totalCents, order.currency)}
                  </span>
                </p>
              </div>
              <div className="flex flex-wrap gap-2 sm:flex-col">
                <Button asChild variant="gilt" size="sm">
                  <Link to={`/mes-billets/${order.id}`}>Voir les billets</Link>
                </Button>
                {live.length > 0 ? (
                  <Button
                    variant="secondary"
                    size="sm"
                    loading={busy === path}
                    leadingIcon={<Download aria-hidden className="size-4" />}
                    onClick={() => void download(path, `billets-${order.reference}.pdf`)}
                  >
                    Tout en PDF
                  </Button>
                ) : null}
              </div>
            </li>
          )
        })}
      </ul>
    </section>
  )
}
