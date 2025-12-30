// @ts-check
const { test: setup, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

/**
 * Test file paths (isolated from development files)
 * These match what install.php uses when APP_ENV=test
 */
const TEST_CONFIG_PATH = path.join(__dirname, '..', 'config', 'config.test.php');
const TEST_DB_PATH = path.join(__dirname, '..', 'data', 'poll.test.db');
const STATE_DIR = path.join(__dirname, 'state');
const ADMIN_STATE_PATH = path.join(STATE_DIR, 'admin.json');

/**
 * Credentials for the sysadmin account created during setup.
 * These are shared with fixtures.js for consistency.
 */
const SYSADMIN = {
  name: 'Test Admin',
  email: 'admin@example.com',
  password: 'testpassword123'
};

setup('install app and create admin session', async ({ page }) => {
  // Ensure state directory exists
  if (!fs.existsSync(STATE_DIR)) {
    fs.mkdirSync(STATE_DIR, { recursive: true });
  }

  // Clean up any existing test files for a fresh install
  if (fs.existsSync(TEST_CONFIG_PATH)) {
    fs.unlinkSync(TEST_CONFIG_PATH);
  }
  if (fs.existsSync(TEST_DB_PATH)) {
    fs.unlinkSync(TEST_DB_PATH);
  }

  // Step 1: Navigate to install page
  await page.goto('/install.php');
  await expect(page.locator('h1')).toContainText('Welcome');

  // Step 2: Start installation
  await page.click('text=Start Installation');

  // Step 3: Database setup (use SQLite defaults)
  await expect(page.locator('h1')).toContainText('Database Setup');
  await page.click('button:has-text("Continue")');

  // Step 4: Create sysadmin account
  await expect(page.locator('h1')).toContainText('Sysadmin Account');
  await page.fill('input[name="name"]', SYSADMIN.name);
  await page.fill('input[name="email"]', SYSADMIN.email);
  await page.fill('input[name="password"]', SYSADMIN.password);
  await page.click('button:has-text("Complete Installation")');

  // Step 5: Verify installation complete
  await expect(page.locator('h1')).toContainText('Installation Complete');

  // Step 6: Log in as admin and save storage state
  await page.goto('/login');
  await page.fill('#loginForm input[name="email"]', SYSADMIN.email);
  await page.fill('#loginForm input[name="password"]', SYSADMIN.password);
  await page.click('#loginForm button[type="submit"]');
  await expect(page).toHaveURL('/dashboard');

  // Save the authenticated state for other tests to reuse
  await page.context().storageState({ path: ADMIN_STATE_PATH });
});
