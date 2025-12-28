// @ts-check
const base = require('@playwright/test').test;
const fs = require('fs');
const path = require('path');

/**
 * Test file paths (isolated from development files)
 * These match what install.php uses when APP_ENV=test
 */
const TEST_CONFIG_PATH = path.join(__dirname, '..', 'config', 'config.test.php');
const TEST_DB_PATH = path.join(__dirname, '..', 'data', 'poll.test.db');

/**
 * Extended test fixture that provides helpers for E2E testing
 */
exports.test = base.extend({
  /**
   * Reset the app to a fresh state before each test
   * Only deletes TEST config and database files (not development files)
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

    // Clean up after test (optional - comment out to inspect state after failures)
    // if (fs.existsSync(TEST_CONFIG_PATH)) {
    //   fs.unlinkSync(TEST_CONFIG_PATH);
    // }
    // if (fs.existsSync(TEST_DB_PATH)) {
    //   fs.unlinkSync(TEST_DB_PATH);
    // }
  }, { auto: false }],

  /**
   * Install the app with a sysadmin account
   * Returns the sysadmin credentials
   */
  installedApp: [async ({ page, freshInstall }, use) => {
    // Clean up first (only test files)
    if (fs.existsSync(TEST_CONFIG_PATH)) {
      fs.unlinkSync(TEST_CONFIG_PATH);
    }
    if (fs.existsSync(TEST_DB_PATH)) {
      fs.unlinkSync(TEST_DB_PATH);
    }

    const sysadmin = {
      email: 'admin@test.com',
      password: 'testpassword123'
    };

    // Go through install flow
    await page.goto('/');

    // Should redirect to install.php
    await page.waitForURL(/install\.php/);

    // Welcome step - click start
    await page.click('text=Start Installation');

    // Database step - use SQLite defaults, just submit
    await page.click('button:has-text("Continue")');

    // Sysadmin step - fill in credentials
    await page.fill('input[name="email"]', sysadmin.email);
    await page.fill('input[name="password"]', sysadmin.password);
    await page.click('button:has-text("Complete Installation")');

    // Should be on complete page
    await page.waitForURL(/step=complete/);

    await use(sysadmin);
  }, { auto: false }],
});

exports.expect = require('@playwright/test').expect;
