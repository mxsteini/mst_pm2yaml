import { defineConfig } from '@playwright/test';

/**
 * E2E-Tests gegen die laufende DDEV-Instanz (Development-Kontext, Backend mit Auto-Login).
 *   cd packages/mst_pm2yaml/Tests/E2E && npm ci && npx playwright test
 *
 * Die Tests legen eigene Datensätze an (Seite /e2e-pm2yaml, uids ab 9001) und räumen sie wieder ab.
 * Sie teilen sich diese Daten, deshalb ein Worker.
 */
export default defineConfig({
  testDir: './tests',
  globalSetup: './global-setup.ts',
  globalTeardown: './global-teardown.ts',
  workers: 1,
  fullyParallel: false,
  retries: 0,
  timeout: 60_000,
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'https://itb3.ddev.site',
    ignoreHTTPSErrors: true,
    channel: process.env.E2E_CHANNEL ?? 'chrome',
    locale: 'de-DE',
    trace: 'retain-on-failure',
  },
});
