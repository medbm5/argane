import { ArrowLeft } from 'lucide-react'
import { useCallback, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { Button } from '../design-system/primitives/Button'
import { useHold } from '../features/booking/api'
import { HoldCountdown } from '../features/booking/HoldCountdown'
import { usePerformance } from '../features/catalog/api'
import { useCheckout } from '../features/checkout/api'
import { PaymentStep } from '../features/checkout/PaymentStep'
import { TariffStep } from '../features/checkout/TariffStep'
import {
  choiceTotal,
  initialChoice,
  needsProof,
  standingBalanced,
  toLines,
  type TariffChoice,
} from '../features/checkout/tariffs'
import { SectionTitle } from '../features/catalog/landing/LandingGrid'
import { useSeatMap } from '../features/seatmap/api'
import { ApiError } from '../lib/api'
import type { Checkout, HoldInfo, SeatMap } from '../lib/api/types'
import { formatLongDate, formatMoney, formatTime } from '../lib/format'

/** /commande/:holdId — tariffs, then card payment, against the hold's countdown. */
export function CheckoutPage() {
  const { holdId = '' } = useParams()
  const hold = useHold(holdId)
  const map = useSeatMap(hold.data?.performanceId ?? '')
  const performance = usePerformance(hold.data?.performanceId ?? '')

  if (hold.isError) {
    return (
      <Notice
        title="Réservation introuvable"
        text="Cette réservation n’existe pas ou ne vous appartient pas."
      />
    )
  }
  if (!hold.data || !map.data || !performance.data) {
    return <p className="px-4 py-20 text-center font-display text-3xl">Préparation…</p>
  }
  if (hold.data.status !== 'ACTIVE') {
    return (
      <Notice
        title="Temps écoulé"
        text="Vos places ont été libérées. Elles sont peut-être encore disponibles."
        to={`/representations/${hold.data.performanceId}`}
      />
    )
  }

  return (
    <Checkout
      hold={hold.data}
      map={map.data}
      title={performance.data.eventTitle}
      when={`${formatLongDate(performance.data.startsAt)} · ${formatTime(performance.data.startsAt)}`}
    />
  )
}

function Notice({ title, text, to = '/programme' }: { title: string; text: string; to?: string }) {
  return (
    <div className="mx-auto max-w-xl px-4 py-20 text-center">
      <h1 className="font-display text-5xl">{title}</h1>
      <p className="mt-4">{text}</p>
      <Button asChild variant="gilt" size="lg" className="mt-8">
        <Link to={to}>Choisir des places</Link>
      </Button>
    </div>
  )
}

function Checkout({
  hold,
  map,
  title,
  when,
}: {
  hold: HoldInfo
  map: SeatMap
  title: string
  when: string
}) {
  const navigate = useNavigate()
  const [choice, setChoice] = useState<TariffChoice>(() => initialChoice(hold, map))
  const [checkout, setCheckout] = useState<Checkout | null>(null)
  const [expired, setExpired] = useState(false)
  const submit = useCheckout(hold.id)
  const onExpire = useCallback(() => {
    setExpired(true)
  }, [])

  const total = choiceTotal(choice, map)
  const balanced = standingBalanced(choice, hold)
  const error = submit.error instanceof ApiError ? submit.error.message : null

  function confirmTariffs() {
    submit.mutate(toLines(choice), {
      onSuccess: (result) => {
        if (!result.clientSecret) {
          void navigate(`/commandes/${result.order.id}/confirmation`)
          return
        }
        setCheckout(result)
      },
    })
  }

  if (expired) {
    return (
      <Notice
        title="Temps écoulé"
        text="Vos places ont été libérées après 10 minutes."
        to={`/representations/${hold.performanceId}`}
      />
    )
  }

  return (
    <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-8 lg:grid-cols-[1fr_22rem]">
      <div>
        <Link
          to={`/representations/${hold.performanceId}`}
          className="inline-flex items-center gap-2 text-sm font-bold tracking-[0.12em] uppercase hover:text-accent"
        >
          <ArrowLeft aria-hidden className="size-4" /> Plan de salle
        </Link>
        <div className="mt-6">
          <SectionTitle id="checkout-title" kicker={when} bar="bg-pop-yellow">
            {title}
          </SectionTitle>
        </div>

        <ol className="mt-8 flex gap-3 text-sm font-bold tracking-[0.12em] uppercase">
          <li className={checkout ? 'text-muted' : 'text-fg'}>1 · Tarifs</li>
          <li aria-hidden>→</li>
          <li className={checkout ? 'text-fg' : 'text-muted'}>2 · Paiement</li>
        </ol>

        <section aria-label={checkout ? 'Paiement' : 'Tarifs'} className="mt-6">
          {checkout?.clientSecret ? (
            <>
              <PaymentStep
                clientSecret={checkout.clientSecret}
                orderId={checkout.order.id}
                totalCents={checkout.order.totalCents}
                currency={checkout.order.currency}
              />
              <Button
                variant="ghost"
                className="mt-4"
                onClick={() => {
                  setCheckout(null)
                }}
              >
                Modifier les tarifs
              </Button>
            </>
          ) : (
            <>
              <TariffStep hold={hold} map={map} choice={choice} onChange={setChoice} />
              {needsProof(choice, map) ? (
                <p className="mt-4 border-2 border-fg bg-pop-yellow p-3 text-sm font-bold text-ink-900">
                  Tarif réduit : un justificatif (étudiant, moins de 26 ans, demandeur d’emploi)
                  vous sera demandé à l’entrée.
                </p>
              ) : null}
              {error ? (
                <p role="alert" className="mt-4 border-2 border-danger p-3 font-bold text-danger">
                  {error}
                </p>
              ) : null}
            </>
          )}
        </section>
      </div>

      <aside className="flex flex-col gap-4 lg:sticky lg:top-24 lg:self-start">
        <HoldCountdown expiresAt={hold.expiresAt} onExpire={onExpire} />
        <div className="brutal bg-surface p-5">
          <p className="text-sm font-bold tracking-[0.12em] uppercase">Total</p>
          <p className="tabular font-display text-5xl">
            {formatMoney(checkout?.order.totalCents ?? total, map.currency)}
          </p>
          <p className="mt-1 text-sm text-muted">
            {hold.seatIds.length + hold.standing.reduce((n, l) => n + l.quantity, 0)} place(s)
            {checkout ? ` · commande ${checkout.order.reference}` : ''}
          </p>
          {!checkout ? (
            <Button
              variant="gilt"
              size="lg"
              block
              className="mt-5"
              disabled={!balanced}
              loading={submit.isPending}
              onClick={confirmTariffs}
            >
              {total === 0 ? 'Confirmer' : 'Continuer vers le paiement'}
            </Button>
          ) : null}
        </div>
      </aside>
    </div>
  )
}
