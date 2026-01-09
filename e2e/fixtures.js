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
  name: 'Test Admin',
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

    const name = 'Test User';
    const password = 'testpassword123';

    // Register new user
    await page.goto('/login');
    await page.click('button.auth-tab:has-text("Register")');
    await page.fill('#registerForm input[name="name"]', name);
    await page.fill('#registerForm input[name="email"]', uniqueEmail);
    await page.fill('#registerForm input[name="password"]', password);
    await page.fill('#registerForm input[name="password_confirm"]', password);
    await page.click('#registerForm button[type="submit"]');
    await page.waitForURL('/dashboard');

    await use({
      page,
      context,
      name,
      email: uniqueEmail,
      password
    });

    await context.close();
  }, { scope: 'test' }],

  /**
   * Provides an authenticated API request context that handles CSRF automatically.
   */
  api: [async ({ request, page }, use) => {
    // Helper to get token from page
    const getStoredToken = async () => {
      return await page.evaluate(() => {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      });
    };

    // 1. Get CSRF token from the current page, or visit home if none exists
    let csrfToken = await getStoredToken();
    
    if (!csrfToken) {
      await page.goto('/');
      csrfToken = await getStoredToken();
    }

    // 2. Create a proxy for the request object that adds the token
    const apiProxy = {
      async post(url, options = {}) {
        const token = csrfToken || await getStoredToken();
        return request.post(url, {
          ...options,
          headers: { ...options.headers, 'X-CSRF-TOKEN': token }
        });
      },
      async put(url, options = {}) {
        const token = csrfToken || await getStoredToken();
        return request.put(url, {
          ...options,
          headers: { ...options.headers, 'X-CSRF-TOKEN': token }
        });
      },
      async delete(url, options = {}) {
        const token = csrfToken || await getStoredToken();
        return request.delete(url, {
          ...options,
          headers: { ...options.headers, 'X-CSRF-TOKEN': token }
        });
      },
      async get(url, options = {}) {
        return request.get(url, options);
      }
    };

    await use(apiProxy);
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

  /**
   * Helper to wait for an email to arrive in Mailhog
   */
  waitForEmail: [async ({}, use) => {
    const helper = async (predicate, timeout = 2000) => {
      const start = Date.now();
      while (Date.now() - start < timeout) {
        try {
          const response = await fetch('http://127.0.0.1:8025/api/v2/messages');
          const data = await response.json();
          const email = data.items.find(predicate);
          if (email) return email;
        } catch (e) {
          // Ignore fetch errors (Mailhog might be temporarily unavailable)
        }
        await new Promise(resolve => setTimeout(resolve, 200));
      }
      return null;
    };
    await use(helper);
  }, { scope: 'test' }],
});

module.exports = { test, expect: base.expect, SYSADMIN };
