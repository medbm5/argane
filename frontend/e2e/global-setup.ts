import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

/**
 * Makes sure the Clerk *test* identity used by the smoke tests exists on the
 * development instance. Reads CLERK_SECRET_KEY from the environment or from the
 * git-ignored backend/.env.local; refuses live keys.
 */
export const E2E_EMAIL = process.env.E2E_CLERK_EMAIL ?? 'client+clerk_test@example.com'

function secretKey(): string {
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

export default async function globalSetup(): Promise<void> {
  const key = secretKey()
  if (!key.startsWith('sk_test_'))
    throw new Error('e2e tests only run against a Clerk development instance.')
  if (!E2E_EMAIL.includes('+clerk_test@'))
    throw new Error('E2E_CLERK_EMAIL must be a +clerk_test address.')

  const api = 'https://api.clerk.com/v1'
  const headers = { Authorization: `Bearer ${key}`, 'Content-Type': 'application/json' }
  const found = await fetchRetry(`${api}/users?email_address=${encodeURIComponent(E2E_EMAIL)}`, {
    headers,
  })
  if (!found.ok) throw new Error(`Clerk user lookup failed: ${String(found.status)}`)
  if (((await found.json()) as unknown[]).length > 0) return

  const created = await fetchRetry(`${api}/users`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      email_address: [E2E_EMAIL],
      first_name: 'Client',
      last_name: 'Test',
      skip_password_requirement: true,
    }),
  })
  if (!created.ok)
    throw new Error(`Clerk user creation failed: ${String(created.status)} ${await created.text()}`)
}
