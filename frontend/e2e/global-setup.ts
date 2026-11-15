import { CUSTOMER, ORGANIZER, ensureClerkTestUser } from './support.ts'

/** Makes sure the Clerk test identities used by the browser tests exist. */
export default async function globalSetup(): Promise<void> {
  await ensureClerkTestUser(CUSTOMER)
  await ensureClerkTestUser(ORGANIZER)
}
