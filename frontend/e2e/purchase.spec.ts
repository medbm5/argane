import { expect, test, type Page } from '@playwright/test'
import { E2E_EMAIL as EMAIL } from './global-setup.ts'

/**
 * Purchase smoke test: sign in → pick a seat → choose a tariff → pay with Stripe's
 * test card → see the confirmed order with its ticket.
 *
 * Uses a Clerk *test* identity (`+clerk_test` emails accept the fixed code 424242 on
 * development instances) and Stripe's published 4242 test card, so nothing real is
 * charged and no inbox is needed.
 */
const API = process.env.E2E_API_URL ?? 'http://127.0.0.1:8099/api'
const CLERK_TEST_CODE = '424242'

interface EventSummary {
  nextPerformance: { id: string; isOnSale: boolean; remaining: number } | null
}

async function performanceOnSale(page: Page): Promise<string> {
  const response = await page.request.get(`${API}/events`)
  expect(response.ok()).toBe(true)
  const events = (await response.json()) as EventSummary[]
  const next = events
    .map((e) => e.nextPerformance)
    .find((p) => p !== null && p.isOnSale && p.remaining > 0)
  if (!next) throw new Error('No performance on sale: run `make seed` first.')
  return next.id
}

async function signIn(page: Page, redirect: string): Promise<void> {
  await page.goto(`/connexion?redirect=${encodeURIComponent(redirect)}`)
  await page.getByLabel(/adresse e-mail|email/i).fill(EMAIL)
  // Wait for Clerk to send the email code (test identities always get 424242).
  const codeSent = page.waitForResponse((r) => r.url().includes('/prepare_first_factor') && r.ok())
  await page.getByRole('button', { name: 'Continuer', exact: true }).click()
  await codeSent
  const code = page.getByRole('textbox', { name: /code/i }).first()
  await code.waitFor()
  await code.pressSequentially(CLERK_TEST_CODE)
  await page.waitForURL((url) => url.pathname === redirect)
}

async function fillStripeCard(page: Page): Promise<void> {
  const stripe = page
    .locator('iframe[title*="paiement" i], iframe[title*="payment" i]')
    .first()
    .contentFrame()
  // Type like a person: Stripe's formatter can drop characters from a bulk fill.
  const card = stripe.getByPlaceholder('1234 1234 1234 1234')
  await card.click()
  await card.pressSequentially('4242424242424242', { delay: 30 })
  await expect(card).toHaveValue('4242 4242 4242 4242')
  const expiry = stripe.getByPlaceholder(/MM \/ AA|MM \/ YY/)
  await expiry.pressSequentially('1234', { delay: 30 })
  await expect(expiry).toHaveValue('12 / 34')
  await stripe.getByPlaceholder('CVC').pressSequentially('123', { delay: 30 })
  const country = stripe.locator('select[name="country"]')
  if (await country.isVisible()) await country.selectOption('MA')
}

test('a customer buys a seat with the Stripe test card', async ({ page }) => {
  const performanceId = await performanceOnSale(page)
  const seatPage = `/representations/${performanceId}`

  // Seats render as free until live availability arrives; wait for it before picking.
  const availability = page.waitForResponse(
    (r) => r.url().includes(`/performances/${performanceId}/availability`) && r.status() === 200,
  )
  await signIn(page, seatPage)
  await availability

  // Pick the first free standard seat, the keyboard way (focus + Space).
  const free = page
    .locator('[role="checkbox"][aria-disabled="false"][data-seat-id]:not([aria-label*="fauteuil"])')
    .first()
  await free.waitFor()
  const seatId = await free.getAttribute('data-seat-id')
  const seat = page.locator(`[data-seat-id="${String(seatId)}"]`)
  await seat.focus()
  await page.keyboard.press('Space')
  await expect(seat).toHaveAttribute('aria-checked', 'true')
  await page.getByRole('button', { name: 'Réserver ces places' }).click()
  // The hold is taken (countdown starts), then go to payment.
  await expect(page.getByText('Places réservées pour vous')).toBeVisible()
  await page.getByRole('button', { name: 'Passer au paiement' }).click()

  // Checkout: default full-price tariff, then pay.
  await page.waitForURL(/\/commande\//)
  await page.getByRole('button', { name: 'Continuer vers le paiement' }).click()
  await fillStripeCard(page)
  await page.getByRole('button', { name: /^Payer/ }).click()

  await page.waitForURL(/\/commandes\/.+\/confirmation/)
  await expect(page.getByRole('heading', { name: 'C’est réservé !' })).toBeVisible({
    timeout: 60_000,
  })
  await expect(page.getByText(/^Place /)).toBeVisible()
})
