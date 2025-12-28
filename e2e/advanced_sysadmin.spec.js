// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Advanced Sysadmin Features', () => {

  test('can delete a user account', async ({ page, userAccount }) => {
    const userEmail = userAccount.email;

    await page.goto('/sysadmin/users');
    await page.waitForSelector('#usersTable tbody tr');

    // Find the user row
    const userRow = page.locator('#usersTable tbody tr', { hasText: userEmail });
    await expect(userRow).toBeVisible();

    // Set up dialog handler for confirmation
    page.on('dialog', dialog => dialog.accept());

    // Click delete button in that row
    await userRow.locator('.delete-user-btn').click();

    // User should be gone from the list
    await expect(page.locator('#usersTable')).not.toContainText(userEmail);
  });

  test('shows system statistics', async ({ page, request }) => {
    // Ensure there is at least one poll to count
    await request.post('/api/polls', {
      data: {
        title: 'Stats Test Poll',
        status: 'open',
        questions: [{ type: 'text_single', text: 'Q1' }]
      }
    });

    await page.goto('/sysadmin/stats');

    // Check for some stat items that should be present
    await expect(page.locator('dt:has-text("Total Users")')).toBeVisible();
    await expect(page.locator('dt:has-text("Total Polls")')).toBeVisible();
    await expect(page.locator('dt:has-text("Total Responses")')).toBeVisible();

    // Verify some numbers are displayed (at least 1 poll, 1 user)
    const pollCountRow = page.locator('.stat-row', { has: page.locator('dt:has-text("Total Polls")') });
    const userCountRow = page.locator('.stat-row', { has: page.locator('dt:has-text("Total Users")') });

    const pollCountText = await pollCountRow.locator('dd').textContent();
    const userCountText = await userCountRow.locator('dd').textContent();

    expect(parseInt(pollCountText || '0')).toBeGreaterThan(0);
    expect(parseInt(userCountText || '0')).toBeGreaterThan(0);
  });

  test('can view action logs with data modal', async ({ page, request }) => {
    const uniqueTitle = `Log Test Poll ${Date.now()}`;
    // Trigger an action that logs data (e.g., creating a poll)
    const response = await request.post('/api/polls', {
      data: { title: uniqueTitle }
    });
    const { poll } = await response.json();
    const pollId = String(poll.id);

    await page.goto('/sysadmin/logs');
    await page.waitForSelector('#logsTable tbody tr');

    // Find the specific log entry for our poll by exact poll ID match in the poll column
    const logRowLocator = page.locator('#logsTable tbody tr').filter({
      has: page.locator('.log-poll', { hasText: new RegExp(`^${pollId}$`) })
    }).filter({
      has: page.locator('.log-action code', { hasText: 'poll.created' })
    });

    await expect(async () => {
      await page.reload();
      await page.waitForSelector('#logsTable tbody tr');
      await expect(logRowLocator.first()).toBeVisible();
    }).toPass({ timeout: 10000 });

    const viewBtn = logRowLocator.first().locator('.view-data-btn');
    await viewBtn.click();

    // Check for modal - the title should be in the data JSON
    const modal = page.locator('#dataModal');
    await expect(modal).toBeVisible();
    await expect(modal).toContainText(uniqueTitle);

    // Close modal
    await modal.locator('.modal-close').click();
    await expect(modal).not.toBeVisible();
  });

});
