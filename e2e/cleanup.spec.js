// @ts-check
const { test: cleanup } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

/**
 * Cleanup runs after all tests complete (as teardown for setup project).
 * Removes test database and config to ensure fresh state next run.
 */
const TEST_CONFIG_PATH = path.join(__dirname, '..', 'config', 'config.test.php');
const TEST_DB_PATH = path.join(__dirname, '..', 'data', 'poll.test.db');

cleanup('cleanup test files', async () => {
  // Only cleanup in CI or if explicitly requested
  // In local development, keeping the DB allows for faster re-runs
  if (process.env.CI || process.env.CLEANUP_TEST_DB) {
    if (fs.existsSync(TEST_CONFIG_PATH)) {
      fs.unlinkSync(TEST_CONFIG_PATH);
    }
    if (fs.existsSync(TEST_DB_PATH)) {
      fs.unlinkSync(TEST_DB_PATH);
    }
  }
});
