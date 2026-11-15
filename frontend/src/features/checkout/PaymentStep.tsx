import { Elements, PaymentElement, useElements, useStripe } from '@stripe/react-stripe-js'
import { Lock } from 'lucide-react'
import { useMemo, useState, type SyntheticEvent } from 'react'
import { useNavigate } from 'react-router'
import { Button } from '../../design-system/primitives/Button'
import { formatMoney } from '../../lib/format'
import { getStripe, stripeAppearance } from './stripe'

function PayForm({ orderId, amount }: { orderId: string; amount: string }) {
  const stripe = useStripe()
  const elements = useElements()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function pay(e: SyntheticEvent<HTMLFormElement>) {
    e.preventDefault()
    if (!stripe || !elements) return
    setBusy(true)
    setError(null)
    const confirmation = `/commandes/${orderId}/confirmation`
    const result = await stripe.confirmPayment({
      elements,
      confirmParams: { return_url: window.location.origin + confirmation },
      // Cards without 3-D Secure confirm in place; others come back via return_url.
      redirect: 'if_required',
    })
    setBusy(false)
    if (result.error) {
      setError(result.error.message ?? 'Le paiement n’a pas abouti.')
      return
    }
    void navigate(confirmation)
  }

  return (
    <form onSubmit={(e) => void pay(e)} className="space-y-5">
      <PaymentElement options={{ layout: 'tabs' }} />
      {error ? (
        <p role="alert" className="border-2 border-danger bg-pop-pink/20 p-3 font-bold">
          {error}
        </p>
      ) : null}
      <Button
        type="submit"
        variant="gilt"
        size="lg"
        block
        loading={busy}
        disabled={!stripe}
        leadingIcon={<Lock aria-hidden className="size-4" />}
      >
        Payer {amount}
      </Button>
      <p className="text-center text-xs text-muted">
        Paiement sécurisé par Stripe. Mode test : carte 4242 4242 4242 4242, date future, CVC
        quelconque.
      </p>
    </form>
  )
}

/** Stripe Payment Element themed with our tokens. */
export function PaymentStep({
  clientSecret,
  orderId,
  totalCents,
  currency,
}: {
  clientSecret: string
  orderId: string
  totalCents: number
  currency: string
}) {
  const stripePromise = getStripe()
  const options = useMemo(
    () => ({ clientSecret, appearance: stripeAppearance(), locale: 'fr' as const }),
    [clientSecret],
  )

  if (!stripePromise) {
    return (
      <p className="border-2 border-fg bg-pop-pink/20 p-4 font-bold">
        Le paiement en ligne n’est pas configuré (clé Stripe manquante).
      </p>
    )
  }

  return (
    <Elements stripe={stripePromise} options={options}>
      <PayForm orderId={orderId} amount={formatMoney(totalCents, currency)} />
    </Elements>
  )
}
