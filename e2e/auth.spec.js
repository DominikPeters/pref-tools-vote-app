// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Authentication', () => {
  test('sysadmin can log in and access dashboard', async ({ page, installedApp }) => {
    // Go to login page
    await page.goto('/login');

    // Fill in sysadmin credentials from installedApp fixture
    await page.fill('input[name="email"]', installedApp.email);
    await page.fill('input[name="password"]', installedApp.password);

    // Click login (need to make sure we're on login tab, not register)
    await page.click('button.auth-tab:has-text("Login")');
    await page.click('#loginForm button[type="submit"]');

    // Should redirect to dashboard
    await expect(page).toHaveURL('/dashboard');
    await expect(page.locator('h1')).toContainText('Dashboard');

    // Should show user email in header
    await expect(page.locator('.user-email')).toContainText(installedApp.email);

    // Should show sysadmin link
    await expect(page.locator('nav a:has-text("Sysadmin")')).toBeVisible();
  });

  test('sysadmin can access sysadmin dashboard', async ({ page, installedApp }) => {
    // Log in first
    await page.goto('/login');
    await page.click('button.auth-tab:has-text("Login")');
    await page.fill('input[name="email"]', installedApp.email);
    await page.fill('input[name="password"]', installedApp.password);
    await page.click('#loginForm button[type="submit"]');
    await expect(page).toHaveURL('/dashboard');

    // Navigate to sysadmin
    await page.click('nav a:has-text("Sysadmin")');

    // Should be on sysadmin dashboard
    await expect(page).toHaveURL('/sysadmin');
    await expect(page.locator('h1')).toContainText('Sysadmin Dashboard');

    // Should show stats
    await expect(page.locator('.stat-card')).toHaveCount(4);
  });

  test('regular user cannot see sysadmin link', async ({ page, installedApp }) => {
    // Register a new regular user
    await page.goto('/login');
    await page.click('button.auth-tab:has-text("Register")');
    await page.fill('#registerForm input[name="email"]', 'user@example.com');
    await page.fill('#registerForm input[name="password"]', 'userpassword123');
    await page.click('#registerForm button[type="submit"]');

    // Should be on dashboard
    await expect(page).toHaveURL('/dashboard');

    // Should NOT show sysadmin link
    await expect(page.locator('nav a:has-text("Sysadmin")')).not.toBeVisible();
  });

  test('regular user cannot access sysadmin pages', async ({ page, installedApp }) => {
    // Register a new regular user
    await page.goto('/login');
    await page.click('button.auth-tab:has-text("Register")');
    await page.fill('#registerForm input[name="email"]', 'user2@example.com');
    await page.fill('#registerForm input[name="password"]', 'userpassword123');
    await page.click('#registerForm button[type="submit"]');
    await expect(page).toHaveURL('/dashboard');

    // Try to access sysadmin directly
    await page.goto('/sysadmin');

    // Should see access denied
    await expect(page.locator('h1')).toContainText('Access Denied');
  });

  test('user can log out', async ({ page, installedApp }) => {
    // Log in
    await page.goto('/login');
    await page.click('button.auth-tab:has-text("Login")');
    await page.fill('input[name="email"]', installedApp.email);
    await page.fill('input[name="password"]', installedApp.password);
    await page.click('#loginForm button[type="submit"]');
    await expect(page).toHaveURL('/dashboard');

    // Log out
    await page.click('button:has-text("Log Out")');

    // Should be redirected to login or home
    // After logout, visiting dashboard should redirect to login
    await page.goto('/dashboard');
    await expect(page).toHaveURL('/login');
  });
});
