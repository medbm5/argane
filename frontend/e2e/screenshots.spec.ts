import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'
import { expect, test, type Page } from '@playwright/test'
import { API, CUSTOMER, ORGANIZER, performanceOnSale, signIn } from './support.ts'

/**
 * README screenshots (`npm run screenshots`), from the seeded demo data.
 * Not a test of behaviour: it only fails if a page does not render.
 */
const OUT = resolve(import.meta.dirname, '../../docs/screenshots')
mkdirSync(OUT, { recursive: true })

test.use({ viewport: { width: 1440, height: 900 }, contextOptions: { reducedMotion: 'reduce' } })

async function shot(page: Page, name: string, fullPage = false): Promise<void> {
  // Pages poll (availability, scanner stats), so the network never goes idle:
  // wait for fonts and let the last paint settle instead.
  await page.evaluate('document.fonts.ready.then(() => true)')
  await page.waitForTimeout(800)
  await page.screenshot({ path: `${OUT}/${name}.jpg`, type: 'jpeg', quality: 85, fullPage })
}

test('public pages', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('link', { name: /^À l’affiche/ }).first()).toBeVisible()
  await shot(page, '01-home')

  await page.goto('/programme')
  await expect(page.getByRole('heading', { name: 'Programmation' })).toBeVisible()
  await shot(page, '02-programme')

  const events = (await (await page.request.get(`${API}/events`)).json()) as { slug: string }[]
  await page.goto(`/spectacles/${events[0]?.slug ?? ''}`)
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await shot(page, '03-event')

  await page.goto(`/representations/${await performanceOnSale(page)}`)
  await expect(page.locator('[data-seat-id]').first()).toBeVisible()
  await shot(page, '04-seat-map')
})

test('customer: tickets with QR code', async ({ page }) => {
  await signIn(page, CUSTOMER.email, '/mes-billets')
  await expect(page.getByRole('heading', { name: 'À venir' })).toBeVisible()
  await shot(page, '05-wallet')

  await page.locator('a[href^="/mes-billets/"]').first().click()
  await expect(page.getByRole('img', { name: /^QR code du billet/ }).first()).toBeVisible()
  await shot(page, '06-ticket-qr')
})

test('staff: studio, box office, check-in', async ({ page }) => {
  await signIn(page, ORGANIZER.email, '/studio')
  await page.getByRole('button', { name: '90 jours' }).click()
  // KPI tiles show "—" until the analytics arrive; wait for an amount.
  await expect(page.getByText(/\d\sMAD/).first()).toBeVisible()
  await shot(page, '07-studio-dashboard')

  await page.goto('/studio/ventes')
  // Seeded orders only: anything bought today (e2e runs, your own test purchases)
  // stays out of a committed image.
  const yesterday = new Date(Date.now() - 86_400_000).toISOString().slice(0, 10)
  await page.getByLabel('Au', { exact: true }).fill(yesterday)
  await expect(page.getByRole('cell', { name: /^ARG-/ }).first()).toBeVisible()
  await shot(page, '08-studio-sales')

  await page.goto('/guichet')
  await page.locator('ul[aria-label="Représentations"] button:not([disabled])').first().click()
  await expect(page.locator('[data-seat-id]').first()).toBeVisible()
  await shot(page, '09-box-office')

  const counts = page.waitForResponse((r) => r.url().includes('/counts') && r.ok())
  await page.goto('/controle')
  await counts
  await shot(page, '10-check-in')
})

test('mobile seat map', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto(`/representations/${await performanceOnSale(page)}`)
  await expect(page.locator('[data-seat-id]').first()).toBeVisible()
  await shot(page, '11-mobile-seat-map')
})
