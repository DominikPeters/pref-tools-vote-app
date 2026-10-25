// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Thank You Message Buttons', () => {
  test('shows Edit button when editing is enabled', async ({ freshPage, api }) => {
    const response = await api.post('/api/polls', {
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
    
    await freshPage.getByRole('radio', { name: 'A', exact: true }).check();
    await freshPage.click('#pollForm button[type="submit"]');

    await expect(freshPage.locator('text=Thank you')).toBeVisible();
    await expect(freshPage.locator('button:has-text("Edit Your Response")')).toBeVisible();
    await expect(freshPage.locator('a:has-text("View Results")')).not.toBeVisible();
  });

  test('hides Edit button when editing is disabled (secret ballot)', async ({ freshPage, api }) => {
    const response = await api.post('/api/polls', {
      data: {
        title: 'Secret Ballot',
        status: 'open',
        voting_mode: 'secret_ballot',
        questions: [{ type: 'single_choice', text: 'Q1', options: [{ label: 'A' }, { label: 'B' }] }]
      }
    });
    const pollData = await response.json();

    // Create an access token since secret ballot requires it
    const tokenResponse = await api.post(`/api/polls/${pollData.poll.public_id}/admin/${pollData.poll.admin_token}/tokens`, {
      data: { count: 1 }
    });
    const tokenData = await tokenResponse.json();
    const token = tokenData.tokens[0].token;
    const requests = [];
    freshPage.on('request', request => requests.push({
      url: request.url(), referrer: request.headers()['referer'] || '',
    }));

    expect(tokenData.tokens[0].url).toContain('#token=');
    await freshPage.goto(tokenData.tokens[0].url);
    
    // Wait for questions to be rendered
    await freshPage.waitForSelector('.question-display');
    await expect(freshPage).toHaveURL(`/${pollData.poll.public_id}`);
    const submission = freshPage.waitForRequest(request => request.method() === 'POST'
      && new URL(request.url()).pathname === `/api/polls/${pollData.poll.public_id}/responses`);
    
    await freshPage.getByRole('radio', { name: 'A', exact: true }).check();
    await freshPage.click('#pollForm button[type="submit"]');
    
    // Handle secret ballot confirmation modal
    await freshPage.click('.confirm-modal .btn-confirm');

    await expect(freshPage.locator('text=Thank you')).toBeVisible();
    await expect(freshPage.locator('button:has-text("Edit Your Response")')).not.toBeVisible();
    await expect(freshPage.locator('a:has-text("View Results")')).not.toBeVisible();
    const submitted = await submission;
    expect(new URL(submitted.url()).search).toBe('');
    expect(submitted.postDataJSON().access_token).toBe(token);
    expect(requests.every(request => !request.url.includes(token) && !request.referrer.includes(token))).toBe(true);
  });

  test('shows editing banner and update button when already voted and can edit', async ({ freshPage, api }) => {
    const response = await api.post('/api/polls', {
      data: {
        title: 'Editable Voted Poll',
        status: 'open',
        allow_edit_own: true,
        questions: [{ type: 'single_choice', text: 'Q1', options: [{ label: 'A' }, { label: 'B' }] }]
      }
    });
    const pollData = await response.json();

    await freshPage.goto(`/${pollData.poll.public_id}`);
    await freshPage.getByRole('radio', { name: 'A', exact: true }).check();
    await freshPage.click('#pollForm button[type="submit"]');

    await expect(freshPage.locator('text=Thank you')).toBeVisible();

    // Reload the page
    await freshPage.reload();

    // Should see editing banner, not the thank you message
    await expect(freshPage.locator('.editing-banner')).toBeVisible();
    await expect(freshPage.locator('#pollForm button[type="submit"]')).toContainText('Update');
    await expect(freshPage.locator('text=Thank you')).not.toBeVisible();
  });

  test('shows View Results button when results are public', async ({ freshPage, api }) => {
    const response = await api.post('/api/polls', {
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
    
    await freshPage.getByRole('radio', { name: 'A', exact: true }).check();
    await freshPage.click('#pollForm button[type="submit"]');

    await expect(freshPage.locator('text=Thank you')).toBeVisible();
    await expect(freshPage.locator('a:has-text("View Results")')).toBeVisible();
    await expect(freshPage.locator('button:has-text("Edit Your Response")')).toBeVisible();
  });

  test('shows thank you message on page reload if already voted and cannot edit', async ({ freshPage, api }) => {
    const response = await api.post('/api/polls', {
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
    
    await freshPage.getByRole('radio', { name: 'A', exact: true }).check();
    await freshPage.click('#pollForm button[type="submit"]');

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
