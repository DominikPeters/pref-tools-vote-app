// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Access Modes', () => {

  test('can generate and use one-time tokens', async ({ page, freshPage, api }) => {
    // 1. Create identified poll
    const pollResponse = await api.post('/api/polls', {
      data: {
        title: 'Token Access Poll',
        status: 'open',
        voting_mode: 'identified',
        access_mode: 'token',
        questions: [{ type: 'single_choice', text: 'Q1', options: [{ label: 'A' }] }]
      }
    });
    const { poll } = await pollResponse.json();

    // 2. Access poll directly - should be blocked
    await expect(async () => {
        await freshPage.goto(`/${poll.public_id}`);
        await expect(freshPage.locator('h1')).toContainText('Access Required');
    }).toPass();

    // 3. Go to admin and generate tokens
    await page.goto(`/${poll.public_id}/admin/${poll.admin_token}`);
    await page.click('button[data-tab="tokens"]');
    await page.fill('#tokenCount', '2');
    await page.click('#generateTokens');
    await expect(page.locator('text=Generated 2 tokens')).toBeVisible();

    // 4. Get a token URL from the table
    await page.waitForSelector('.tokens-table tbody tr .copy-token');
    const tokenUrl = await page.locator('.tokens-table tbody tr').first().locator('.copy-token').getAttribute('data-url');
    expect(tokenUrl).toContain('token=');

    // 5. Vote with token
    await freshPage.goto(tokenUrl);
    await expect(freshPage.locator('h1')).toContainText('Token Access Poll');

    // Wait for poll questions to be rendered by JavaScript before interacting
    await freshPage.waitForSelector('#questionsContainer .question-display');

    // Click on the radio button option (use specific selector to avoid ambiguity)
    await freshPage.click('.radio-option input[type="radio"]');
    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();

    // 6. Try to use same token again - should be blocked
    await freshPage.goto(tokenUrl);
    await expect(freshPage.locator('text=already been used')).toBeVisible();
  });

  test('can send and use email invitations', async ({ page, freshPage, api }) => {
    // Check if Mailhog is available for testing
    const mailhogAvailable = await fetch('http://127.0.0.1:8025/api/v2/messages')
      .then(() => true)
      .catch(() => false);

    if (!mailhogAvailable) {
      console.log('Skipping email invitation test: Mailhog not available at 127.0.0.1:8025');
      console.log('Start Mailhog with: docker run -d -p 1025:1025 -p 8025:8025 mailhog/mailhog');
      test.skip();
      return;
    }

    // Configure mail settings via sysadmin API BEFORE creating the poll
    // This ensures the admin page loads with mail enabled
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

    // Clear any existing emails in Mailhog
    await fetch('http://127.0.0.1:8025/api/v1/messages', { method: 'DELETE' });

    const pollResponse = await api.post('/api/polls', {
      data: {
        title: 'Email Invitation Poll',
        status: 'open',
        voting_mode: 'identified',
        access_mode: 'token',
        questions: [{ type: 'single_choice', text: 'Q1', options: [{ label: 'A' }] }]
      }
    });
    const { poll } = await pollResponse.json();

    // Navigate to admin page AFTER mail is configured
    await page.goto(`/${poll.public_id}/admin/${poll.admin_token}`);

    // Check invitations tab - mail should be enabled now
    await page.click('button[data-tab="invitations"]');
    await expect(page.locator('#invitationEmails')).toBeVisible();

    // Verify mail is configured (no warning visible)
    await expect(page.locator('#mailConfigWarning')).not.toBeVisible();

    // Send invitation
    await page.fill('#invitationEmails', 'voter@example.com');
    await page.click('#sendInvitations');
    await expect(page.locator('.invitations-table')).toContainText('voter@example.com');

    // Get invitation URL from the table
    await page.waitForSelector('.invitations-table tbody tr .copy-invitation');
    const inviteUrl = await page.locator('.invitations-table tbody tr').first().locator('.copy-invitation').getAttribute('data-url');
    expect(inviteUrl).toContain('token=');

    // Vote with invitation URL
    await freshPage.goto(inviteUrl);
    await expect(freshPage.locator('h1')).toContainText('Email Invitation Poll');
    await freshPage.waitForSelector('#questionsContainer .question-display');
    await freshPage.click('.radio-option input[type="radio"]');
    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();

    // Verify invitation is now marked as used
    await page.reload();
    await page.click('button[data-tab="invitations"]');
    await expect(page.locator('.invitations-table .badge-used')).toBeVisible();
  });

});
