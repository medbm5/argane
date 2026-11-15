import { CheckCircle2, Printer, RotateCcw } from 'lucide-react'
import { useMemo, useReducer, useState } from 'react'
import { Button } from '../../design-system/primitives/Button'
import { Input } from '../../design-system/primitives/Input'
import { Pill } from '../../design-system/primitives/Pill'
import { toast } from '../../design-system/toast-store'
import { ApiError } from '../../lib/api'
import type { HoldInfo, Order, SeatMap as SeatMapData } from '../../lib/api/types'
import { formatMoney } from '../../lib/format'
import { useCreateHold, useReleaseHold } from '../booking/api'
import { TariffStep } from '../checkout/TariffStep'
import {
  choiceTotal,
  initialChoice,
  standingBalanced,
  toLines,
  type TariffChoice,
} from '../checkout/tariffs'
import { useAvailability, useSeatMap } from '../seatmap/api'
import { SeatMap } from '../seatmap/SeatMap'
import { EMPTY_SELECTION, selectedCount, selectionReducer } from '../seatmap/selection'
import { useCounterSale, usePrintTickets, type PaymentMethod } from './api'

/**
 * One counter sale on one performance: plan → hold → tariffs and payment → done.
 * Keyed by performance by the parent, so switching performance starts afresh.
 */
export function CounterSale({ performanceId }: { performanceId: string }) {
  const map = useSeatMap(performanceId, 'BOX_OFFICE')
  const availability = useAvailability(performanceId, map.isSuccess)

  if (map.isError) return <p className="p-6 font-bold text-danger">Plan indisponible.</p>
  if (!map.data) return <p className="p-6 font-display text-2xl">Chargement du plan…</p>

  return <Sale performanceId={performanceId} map={map.data} availability={availability.data} />
}

function Sale({
  performanceId,
  map,
  availability,
}: {
  performanceId: string
  map: SeatMapData
  availability: ReturnType<typeof useAvailability>['data']
}) {
  const reducer = useMemo(() => selectionReducer(map.maxSeatsPerOrder * 3), [map.maxSeatsPerOrder])
  const [selection, dispatch] = useReducer(reducer, EMPTY_SELECTION)
  const [hold, setHold] = useState<HoldInfo | null>(null)
  const [done, setDone] = useState<Order | null>(null)
  const createHold = useCreateHold(performanceId, 'BOX_OFFICE')
  const releaseHold = useReleaseHold(performanceId)

  function reset() {
    dispatch({ type: 'clear' })
    setHold(null)
    setDone(null)
  }

  if (done) return <SaleDone order={done} onNext={reset} />

  if (hold) {
    return (
      <SaleCheckout
        hold={hold}
        map={map}
        onBack={() => {
          releaseHold.mutate(hold.id)
          setHold(null)
        }}
        onSold={setDone}
      />
    )
  }

  const count = selectedCount(selection)
  return (
    <div className="grid gap-4 xl:grid-cols-[1fr_16rem]">
      <div
        data-theme="night"
        className="aspect-[10/7] border-2 border-ink-900 bg-night-950 text-paper-100"
      >
        <SeatMap
          map={map}
          availability={availability}
          selection={selection}
          onToggleSeat={(seatId) => {
            dispatch({ type: 'toggleSeat', seatId })
          }}
          onStandingZone={(zoneId) => {
            dispatch({
              type: 'setStanding',
              zoneId,
              quantity: (selection.standing[zoneId] ?? 0) + 1,
            })
          }}
        />
      </div>
      <div className="flex flex-col gap-3">
        <p className="font-display text-3xl">{count} place(s)</p>
        {Object.entries(selection.standing).map(([zoneId, quantity]) => (
          <p key={zoneId} className="text-sm">
            {map.zones.find((z) => z.id === zoneId)?.name} : {quantity} debout{' '}
            <button
              type="button"
              className="underline"
              onClick={() => {
                dispatch({ type: 'setStanding', zoneId, quantity: quantity - 1 })
              }}
            >
              −1
            </button>
          </p>
        ))}
        <Button
          variant="gilt"
          size="lg"
          disabled={count === 0}
          loading={createHold.isPending}
          onClick={() => {
            createHold.mutate(selection, {
              onSuccess: setHold,
              onError: (error) => {
                if (error instanceof ApiError && error.is('SEATS_UNAVAILABLE')) {
                  const gone = Array.isArray(error.problem.seatIds)
                    ? error.problem.seatIds.filter((id): id is string => typeof id === 'string')
                    : []
                  dispatch({ type: 'dropSeats', seatIds: gone })
                }
                toast(error instanceof ApiError ? error.message : 'Réservation impossible', {
                  tone: 'danger',
                })
              },
            })
          }}
        >
          Bloquer ces places
        </Button>
        <Button
          variant="ghost"
          disabled={count === 0}
          onClick={() => {
            dispatch({ type: 'clear' })
          }}
        >
          Effacer
        </Button>
      </div>
    </div>
  )
}

