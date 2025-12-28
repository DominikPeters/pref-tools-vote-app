// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Authentication', () => {
  test('sysadmin can log in and access dashboard', async ({ freshPage, adminCredentials }) => {
    // Use freshPage since we want to test the login flow
    await freshPage.goto('/login');

    // Fill in sysadmin credentials
    await freshPage.fill('input[name="email"]', adminCredentials.email);
    await freshPage.fill('input[name="password"]', adminCredentials.password);

    // Click login (ensure we're on login tab, not register)
    await freshPage.click('button.auth-tab:has-text("Login")');
    await freshPage.click('#loginForm button[type="submit"]');

    // Should redirect to dashboard
    await expect(freshPage).toHaveURL('/dashboard');
    await expect(freshPage.locator('h1')).toContainText('Dashboard');

    // Should show user email in header
    await expect(freshPage.locator('.user-email')).toContainText(adminCredentials.email);

    // Should show sysadmin link
    await expect(freshPage.locator('nav a:has-text("Sysadmin")')).toBeVisible();
  });

  test('sysadmin can access sysadmin dashboard', async ({ page }) => {
    // page is pre-authenticated as admin via storage state
    await page.goto('/sysadmin');

    // Should be on sysadmin dashboard
    await expect(page).toHaveURL('/sysadmin');
    await expect(page.locator('h1')).toContainText('Sysadmin Dashboard');

    // Should show stats
    await expect(page.locator('.stat-card')).toHaveCount(4);
  });

  test('regular user cannot see sysadmin link', async ({ userAccount }) => {
    // userAccount fixture creates and logs in a fresh regular user
    const { page } = userAccount;

    // Should be on dashboard
    await expect(page).toHaveURL('/dashboard');

    // Should NOT show sysadmin link
    await expect(page.locator('nav a:has-text("Sysadmin")')).not.toBeVisible();
  });

  test('regular user cannot access sysadmin pages', async ({ userAccount }) => {
    const { page } = userAccount;

    // Try to access sysadmin directly
    await page.goto('/sysadmin');

    // Should see access denied
    await expect(page.locator('h1')).toContainText('Access Denied');
  });

  test('user can log out', async ({ freshPage, adminCredentials }) => {
    // Log in with fresh page (don't use shared admin session to avoid invalidating it)
    await freshPage.goto('/login');
    await freshPage.click('button.auth-tab:has-text("Login")');
    await freshPage.fill('#loginForm input[name="email"]', adminCredentials.email);
    await freshPage.fill('#loginForm input[name="password"]', adminCredentials.password);
    await freshPage.click('#loginForm button[type="submit"]');
    await expect(freshPage).toHaveURL('/dashboard');

    // Log out
    await freshPage.click('button:has-text("Log Out")');

    // Verify logged out by trying to access dashboard
    await freshPage.goto('/dashboard');
    await expect(freshPage).toHaveURL('/login');
  });

  test('shows error for invalid credentials', async ({ freshPage }) => {
    await freshPage.goto('/login');

    await freshPage.click('button.auth-tab:has-text("Login")');
    await freshPage.fill('#loginForm input[name="email"]', 'wrong@example.com');
    await freshPage.fill('#loginForm input[name="password"]', 'wrongpassword');
    await freshPage.click('#loginForm button[type="submit"]');

    // Should show error message
    await expect(freshPage.locator('text=Invalid credentials')).toBeVisible();
  });

  test('can register a new account', async ({ freshPage, uniqueEmail }) => {
    await freshPage.goto('/login');

    // Switch to register tab
    await freshPage.click('button.auth-tab:has-text("Register")');

    // Fill registration form
    await freshPage.fill('#registerForm input[name="email"]', uniqueEmail);
    await freshPage.fill('#registerForm input[name="password"]', 'newuserpassword123');
    await freshPage.fill('#registerForm input[name="password_confirm"]', 'newuserpassword123');
    await freshPage.click('#registerForm button[type="submit"]');

    // Should redirect to dashboard
    await expect(freshPage).toHaveURL('/dashboard');
    await expect(freshPage.locator('h1')).toContainText('Dashboard');
  });
});
