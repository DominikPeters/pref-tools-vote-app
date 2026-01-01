// @ts-check
const { defineConfig } = require('@playwright/test');
const path = require('path');

/**
 * Playwright configuration for Vote App E2E tests
 *
 * Project Structure:
 * - setup: Installs the app and saves admin storage state (runs first)
 * - main: Auth, poll, and sysadmin tests with pre-authenticated admin (depends on setup)
 * - install: Isolated installation flow tests (runs separately with single worker)
 *
 * Uses APP_ENV=test to isolate test database from development:
 * - Config: config/config.test.php (instead of config.php)
 * - Database: data/poll.test.db (instead of poll.db)
 *
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './e2e',

  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,

  // Retry on CI only
  retries: process.env.CI ? 2 : 0,

  // Reporter to use
  reporter: 'line',

  // Shared settings for all projects
  use: {
    // Base URL for the app (using port 18080 to avoid conflicts)
    baseURL: 'http://localhost:18080',

    // Collect trace when retrying the failed test
    trace: 'on-first-retry',

    // Take screenshot on failure
    screenshot: 'only-on-failure',
  },

  // Configure projects
  projects: [
    // Teardown project: cleans up test files
    {
      name: 'teardown',
      testMatch: /cleanup\.spec\.js/,
    },

    // Setup project: installs app and creates admin session
    {
      name: 'setup',
      testMatch: /setup\.spec\.js/,
      dependencies: ['install'],
      teardown: 'teardown',
    },

    // Main tests: run with admin storage state pre-loaded
    {
      name: 'main',
      testMatch: /(auth|poll|sysadmin|extended_poll|advanced_sysadmin|specialized_inputs|voter_experience|builder_advanced|poll_thank_you|results_reports|privacy_visibility|access_modes|participatory_budgeting)\.spec\.js$/,
      dependencies: ['setup'],
      use: {
        // Pre-authenticate as admin - tests can override if needed
        storageState: path.join(__dirname, 'e2e', 'state', 'admin.json'),
      },
      // Run tests in parallel - test isolation is handled via fixtures
      fullyParallel: true,
      workers: process.env.CI ? 1 : 4,
    },

    // Install tests: isolated, runs separately with single worker
    {
      name: 'install',
      testMatch: /(install|cleanup)\.spec\.js/,
      // Single worker to avoid race conditions
      fullyParallel: false,
      workers: 1,
    },
  ],

  // Run local PHP server before starting tests
  // APP_ENV=test ensures separate config and database files
  webServer: {
    command: 'APP_ENV=test php -S localhost:18080 2>/dev/null',
    url: 'http://localhost:18080',
    reuseExistingServer: !process.env.CI,
    timeout: 30000,
    stdout: 'ignore',
    stderr: 'ignore',
  },
});
