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

  test('can reset password via email link', async ({ freshPage, uniqueEmail, api, waitForEmail }) => {
    // Check if Mailhog is available
    const mailhogAvailable = await fetch('http://127.0.0.1:8025/api/v2/messages')
      .then(() => true)
      .catch(() => false);

    if (!mailhogAvailable) {
      console.log('Skipping password reset test: Mailhog not available at 127.0.0.1:8025');
      test.skip();
      return;
    }

    // Configure mail settings
    const settingsResponse = await api.put('/api/sysadmin/settings', {
      data: {
        settings: {
          'mail.enabled': '1',
          'mail.smtp_host': '127.0.0.1',
          'mail.smtp_port': '1025',
          'mail.from_address': 'test@pref.tools',
          'mail.from_name': 'Test Pref.Tools',
          'mail.smtp_encryption': 'none',
        }
      }
    });
    expect(settingsResponse.ok()).toBeTruthy();

    // Clear existing emails
    await fetch('http://127.0.0.1:8025/api/v1/messages', { method: 'DELETE' });

    // 1. Register a new user
    await freshPage.goto('/login');
    await freshPage.click('button.auth-tab:has-text("Register")');
    await freshPage.fill('#registerForm input[name="name"]', 'Password Reset User');
    await freshPage.fill('#registerForm input[name="email"]', uniqueEmail);
    await freshPage.fill('#registerForm input[name="password"]', 'oldpassword123');
    await freshPage.fill('#registerForm input[name="password_confirm"]', 'oldpassword123');
    await freshPage.click('#registerForm button[type="submit"]');
    await expect(freshPage).toHaveURL('/dashboard');

    // Clear emails (registration sends welcome email)
    await fetch('http://127.0.0.1:8025/api/v1/messages', { method: 'DELETE' });

    // 2. Log out and request password reset
    await freshPage.click('.user-menu-trigger');
    // Wait for navigation after clicking logout
    await Promise.all([
      freshPage.waitForURL('/'),
      freshPage.click('button:has-text("Log Out")')
    ]);

    // Navigate to login and request password reset
    await freshPage.goto('/login');
    await expect(freshPage.locator('#loginForm')).toBeVisible();
    await freshPage.click('#showForgotPassword');
    await freshPage.fill('#forgotEmail', uniqueEmail);
    await freshPage.click('#forgotPasswordForm button[type="submit"]');

    // Should show success message
    await expect(freshPage.locator('#forgotSuccess')).toBeVisible();

    // 3. Get reset link from Mailhog with retry
    const resetEmail = await waitForEmail(m =>
      m.Content.Headers.To?.[0]?.includes(uniqueEmail.toLowerCase()) &&
      (m.Content.Headers.Subject?.[0]?.includes('Reset') ||
       m.Content.Headers.Subject?.[0]?.includes('Password'))
    );
    expect(resetEmail).toBeTruthy();

    // Extract reset link from email body
    const emailBody = resetEmail.Content.Body;
    const resetLinkMatch = emailBody.match(/https?:\/\/[^\s"<>]+reset_token=[^\s"<>]+/);
    expect(resetLinkMatch).toBeTruthy();
    const resetLink = resetLinkMatch[0];

    // 4. Use reset link to set new password
    await freshPage.goto(resetLink);
    await expect(freshPage.locator('#resetPasswordForm')).toBeVisible();

    await freshPage.fill('#resetPassword', 'newpassword456');
    await freshPage.fill('#resetPasswordConfirm', 'newpassword456');
    await freshPage.click('#resetPasswordForm button[type="submit"]');

    // Should show success message and redirect to dashboard (auto-login)
    await expect(freshPage.locator('.auth-message')).toContainText('Password reset successfully');
    await expect(freshPage).toHaveURL('/dashboard', { timeout: 5000 });

    // 5. Verify we're logged in by checking user menu shows
    await expect(freshPage.locator('.user-menu-trigger')).toBeVisible();
  });

  test('user can verify email via link from email', async ({ freshPage, uniqueEmail, api, waitForEmail }) => {
    // Check if Mailhog is available
    const mailhogAvailable = await fetch('http://127.0.0.1:8025/api/v2/messages')
      .then(() => true)
      .catch(() => false);

    if (!mailhogAvailable) {
      console.log('Skipping email verification test: Mailhog not available at 127.0.0.1:8025');
      test.skip();
      return;
    }

    // Configure mail settings
    const settingsResponse = await api.put('/api/sysadmin/settings', {
      data: {
        settings: {
          'mail.enabled': '1',
          'mail.smtp_host': '127.0.0.1',
          'mail.smtp_port': '1025',
          'mail.from_address': 'test@pref.tools',
          'mail.from_name': 'Test Pref.Tools',
          'mail.smtp_encryption': 'none',
        }
      }
    });
    expect(settingsResponse.ok()).toBeTruthy();

    // Clear existing emails
    await fetch('http://127.0.0.1:8025/api/v1/messages', { method: 'DELETE' });

    // 1. Register a new user
    await freshPage.goto('/login');
    await freshPage.click('button.auth-tab:has-text("Register")');
    await freshPage.fill('#registerForm input[name="name"]', 'Email Verification User');
    await freshPage.fill('#registerForm input[name="email"]', uniqueEmail);
    await freshPage.fill('#registerForm input[name="password"]', 'password123');
    await freshPage.fill('#registerForm input[name="password_confirm"]', 'password123');
    await freshPage.click('#registerForm button[type="submit"]');

    // Should be on dashboard and see verification banner
    await expect(freshPage).toHaveURL('/dashboard');
    await expect(freshPage.locator('.verification-banner')).toBeVisible();

    // 2. Get the verification email from Mailhog with retry
    const verifyEmail = await waitForEmail(m =>
      m.Content.Headers.To?.[0]?.includes(uniqueEmail.toLowerCase()) &&
      (m.Content.Headers.Subject?.[0]?.includes('Verify') ||
       m.Content.Headers.Subject?.[0]?.includes('Welcome'))
    );
    expect(verifyEmail).toBeTruthy();

    // Extract verification link from email body
    const emailBody = verifyEmail.Content.Body;
    const verifyLinkMatch = emailBody.match(/https?:\/\/[^\s"<>]+verify_token=[^\s"<>]+/);
    expect(verifyLinkMatch).toBeTruthy();
    const verifyLink = verifyLinkMatch[0];

    // 3. Log out first (simulates clicking link from email client)
    await freshPage.click('.user-menu-trigger');
    await Promise.all([
      freshPage.waitForURL('/'),
      freshPage.click('button:has-text("Log Out")')
    ]);

    // 4. Click verification link from email
    await freshPage.goto(verifyLink);

    // 5. Should redirect to dashboard
    await expect(freshPage).toHaveURL(/\/dashboard/);

    // 6. Verification banner should be GONE
    await expect(freshPage.locator('.verification-banner')).not.toBeVisible();

    // 7. Should show success toast
    await expect(freshPage.locator('.toast-success')).toBeVisible();
    await expect(freshPage.locator('.toast-success')).toContainText('Email verified successfully');
  });
});
