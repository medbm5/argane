import { useState, type SyntheticEvent } from 'react'
import { Button } from '../../design-system/primitives/Button'
import { Input } from '../../design-system/primitives/Input'
import { toast } from '../../design-system/toast-store'
import { ApiError } from '../../lib/api'
import { formatLongDate, formatMoney, formatTime } from '../../lib/format'
import { useCounterCancel, useOrderLookup, usePendingRefunds, useSettleRefund } from './api'

const METHOD_LABEL: Record<string, string> = { CASH: 'espèces', CARD_TERMINAL: 'carte (TPE)' }

/** Money to hand back at the counter, and cancelling a counter sale by reference. */
export function CounterRefunds() {
  const pending = usePendingRefunds()
  const settle = useSettleRefund()

  return (
    <div className="grid gap-8 lg:grid-cols-[1fr_22rem]">
      <section aria-labelledby="pending-title">
        <h2 id="pending-title" className="font-display text-3xl">
          Remboursements à rendre
        </h2>
        {pending.data?.length === 0 ? (
          <p className="mt-4 text-muted">Rien à rendre pour l’instant.</p>
        ) : null}
        <ul className="mt-4 space-y-3">
          {(pending.data ?? []).map((refund) => (
            <li
              key={refund.id}
              className="brutal grid gap-2 bg-surface p-4 sm:grid-cols-[1fr_auto] sm:items-center"
            >
              <div>
                <p className="font-bold">
                  {refund.reference} · {refund.eventTitle}
                </p>
                <p className="text-sm text-muted">
                  {formatLongDate(refund.startsAt)} · {formatTime(refund.startsAt)} ·{' '}
                  {refund.tickets} billet(s) · {refund.buyerName ?? 'spectateur anonyme'} · payé en{' '}
                  {METHOD_LABEL[refund.paidWith ?? ''] ?? '—'}
                </p>
                <p className="text-sm">{refund.reason}</p>
              </div>
              <Button
                variant="gilt"
                loading={settle.isPending && settle.variables === refund.id}
                onClick={() => {
                  settle.mutate(refund.id, {
                    onSuccess: () => {
                      toast(`${formatMoney(refund.amountCents, refund.currency)} rendus`, {
                        tone: 'success',
                      })
                    },
                  })
                }}
              >
                Rendu {formatMoney(refund.amountCents, refund.currency)}
              </Button>
            </li>
          ))}
        </ul>
      </section>
      <CounterCancel />
    </div>
  )
}

function CounterCancel() {
  const [reference, setReference] = useState('')
  const lookup = useOrderLookup()
  const cancel = useCounterCancel()
  const order = lookup.data

  function find(e: SyntheticEvent<HTMLFormElement>) {
    e.preventDefault()
    if (reference.trim()) lookup.mutate(reference)
  }

  return (
    <section aria-labelledby="cancel-title" className="brutal self-start bg-surface p-5">
      <h2 id="cancel-title" className="font-display text-2xl">
        Annuler une vente guichet
      </h2>
      <form onSubmit={find} className="mt-4 flex items-end gap-2">
        <Input
          label="Référence"
          placeholder="ARG-26-000123"
          value={reference}
          onChange={(e) => {
            setReference(e.target.value)
          }}
        />
        <Button type="submit" variant="secondary" loading={lookup.isPending}>
          Chercher
        </Button>
      </form>
      {lookup.isError ? (
        <p className="mt-3 text-sm font-bold text-danger">Commande introuvable.</p>
      ) : null}
      {order ? (
        <div className="mt-4 border-t-2 border-fg pt-4">
          <p className="font-bold">
            {order.reference} · {order.eventTitle}
          </p>
          <p className="text-sm text-muted">
            {order.tickets.length} billet(s) · {formatMoney(order.totalCents, order.currency)} ·{' '}
            {order.status}
          </p>
          <Button
            variant="danger"
            className="mt-3"
            disabled={order.status !== 'PAID'}
            loading={cancel.isPending}
            onClick={() => {
              cancel.mutate(order.id, {
                onSuccess: (result) => {
                  toast(
                    `Vente annulée : rendez ${formatMoney(result.totalCents, result.currency)}`,
                    {
                      tone: 'success',
                    },
                  )
                  lookup.reset()
                  setReference('')
                },
                onError: (error) => {
                  toast(error instanceof ApiError ? error.message : 'Annulation impossible', {
                    tone: 'danger',
                  })
                },
              })
            }}
          >
            Annuler et rembourser
          </Button>
        </div>
      ) : null}
    </section>
  )
}
