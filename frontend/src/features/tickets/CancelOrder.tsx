import { useState } from 'react'
import { Button } from '../../design-system/primitives/Button'
import { Dialog } from '../../design-system/primitives/Dialog'
import { toast } from '../../design-system/toast-store'
import { ApiError } from '../../lib/api'
import type { Order } from '../../lib/api/types'
import { formatLongDate, formatMoney, formatTime } from '../../lib/format'
import { cancellableUntil, CANCELLATION_NOTICE_HOURS, useCancelOrder } from './api'

/**
 * Cancel-and-refund for online orders, up to 48 h before the show. Shows why it is not
 * possible otherwise, so the buyer is never left guessing.
 */
export function CancelOrder({ order, now }: { order: Order; now: number }) {
  const [open, setOpen] = useState(false)
  const cancel = useCancelOrder()
  const deadline = cancellableUntil(order)

  if (order.status !== 'PAID') return null
  if (order.channel !== 'ONLINE') {
    return <p className="text-sm text-muted">Billets achetés au guichet : annulation au guichet.</p>
  }
  if (deadline.getTime() <= now) {
    return (
      <p className="text-sm text-muted">
        Annulation en ligne close ({CANCELLATION_NOTICE_HOURS} h avant la représentation).
      </p>
    )
  }

  return (
    <div>
      <Button
        variant="danger"
        onClick={() => {
          setOpen(true)
        }}
      >
        Annuler la commande
      </Button>
      <p className="mt-2 text-xs text-muted">
        Possible jusqu’au {formatLongDate(deadline.toISOString())} à{' '}
        {formatTime(deadline.toISOString())}.
      </p>
      <Dialog
        open={open}
        onOpenChange={setOpen}
        title="Annuler cette commande ?"
        description={`Les ${String(order.tickets.length)} billet(s) seront annulés et les places remises en vente. Vous serez remboursé de ${formatMoney(order.totalCents, order.currency)} sur votre carte sous 5 à 10 jours. Cette action est définitive.`}
        footer={
          <>
            <Button
              variant="secondary"
              onClick={() => {
                setOpen(false)
              }}
            >
              Garder mes billets
            </Button>
            <Button
              variant="danger"
              loading={cancel.isPending}
              onClick={() => {
                cancel.mutate(order.id, {
                  onSuccess: (result) => {
                    setOpen(false)
                    toast(
                      result.status === 'REFUNDED'
                        ? 'Commande annulée et remboursée'
                        : 'Commande annulée, remboursement en cours',
                      { tone: 'success' },
                    )
                  },
                  onError: (error) => {
                    toast(
                      error instanceof ApiError ? error.message : 'L’annulation n’a pas abouti.',
                      { tone: 'danger' },
                    )
                  },
                })
              }}
            >
              Annuler et me rembourser
            </Button>
          </>
        }
      />
    </div>
  )
}
