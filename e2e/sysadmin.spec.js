// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Sysadmin Dashboard', () => {
  // No beforeEach needed - page is pre-authenticated as admin via storage state

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
    await expect(page.locator('#usersTable tbody tr').first()).toBeVisible();

    // Should show at least the sysadmin user
    await expect(page.locator('#usersTable')).toContainText('admin@example.com');
  });

  test('can navigate to polls page', async ({ page, request }) => {
    // Create a poll first
    await request.post('/api/polls', {
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
    await expect(page.locator('#pollsTable tbody tr').first()).toBeVisible();

    // Should show the poll
    await expect(page.locator('#pollsTable')).toContainText('Sysadmin Test Poll');
  });

  test('can navigate to logs page', async ({ page }) => {
    await page.goto('/sysadmin/logs');

    await expect(page.locator('h1')).toContainText('Action Log');

    // Wait for logs to load
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

  test('can change user role', async ({ page, userAccount }) => {
    // userAccount fixture creates a fresh regular user in its own context
    const userEmail = userAccount.email;

    // Go to users page (we're still authenticated as admin in our page context)
    await page.goto('/sysadmin/users');

    // Wait for users to load
    await page.waitForSelector('#usersTable tbody tr');

    // Find the regular user row and change their role
    const userRow = page.locator('#usersTable tbody tr', { hasText: userEmail });
    await expect(userRow).toBeVisible();

    // Change role to sysadmin
    await userRow.locator('select.role-select').selectOption('sysadmin');

    // Wait for the API call to complete
    await page.waitForResponse(response =>
      response.url().includes('/api/') && response.status() === 200
    );

    // Verify the change persisted by reloading
    await page.reload();
    await page.waitForSelector('#usersTable tbody tr');

    const updatedRow = page.locator('#usersTable tbody tr', { hasText: userEmail });
    await expect(updatedRow.locator('select.role-select')).toHaveValue('sysadmin');
  });

  test('can delete a poll', async ({ page, request }) => {
    // Create a poll to delete
    const response = await request.post('/api/polls', {
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
    await response.json();

    // Go to polls page
    await page.goto('/sysadmin/polls');

    // Wait for polls to load
    await page.waitForSelector('#pollsTable tbody tr');

    // Find the poll row
    const pollRow = page.locator('#pollsTable tbody tr', { hasText: 'Poll To Delete' });
    await expect(pollRow).toBeVisible();

    // Click delete
    await pollRow.locator('button:has-text("Delete")').click();

    // Confirm in the custom modal
    await page.click('.confirm-modal .btn-confirm');

    // Wait for deletion
    await page.waitForResponse(response =>
      response.url().includes('/api/') && response.status() === 200
    );

    // Poll should be gone
    await expect(page.locator('#pollsTable')).not.toContainText('Poll To Delete');
  });

  test('can view user details', async ({ page, userAccount }) => {
    const userEmail = userAccount.email;

    await page.goto('/sysadmin/users');
    await page.waitForSelector('#usersTable tbody tr');

    // Find and click on the user
    const userRow = page.locator('#usersTable tbody tr', { hasText: userEmail });
    await expect(userRow).toBeVisible();

    // Check that user info is displayed
    await expect(userRow).toContainText(userEmail);
  });
});
