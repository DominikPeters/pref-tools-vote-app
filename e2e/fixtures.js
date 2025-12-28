// @ts-check
const base = require('@playwright/test');
const fs = require('fs');
const path = require('path');

/**
 * Test file paths (isolated from development files)
 * These match what install.php uses when APP_ENV=test
 */
const TEST_CONFIG_PATH = path.join(__dirname, '..', 'config', 'config.test.php');
const TEST_DB_PATH = path.join(__dirname, '..', 'data', 'poll.test.db');
const STATE_DIR = path.join(__dirname, 'state');

/**
 * Sysadmin credentials created during setup.
 * Must match setup.spec.js
 */
const SYSADMIN = {
  email: 'admin@example.com',
  password: 'testpassword123'
};

/**
 * Extended test fixture that provides helpers for E2E testing.
 *
 * Note: The main project automatically loads admin storage state,
 * so `page` is pre-authenticated as admin by default.
 */
const test = base.test.extend({
  /**
   * Provides the sysadmin credentials.
   * Use this when you need to log in manually (e.g., after logout).
   */
  adminCredentials: [async ({}, use) => {
    await use(SYSADMIN);
  }, { option: true }],

  /**
   * Provides a fresh page without any stored authentication state.
   * Use this for testing unauthenticated flows (login, registration, public poll access).
   */
  freshPage: [async ({ browser }, use) => {
    const context = await browser.newContext({ storageState: undefined });
    const page = await context.newPage();
    await use(page);
    await context.close();
  }, { scope: 'test' }],

  /**
   * Generates a unique email address for this test.
   * Uses timestamp + random string to ensure uniqueness across all runs.
   */
  uniqueEmail: [async ({}, use) => {
    const timestamp = Date.now();
    const random = Math.random().toString(36).slice(2, 8);
    const email = `test-${timestamp}-${random}@example.com`;
    await use(email);
  }, { scope: 'test' }],

  /**
   * Creates a fresh user account and provides authenticated page + credentials.
   * Useful for tests that need a non-admin user.
   */
  userAccount: [async ({ browser, uniqueEmail }, use) => {
    const context = await browser.newContext({ storageState: undefined });
    const page = await context.newPage();

    const password = 'testpassword123';

    // Register new user
    await page.goto('/login');
    await page.click('button.auth-tab:has-text("Register")');
    await page.fill('#registerForm input[name="email"]', uniqueEmail);
    await page.fill('#registerForm input[name="password"]', password);
    await page.fill('#registerForm input[name="password_confirm"]', password);
    await page.click('#registerForm button[type="submit"]');
    await page.waitForURL('/dashboard');

    await use({
      page,
      context,
      email: uniqueEmail,
      password
    });

    await context.close();
  }, { scope: 'test' }],

  /**
   * Resets the app to a fresh state (deletes config and database).
   * WARNING: Only use in the install project - this breaks other tests!
   */
  freshInstall: [async ({}, use) => {
    // Clean up before test
    if (fs.existsSync(TEST_CONFIG_PATH)) {
      fs.unlinkSync(TEST_CONFIG_PATH);
    }
    if (fs.existsSync(TEST_DB_PATH)) {
      fs.unlinkSync(TEST_DB_PATH);
    }
    await use(undefined);
  }, { auto: false, scope: 'test' }],
});

module.exports = { test, expect: base.expect, SYSADMIN };