const METHODS: { value: PaymentMethod; label: string }[] = [
  { value: 'CASH', label: 'Espèces' },
  { value: 'CARD_TERMINAL', label: 'Carte (TPE)' },
]

function SaleCheckout({
  hold,
  map,
  onBack,
  onSold,
}: {
  hold: HoldInfo
  map: SeatMapData
  onBack: () => void
  onSold: (order: Order) => void
}) {
  const [choice, setChoice] = useState<TariffChoice>(() => initialChoice(hold, map))
  const [method, setMethod] = useState<PaymentMethod>('CASH')
  const [buyerName, setBuyerName] = useState('')
  const [buyerEmail, setBuyerEmail] = useState('')
  const [reason, setReason] = useState('')
  const sell = useCounterSale()

  const total = choiceTotal(choice, map)
  const invitation = map.zones.some((z) =>
    z.prices.some(
      (p) =>
        p.requiresReason &&
        (Object.values(choice.seats).includes(p.tariffId) ||
          Object.values(choice.standing).some((split) => (split[p.tariffId] ?? 0) > 0)),
    ),
  )
  const error = sell.error instanceof ApiError ? sell.error.message : null

  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
      <div>
        <TariffStep hold={hold} map={map} choice={choice} onChange={setChoice} />
        {invitation ? (
          <div className="mt-4">
            <Input
              label="Motif de l’invitation"
              value={reason}
              onChange={(e) => {
                setReason(e.target.value)
              }}
              placeholder="Presse, partenaire, artiste…"
            />
          </div>
        ) : null}
      </div>
      <aside className="brutal flex flex-col gap-4 bg-surface p-5">
        <p className="text-sm font-bold tracking-[0.12em] uppercase">À encaisser</p>
        <p className="tabular font-display text-5xl">{formatMoney(total, map.currency)}</p>
        {total > 0 ? (
          <div role="group" aria-label="Moyen de paiement" className="flex flex-wrap gap-2">
            {METHODS.map((m) => (
              <Pill
                key={m.value}
                selected={method === m.value}
                onClick={() => {
                  setMethod(m.value)
                }}
              >
                {m.label}
              </Pill>
            ))}
          </div>
        ) : null}
        <Input
          label="Nom du spectateur (facultatif)"
          value={buyerName}
          onChange={(e) => {
            setBuyerName(e.target.value)
          }}
        />
        <Input
          label="Email (facultatif)"
          type="email"
          value={buyerEmail}
          onChange={(e) => {
            setBuyerEmail(e.target.value)
          }}
        />
        {error ? (
          <p role="alert" className="border-2 border-danger p-2 text-sm font-bold text-danger">
            {error}
          </p>
        ) : null}
        <Button
          variant="gilt"
          size="lg"
          disabled={!standingBalanced(choice, hold)}
          loading={sell.isPending}
          onClick={() => {
            sell.mutate(
              {
                holdId: hold.id,
                sale: {
                  ...toLines(choice, invitation ? reason : undefined),
                  method: total > 0 ? method : 'NONE',
                  ...(buyerName ? { buyerName } : {}),
                  ...(buyerEmail ? { buyerEmail } : {}),
                },
              },
              { onSuccess: onSold },
            )
          }}
        >
          {total > 0 ? `Encaisser ${formatMoney(total, map.currency)}` : 'Émettre les invitations'}
        </Button>
        <Button variant="ghost" onClick={onBack}>
          Retour au plan
        </Button>
      </aside>
    </div>
  )
}

function SaleDone({ order, onNext }: { order: Order; onNext: () => void }) {
  const print = usePrintTickets()
  const [printing, setPrinting] = useState(false)
  return (
    <div className="mx-auto max-w-xl py-10 text-center">
      <CheckCircle2 aria-hidden className="mx-auto size-14 text-success" />
      <h2 className="mt-3 font-display text-5xl">Vendu</h2>
      <p className="mt-2 text-lg">
        Commande <strong>{order.reference}</strong> · {order.tickets.length} billet(s) ·{' '}
        {formatMoney(order.totalCents, order.currency)}
      </p>
      <div className="mt-8 flex flex-wrap justify-center gap-3">
        <Button
          variant="gilt"
          size="lg"
          loading={printing}
          leadingIcon={<Printer aria-hidden className="size-5" />}
          onClick={() => {
            setPrinting(true)
            void print(order.id, order.reference)
              .catch(() => {
                toast('Impression impossible', { tone: 'danger' })
              })
              .finally(() => {
                setPrinting(false)
              })
          }}
        >
          Imprimer les billets
        </Button>
        <Button
          variant="secondary"
          size="lg"
          leadingIcon={<RotateCcw aria-hidden className="size-5" />}
          onClick={onNext}
        >
          Vente suivante
        </Button>
      </div>
    </div>
  )
}
