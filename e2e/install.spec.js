// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Installation Flow', () => {
  test('completes full installation process', async ({ page, freshInstall }) => {
    // Navigate to app - should redirect to installer
    await page.goto('/');
    await expect(page).toHaveURL(/install\.php/);

    // Welcome step
    await expect(page.locator('h1')).toContainText('Welcome');
    await page.click('text=Start Installation');

    // Database step
    await expect(page.locator('h1')).toContainText('Database Setup');

    // SQLite should be selected by default
    await expect(page.locator('input[value="sqlite"]')).toBeChecked();

    // Submit with defaults
    await page.click('button:has-text("Continue")');

    // Sysadmin step
    await expect(page.locator('h1')).toContainText('Sysadmin Account');

    // Fill in sysadmin credentials
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'securepassword123');
    await page.click('button:has-text("Complete Installation")');

    // Complete step
    await expect(page.locator('h1')).toContainText('Installation Complete');

    // Click to go to app
    await page.click('text=Go to App');

    // Should be on homepage now
    await expect(page).toHaveURL('/');
    await expect(page.locator('h1')).toContainText('Create and Share Polls');
  });

  test('validates sysadmin email is required', async ({ page, freshInstall }) => {
    await page.goto('/');
    await page.click('text=Start Installation');
    await page.click('button:has-text("Continue")');

    // Try to submit without email
    await page.fill('input[name="password"]', 'testpassword123');
    await page.click('button:has-text("Complete Installation")');

    // Should show error (HTML5 validation or server-side)
    // The form has required attribute, so it won't submit
    await expect(page).toHaveURL(/step=admin/);
  });

  test('validates password minimum length', async ({ page, freshInstall }) => {
    await page.goto('/');
    await page.click('text=Start Installation');
    await page.click('button:has-text("Continue")');

    // Fill with short password
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'short');
    await page.click('button:has-text("Complete Installation")');

    // Should show error
    await expect(page.locator('.error')).toContainText('8 characters');
  });

  test('redirects to home when already installed', async ({ page, installedApp }) => {
    // installedApp fixture already installed the app
    // Now try to access install.php directly
    await page.goto('/install.php');

    // Should redirect to homepage
    await expect(page).toHaveURL('/');
  });
});
