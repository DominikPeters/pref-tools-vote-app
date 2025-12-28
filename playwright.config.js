// @ts-check
const { defineConfig } = require('@playwright/test');

/**
 * Playwright configuration for Vote App E2E tests
 *
 * Uses APP_ENV=test to isolate test database from development:
 * - Config: config/config.test.php (instead of config.php)
 * - Database: data/poll.test.db (instead of poll.db)
 *
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './e2e',

  // Run tests in parallel
  fullyParallel: true,

  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,

  // Retry on CI only
  retries: process.env.CI ? 2 : 0,

  // Limit parallel workers on CI
  workers: process.env.CI ? 1 : undefined,

  // Reporter to use
  reporter: 'html',

  // Shared settings for all projects
  use: {
    // Base URL for the app (using port 18080 to avoid conflicts)
    baseURL: 'http://localhost:18080',

    // Collect trace when retrying the failed test
    trace: 'on-first-retry',

    // Take screenshot on failure
    screenshot: 'only-on-failure',
  },

  // Configure projects for browsers
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
    // Uncomment to test on more browsers:
    // {
    //   name: 'firefox',
    //   use: { browserName: 'firefox' },
    // },
    // {
    //   name: 'webkit',
    //   use: { browserName: 'webkit' },
    // },
  ],

  // Run local PHP server before starting tests
  // APP_ENV=test ensures separate config and database files
  webServer: {
    command: 'APP_ENV=test php -S localhost:18080 2>/dev/null',
    url: 'http://localhost:18080/install.php',
    reuseExistingServer: !process.env.CI,
    timeout: 30000,
    stdout: 'ignore',
    stderr: 'ignore',
  },
});
