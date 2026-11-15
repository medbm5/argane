import { defineConfig, devices } from '@playwright/test'

/**
 * End-to-end smoke tests. They drive the real stack (Vite + Symfony + Clerk dev instance +
 * Stripe test mode), so they are not part of `npm test`; run them with `npm run e2e`
 * once `make up` and the dev servers are running. See INSTALL.md.
 */
export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.ts',
  timeout: 120_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:5173',
    locale: 'fr-FR',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chrome',
      // The installed Google Chrome by default (no browser download); set
      // E2E_BROWSER_CHANNEL=chromium after `npx playwright install chromium` in CI.
      use: { ...devices['Desktop Chrome'], channel: process.env.E2E_BROWSER_CHANNEL ?? 'chrome' },
    },
  ],
})
