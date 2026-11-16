import { BOX_OFFICE, CUSTOMER, ORGANIZER, ensureClerkTestUser } from './support.ts'

/** Makes sure the Clerk test identities (spectator, organizer, box-office staff) exist. */
export default async function globalSetup(): Promise<void> {
  await ensureClerkTestUser(CUSTOMER)
  await ensureClerkTestUser(ORGANIZER)
  await ensureClerkTestUser(BOX_OFFICE)
}
