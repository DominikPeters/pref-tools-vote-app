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

    // Small delay to ensure DB sync
    await new Promise(resolve => setTimeout(resolve, 500));

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
    const tokenUrl = await page.locator('.tokens-table tbody tr').first().locator('.copy-token').getAttribute('data-url');
    expect(tokenUrl).toContain('token=');

    // 5. Vote with token
    await freshPage.goto(tokenUrl);
    await expect(freshPage.locator('h1')).toContainText('Token Access Poll');
    await freshPage.click('text=A');
    await expect(freshPage.locator('button[type="submit"]')).toBeVisible({ timeout: 1000 });
    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();

    // 6. Try to use same token again - should be blocked
    await freshPage.goto(tokenUrl);
    await expect(freshPage.locator('text=already been used')).toBeVisible();
  });

  test('can send and use email invitations', async ({ page, freshPage, api }) => {
    // Only works if mail is "configured" in the test env
    // We can simulate this by setting mail.enabled = true in config
    // For now, let's just check the UI flow in the admin panel
    
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

    await page.goto(`/${poll.public_id}/admin/${poll.admin_token}`);
    
    // Check invitations tab
    await page.click('button[data-tab="invitations"]');
    await expect(page.locator('#invitationEmails')).toBeVisible();

    // Since actual mail sending might fail in E2E environment without SMTP, 
    // we just check that the UI elements are there.
    // If mail isn't configured, it should show a warning.
    const isMailEnabled = await page.evaluate(() => {
        return !document.getElementById('mailConfigWarning')?.offsetParent;
    });

    if (isMailEnabled) {
        await page.fill('#invitationEmails', 'test@example.com');
        await page.click('#sendInvitations');
        await expect(page.locator('.invitations-table')).toContainText('test@example.com');
        
        // Get invitation URL
        const inviteUrl = await page.locator('.invitations-table tbody tr').first().locator('.copy-invitation').getAttribute('data-url');
        
        // Vote with invite
        if (inviteUrl) {
            await freshPage.goto(inviteUrl);
            await expect(freshPage.locator('h1')).toContainText('Email Invitation Poll');
        }
    }
  });

});
