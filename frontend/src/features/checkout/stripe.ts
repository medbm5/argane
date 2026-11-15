import { loadStripe, type Appearance, type Stripe } from '@stripe/stripe-js'

const publishableKey = import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY ?? ''

let stripePromise: Promise<Stripe | null> | null = null

/** Stripe.js loads once, only when a payment step is shown. Null when not configured. */
export function getStripe(): Promise<Stripe | null> | null {
  if (!publishableKey) return null
  stripePromise ??= loadStripe(publishableKey)
  return stripePromise
}

export const paymentsConfigured = publishableKey !== ''

/**
 * Stripe Elements styled with our design tokens. Stripe needs concrete values, so the
 * tokens are read from the CSS custom properties at runtime (the mural palette today,
 * whatever the theme says tomorrow).
 */
export function stripeAppearance(): Appearance {
  const css = getComputedStyle(document.documentElement)
  const token = (name: string, fallback: string) => css.getPropertyValue(name).trim() || fallback
  const ink = token('--ink-900', '#15121e')
  const paper = token('--paper-50', '#fbf8f1')
  const red = token('--pop-red', '#e8432e')
  const blue = token('--pop-blue', '#2f4fe0')
  const yellow = token('--pop-yellow', '#ffc21a')

  return {
    theme: 'flat',
    variables: {
      colorPrimary: ink,
      colorBackground: paper,
      colorText: ink,
      colorDanger: red,
      colorTextSecondary: token('--sepia-800', '#4a4556'),
      fontFamily: '"Space Grotesk", system-ui, sans-serif',
      fontSizeBase: '16px',
      borderRadius: '0px',
      spacingUnit: '4px',
      focusBoxShadow: `4px 4px 0 ${blue}`,
      focusOutline: 'none',
    },
    rules: {
      '.Input': {
        border: `2px solid ${ink}`,
        boxShadow: 'none',
        padding: '12px',
      },
      '.Input:focus': { boxShadow: `4px 4px 0 ${blue}` },
      '.Input--invalid': { boxShadow: `3px 3px 0 ${red}`, borderColor: red },
      '.Label': {
        fontWeight: '700',
        textTransform: 'uppercase',
        letterSpacing: '0.12em',
        fontSize: '12px',
      },
      '.Tab': { border: `2px solid ${ink}`, boxShadow: 'none' },
      '.Tab--selected': { backgroundColor: yellow, color: ink, boxShadow: `3px 3px 0 ${ink}` },
    },
  }
}
