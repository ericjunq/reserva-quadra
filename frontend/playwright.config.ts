import { defineConfig } from '@playwright/test';

const PORTA = 4300;

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env['CI'],
  retries: process.env['CI'] ? 1 : 0,
  reporter: process.env['CI'] ? 'github' : 'list',
  expect: { timeout: 10_000 },
  use: {
    baseURL: `http://localhost:${PORTA}`,
    channel: 'chrome',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'computador', use: { viewport: { width: 1280, height: 900 } } },
    { name: 'celular', use: { viewport: { width: 400, height: 800 }, isMobile: true } },
  ],
  webServer: {
    command: `npx ng serve --configuration production --port ${PORTA}`,
    url: `http://localhost:${PORTA}`,
    reuseExistingServer: false,
    timeout: 180_000,
  },
});
