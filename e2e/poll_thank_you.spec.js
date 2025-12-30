// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Thank You Message Buttons', () => {
  test('shows Edit button when editing is enabled', async ({ freshPage, request }) => {
    const response = await request.post('/api/polls', {
      data: {
        title: 'Editable Poll',
        status: 'open',
        allow_edit_own: true,
        questions: [{ type: 'single_choice', text: 'Q1', options: [{ label: 'A' }, { label: 'B' }] }]
      }
    });
    const pollData = await response.json();

    await freshPage.goto(`/${pollData.poll.public_id}`);
    
    // Wait for questions to be rendered
    await freshPage.waitForSelector('.question-display');
    
    await freshPage.click('label:has-text("A")');
    await freshPage.click('button[type="submit"]');

    await expect(freshPage.locator('text=Thank you')).toBeVisible();
    await expect(freshPage.locator('button:has-text("Edit Your Response")')).toBeVisible();
    await expect(freshPage.locator('a:has-text("View Results")')).not.toBeVisible();
  });

  test('hides Edit button when editing is disabled (secret ballot)', async ({ freshPage, request }) => {
    const response = await request.post('/api/polls', {
      data: {
        title: 'Secret Ballot',
        status: 'open',
        voting_mode: 'secret_ballot',
        questions: [{ type: 'single_choice', text: 'Q1', options: [{ label: 'A' }, { label: 'B' }] }]
      }
    });
    const pollData = await response.json();

    // Create an access token since secret ballot requires it
    const tokenResponse = await request.post(`/api/polls/${pollData.poll.public_id}/admin/${pollData.poll.admin_token}/tokens`, {
      data: { count: 1 }
    });
    const tokenData = await tokenResponse.json();
    const token = tokenData.tokens[0].token;

    await freshPage.goto(`/${pollData.poll.public_id}?token=${token}`);
    
    // Wait for questions to be rendered
    await freshPage.waitForSelector('.question-display');
    
    await freshPage.click('label:has-text("A")');
    await freshPage.click('button[type="submit"]');
    
    // Handle secret ballot confirmation modal
    await freshPage.click('.confirm-modal .btn-confirm');

    await expect(freshPage.locator('text=Thank you')).toBeVisible();
    await expect(freshPage.locator('button:has-text("Edit Your Response")')).not.toBeVisible();
    await expect(freshPage.locator('a:has-text("View Results")')).not.toBeVisible();
  });

  test('shows editing banner and update button when already voted and can edit', async ({ freshPage, request }) => {
    const response = await request.post('/api/polls', {
      data: {
        title: 'Editable Voted Poll',
        status: 'open',
        allow_edit_own: true,
        questions: [{ type: 'single_choice', text: 'Q1', options: [{ label: 'A' }, { label: 'B' }] }]
      }
    });
    const pollData = await response.json();

    await freshPage.goto(`/${pollData.poll.public_id}`);
    await freshPage.click('label:has-text("A")');
    await freshPage.click('button[type="submit"]');

    await expect(freshPage.locator('text=Thank you')).toBeVisible();

    // Reload the page
    await freshPage.reload();

    // Should see editing banner, not the thank you message
    await expect(freshPage.locator('.editing-banner')).toBeVisible();
    await expect(freshPage.locator('button[type="submit"]')).toContainText('Update');
    await expect(freshPage.locator('text=Thank you')).not.toBeVisible();
  });

  test('shows View Results button when results are public', async ({ freshPage, request }) => {
    const response = await request.post('/api/polls', {
      data: {
        title: 'Public Results Poll',
        status: 'open',
        visibility: 'anonymous',
        questions: [{ type: 'single_choice', text: 'Q1', options: [{ label: 'A' }, { label: 'B' }] }]
      }
    });
    const pollData = await response.json();

    await freshPage.goto(`/${pollData.poll.public_id}`);
    
    // Wait for questions to be rendered
    await freshPage.waitForSelector('.question-display');
    
    await freshPage.click('label:has-text("A")');
    await freshPage.click('button[type="submit"]');

    await expect(freshPage.locator('text=Thank you')).toBeVisible();
    await expect(freshPage.locator('a:has-text("View Results")')).toBeVisible();
    await expect(freshPage.locator('button:has-text("Edit Your Response")')).toBeVisible();
  });

  test('shows thank you message on page reload if already voted and cannot edit', async ({ freshPage, request }) => {
    const response = await request.post('/api/polls', {
      data: {
        title: 'Non-editable Voted Poll',
        status: 'open',
        allow_edit_own: false,
        questions: [{ type: 'single_choice', text: 'Q1', options: [{ label: 'A' }, { label: 'B' }] }]
      }
    });
    const pollData = await response.json();

    await freshPage.goto(`/${pollData.poll.public_id}`);
    
    // Wait for questions to be rendered
    await freshPage.waitForSelector('.question-display');
    
    await freshPage.click('label:has-text("A")');
    await freshPage.click('button[type="submit"]');

    await expect(freshPage.locator('text=Thank you')).toBeVisible();
    await expect(freshPage.locator('button:has-text("Edit Your Response")')).not.toBeVisible();

    // Reload the page
    await freshPage.reload();

    // Should still see thank you message, not the form
    await expect(freshPage.locator('text=Thank you')).toBeVisible();
    await expect(freshPage.locator('text=recorded')).toBeVisible();
    await expect(freshPage.locator('form#pollForm')).not.toBeVisible();
  });
});
