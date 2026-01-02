// @ts-check
const { test, expect } = require('./fixtures');
const { execSync } = require('child_process');
const path = require('path');

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

    // Should show user name in header
    await expect(freshPage.locator('.user-name')).toContainText(adminCredentials.name);

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
    await freshPage.click('.user-menu-trigger');
    await freshPage.click('button:has-text("Log Out")');
    await freshPage.waitForURL(url => url.pathname === '/' || url.pathname === '/login');

    // Verify logged out by trying to access dashboard
    await freshPage.goto('/dashboard');
    await expect(freshPage).toHaveURL(/\/login/);
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
    await freshPage.fill('#registerForm input[name="name"]', 'New Test User');
    await freshPage.fill('#registerForm input[name="email"]', uniqueEmail);
    await freshPage.fill('#registerForm input[name="password"]', 'newuserpassword123');
    await freshPage.fill('#registerForm input[name="password_confirm"]', 'newuserpassword123');
    await freshPage.click('#registerForm button[type="submit"]');

    // Should redirect to dashboard
    await expect(freshPage).toHaveURL('/dashboard');
    await expect(freshPage.locator('h1')).toContainText('Dashboard');

    // Should show user name in header
    await expect(freshPage.locator('.user-name')).toContainText('New Test User');
  });

  test('new user sees email verification banner', async ({ freshPage, uniqueEmail }) => {
    await freshPage.goto('/login');

    // Register a new user
    await freshPage.click('button.auth-tab:has-text("Register")');
    await freshPage.fill('#registerForm input[name="name"]', 'Unverified User');
    await freshPage.fill('#registerForm input[name="email"]', uniqueEmail);
    await freshPage.fill('#registerForm input[name="password"]', 'password12345');
    await freshPage.fill('#registerForm input[name="password_confirm"]', 'password12345');
    await freshPage.click('#registerForm button[type="submit"]');

    // Should redirect to dashboard
    await expect(freshPage).toHaveURL('/dashboard');

    // Should show verification banner
    await expect(freshPage.locator('.verification-banner')).toBeVisible();
    await expect(freshPage.locator('.verification-banner')).toContainText('Verify your email address');

    // Should show resend button
    await expect(freshPage.locator('#resendVerificationBtn')).toBeVisible();
  });

  test('forgot password link shows forgot password form', async ({ freshPage }) => {
    await freshPage.goto('/login');

    // Click forgot password link
    await freshPage.click('#showForgotPassword');

    // Should show forgot password form
    await expect(freshPage.locator('#forgotPasswordForm')).toBeVisible();
    await expect(freshPage.locator('#forgotPasswordForm h2')).toContainText('Reset Password');

    // Should have email input
    await expect(freshPage.locator('#forgotEmail')).toBeVisible();

    // Should have back to login link
    await expect(freshPage.locator('#backToLogin')).toBeVisible();
  });

  test('can navigate back from forgot password to login', async ({ freshPage }) => {
    await freshPage.goto('/login');

    // Go to forgot password
    await freshPage.click('#showForgotPassword');
    await expect(freshPage.locator('#forgotPasswordForm')).toBeVisible();

    // Click back to login
    await freshPage.click('#backToLogin');

    // Should show login form again
    await expect(freshPage.locator('#loginForm')).toBeVisible();
    await expect(freshPage.locator('#forgotPasswordForm')).not.toBeVisible();
  });

  test('forgot password form accepts email', async ({ freshPage }) => {
    await freshPage.goto('/login');
    await freshPage.click('#showForgotPassword');

    // Fill in email
    await freshPage.fill('#forgotEmail', 'test@example.com');
    await freshPage.click('#forgotPasswordForm button[type="submit"]');

    // Should show success message (or at least not fail with validation error)
    // Note: Mail may not be configured, but we should see either success or mail error
    const message = freshPage.locator('#forgotSuccess, #forgotError').filter({ hasText: /./ });
    await expect(message).toBeVisible();
  });

  test('user can verify email via link', async ({ freshPage, uniqueEmail }) => {
    // 1. Register a new user
    await freshPage.goto('/login');
    await freshPage.click('button.auth-tab:has-text("Register")');
    await freshPage.fill('#registerForm input[name="name"]', 'Verification Test User');
    await freshPage.fill('#registerForm input[name="email"]', uniqueEmail);
    await freshPage.fill('#registerForm input[name="password"]', 'password123');
    await freshPage.fill('#registerForm input[name="password_confirm"]', 'password123');
    await freshPage.click('#registerForm button[type="submit"]');

    // Should be on dashboard and see verification banner
    await expect(freshPage).toHaveURL('/dashboard');
    await expect(freshPage.locator('.verification-banner')).toBeVisible();

    // 2. Get the verification token from the database
    const dbPath = path.join(__dirname, '..', 'data', 'poll.test.db');
    const query = `SELECT email_verification_token FROM users WHERE email = '${uniqueEmail.toLowerCase()}'`;
    const token = execSync(`sqlite3 ${dbPath} "${query}"`).toString().trim();

    expect(token).toBeTruthy();

    // 3. Navigate to verification URL
    await freshPage.goto(`/login?verify_token=${token}`);

    // 4. Should redirect to dashboard
    await expect(freshPage).toHaveURL(/\/dashboard/);
    
    // 5. Verification banner should be GONE
    await expect(freshPage.locator('.verification-banner')).not.toBeVisible();
    
    // 6. Should show success toast
    await expect(freshPage.locator('.toast-success')).toBeVisible();
    await expect(freshPage.locator('.toast-success')).toContainText('Email verified successfully');
  });
});
