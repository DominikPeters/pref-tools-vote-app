// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Sysadmin Dashboard', () => {
  test.beforeEach(async ({ page, installedApp }) => {
    // Log in as sysadmin before each test
    await page.goto('/login');
    await page.click('button.auth-tab:has-text("Login")');
    await page.fill('input[name="email"]', installedApp.email);
    await page.fill('input[name="password"]', installedApp.password);
    await page.click('#loginForm button[type="submit"]');
    await expect(page).toHaveURL('/dashboard');
  });

  test('shows dashboard with stats', async ({ page }) => {
    await page.goto('/sysadmin');

    // Check header
    await expect(page.locator('h1')).toContainText('Sysadmin Dashboard');

    // Check nav links
    await expect(page.locator('.sysadmin-nav a:has-text("Overview")')).toBeVisible();
    await expect(page.locator('.sysadmin-nav a:has-text("Users")')).toBeVisible();
    await expect(page.locator('.sysadmin-nav a:has-text("Polls")')).toBeVisible();
    await expect(page.locator('.sysadmin-nav a:has-text("Logs")')).toBeVisible();
    await expect(page.locator('.sysadmin-nav a:has-text("Stats")')).toBeVisible();

    // Check stats cards
    await expect(page.locator('.stat-card')).toHaveCount(4);
  });

  test('can navigate to users page', async ({ page }) => {
    await page.goto('/sysadmin');
    await page.click('.sysadmin-nav a:has-text("Users")');

    await expect(page).toHaveURL('/sysadmin/users');
    await expect(page.locator('h1')).toContainText('User Administration');

    // Wait for users to load
    await expect(page.locator('#usersTable tbody tr')).not.toContainText('Loading');

    // Should show at least the sysadmin user
    await expect(page.locator('#usersTable')).toContainText('admin@test.com');
  });

  test('can navigate to polls page', async ({ page }) => {
    // Create a poll first
    await page.request.post('/api/polls', {
      data: {
        title: 'Sysadmin Test Poll',
        status: 'open',
        questions: [
          {
            type: 'single_choice',
            text: 'Test',
            options: [{ label: 'A' }, { label: 'B' }]
          }
        ]
      }
    });

    await page.goto('/sysadmin/polls');

    await expect(page.locator('h1')).toContainText('Poll Administration');

    // Wait for polls to load
    await expect(page.locator('#pollsTable tbody tr')).not.toContainText('Loading');

    // Should show the poll
    await expect(page.locator('#pollsTable')).toContainText('Sysadmin Test Poll');
  });

  test('can navigate to logs page', async ({ page }) => {
    await page.goto('/sysadmin/logs');

    await expect(page.locator('h1')).toContainText('Action Log');

    // Wait for logs to load
    await page.waitForTimeout(1000); // Give it time to load

    // Should show some log entries (at least user registration from install)
    await expect(page.locator('#logsTable tbody tr').first()).toBeVisible();
  });

  test('can navigate to stats page', async ({ page }) => {
    await page.goto('/sysadmin/stats');

    await expect(page.locator('h1')).toContainText('Statistics');

    // Should show detailed stats
    await expect(page.locator('text=Total Users')).toBeVisible();
    await expect(page.locator('text=Total Polls')).toBeVisible();
    await expect(page.locator('text=Total Responses')).toBeVisible();
  });

  test('can change user role', async ({ page }) => {
    // First create a regular user via API
    await page.request.post('/api/auth/register', {
      data: {
        email: 'regularuser@test.com',
        password: 'testpassword123'
      }
    });

    // Go to users page
    await page.goto('/sysadmin/users');

    // Wait for users to load
    await page.waitForSelector('#usersTable tbody tr:not(:has-text("Loading"))');

    // Find the regular user row and change their role
    const userRow = page.locator('#usersTable tbody tr', { hasText: 'regularuser@test.com' });
    await expect(userRow).toBeVisible();

    // Change role to sysadmin
    await userRow.locator('select.role-select').selectOption('sysadmin');

    // Wait a moment for the API call
    await page.waitForTimeout(500);

    // Verify the change persisted by reloading
    await page.reload();
    await page.waitForSelector('#usersTable tbody tr:not(:has-text("Loading"))');

    const updatedRow = page.locator('#usersTable tbody tr', { hasText: 'regularuser@test.com' });
    await expect(updatedRow.locator('select.role-select')).toHaveValue('sysadmin');
  });

  test('can delete a poll', async ({ page }) => {
    // Create a poll to delete
    const response = await page.request.post('/api/polls', {
      data: {
        title: 'Poll To Delete',
        status: 'draft',
        questions: [
          {
            type: 'single_choice',
            text: 'Test',
            options: [{ label: 'A' }]
          }
        ]
      }
    });
    const pollData = await response.json();

    // Go to polls page
    await page.goto('/sysadmin/polls');

    // Wait for polls to load
    await page.waitForSelector('#pollsTable tbody tr:not(:has-text("Loading"))');

    // Find the poll row
    const pollRow = page.locator('#pollsTable tbody tr', { hasText: 'Poll To Delete' });
    await expect(pollRow).toBeVisible();

    // Set up dialog handler for confirmation
    page.on('dialog', dialog => dialog.accept());

    // Click delete
    await pollRow.locator('button:has-text("Delete")').click();

    // Wait for deletion
    await page.waitForTimeout(500);

    // Poll should be gone
    await expect(page.locator('#pollsTable')).not.toContainText('Poll To Delete');
  });
});
