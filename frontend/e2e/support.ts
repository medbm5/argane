import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { expect, type Page } from '@playwright/test'

/**
 * Shared helpers for the browser tests. Identities are Clerk *test* emails: on a
 * development instance `+clerk_test` addresses accept the fixed code 424242, so no
 * inbox is needed. They match the seeded demo accounts (INSTALL.md).
 */
export const API = process.env.E2E_API_URL ?? 'http://127.0.0.1:8099/api'
const CLERK_TEST_CODE = '424242'

export const CUSTOMER = {
  email: process.env.E2E_CLERK_EMAIL ?? 'client+clerk_test@example.com',
  firstName: 'Client',
}
export const ORGANIZER = { email: 'organisateur+clerk_test@example.com', firstName: 'Organisateur' }

function clerkSecretKey(): string {
  if (process.env.CLERK_SECRET_KEY) return process.env.CLERK_SECRET_KEY
  try {
    const env = readFileSync(resolve(import.meta.dirname, '../../backend/.env.local'), 'utf8')
    const match = /^CLERK_SECRET_KEY=["']?([^"'\r\n]+)/m.exec(env)
    if (match?.[1]) return match[1]
  } catch {
    // fall through
  }
  throw new Error('CLERK_SECRET_KEY is required for e2e tests (env or backend/.env.local).')
}

/** fetch with a few retries: a flaky network should not fail the whole run. */
async function fetchRetry(url: string, init: RequestInit, attempts = 4): Promise<Response> {
  for (let i = 1; ; i++) {
    try {
      return await fetch(url, init)
    } catch (error) {
      if (i >= attempts) throw error
      await new Promise((r) => setTimeout(r, 1000 * i))
    }
  }
}

/** Creates the Clerk test user on the development instance if it does not exist yet. */
export async function ensureClerkTestUser(user: {
  email: string
  firstName: string
}): Promise<void> {
  const key = clerkSecretKey()
  if (!key.startsWith('sk_test_'))
    throw new Error('e2e tests only run against a Clerk development instance.')
  if (!user.email.includes('+clerk_test@'))
    throw new Error(`${user.email} is not a +clerk_test address.`)

  const api = 'https://api.clerk.com/v1'
  const headers = { Authorization: `Bearer ${key}`, 'Content-Type': 'application/json' }
  const found = await fetchRetry(`${api}/users?email_address=${encodeURIComponent(user.email)}`, {
    headers,
  })
  if (!found.ok) throw new Error(`Clerk user lookup failed: ${String(found.status)}`)
  if (((await found.json()) as unknown[]).length > 0) return

  const created = await fetchRetry(`${api}/users`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      email_address: [user.email],
      first_name: user.firstName,
      last_name: 'Test',
      skip_password_requirement: true,
    }),
  })
  if (!created.ok)
    throw new Error(`Clerk user creation failed: ${String(created.status)} ${await created.text()}`)
}

interface EventSummary {
  nextPerformance: { id: string; isOnSale: boolean; remaining: number } | null
}

export async function performanceOnSale(page: Page): Promise<string> {
  const response = await page.request.get(`${API}/events`)
  expect(response.ok()).toBe(true)
  const events = (await response.json()) as EventSummary[]
  const next = events
    .map((e) => e.nextPerformance)
    .find((p) => p !== null && p.isOnSale && p.remaining > 0)
  if (!next) throw new Error('No performance on sale: run `make fixtures` first.')
  return next.id
}

/** Signs in through the real Clerk widget and lands on `redirect`. */
export async function signIn(page: Page, email: string, redirect: string): Promise<void> {
  await page.goto(`/connexion?redirect=${encodeURIComponent(redirect)}`)
  await page.getByLabel(/adresse e-mail|email/i).fill(email)
  // Wait for Clerk to send the email code (test identities always get 424242).
  const codeSent = page.waitForResponse((r) => r.url().includes('/prepare_first_factor') && r.ok())
  await page.getByRole('button', { name: 'Continuer', exact: true }).click()
  await codeSent
  const code = page.getByRole('textbox', { name: /code/i }).first()
  await code.waitFor()
  await code.pressSequentially(CLERK_TEST_CODE)
  await page.waitForURL((url) => url.pathname === redirect)
}
