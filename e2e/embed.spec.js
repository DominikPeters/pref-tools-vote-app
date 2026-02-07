// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Poll Embedding', () => {
  test('can enable embedding for a poll', async ({ page, api }) => {
    // Create a poll via API
    const response = await api.post('/api/polls', {
      data: {
        title: 'Embeddable Poll',
        status: 'open',
        voting_mode: 'open',
        questions: [
          {
            type: 'single_choice',
            text: 'Pick one',
            options: [{ label: 'A' }, { label: 'B' }]
          }
        ]
      }
    });

    const pollData = await response.json();
    const { public_id, admin_token } = pollData.poll;

    // Go to admin panel
    await page.goto(`/${public_id}/admin/${admin_token}`);
    await expect(page.locator('h1')).toContainText('Embeddable Poll');

    // Find and enable embedding checkbox
    const embedCheckbox = page.locator('#settingAllowEmbedding');
    await expect(embedCheckbox).toBeVisible();
    await expect(embedCheckbox).not.toBeChecked();

    // Enable embedding
    await embedCheckbox.check();

    // Save settings
    await page.click('#saveSettings');
    await expect(page.locator('.toast')).toContainText('saved');

    // Wait for embed code section to appear
    await page.waitForSelector('#embedCodeSection:not([style*="display: none"])');

    // Verify embed code textarea is visible and has content
    const embedCode = page.locator('#embedCode');
    await expect(embedCode).toBeVisible();
    const codeValue = await embedCode.inputValue();
    expect(codeValue).toContain('vote-poll');
    expect(codeValue).toContain('/embed/');
  });

  test('embed section shows info message for non-open voting modes', async ({ page, api }) => {
    // Create a poll with identified voting mode
    const response = await api.post('/api/polls', {
      data: {
        title: 'Identified Poll',
        status: 'open',
        voting_mode: 'identified',
        questions: [
          {
            type: 'single_choice',
            text: 'Pick one',
            options: [{ label: 'A' }, { label: 'B' }]
          }
        ]
      }
    });

    const pollData = await response.json();
    const { public_id, admin_token } = pollData.poll;

    // Go to admin panel
    await page.goto(`/${public_id}/admin/${admin_token}`);

    // The embed section should show info banner, not the checkbox
    const infoBanner = page.locator('.embed-section .info-banner');
    await expect(infoBanner).toBeVisible();
    await expect(infoBanner).toContainText('Not available for this voting mode');

    // Checkbox should not exist
    const embedCheckbox = page.locator('#settingAllowEmbedding');
    await expect(embedCheckbox).not.toBeVisible();
  });

  test('can access embed page and submit vote', async ({ freshPage, api }) => {
    // Create and configure a poll for embedding
    const createResponse = await api.post('/api/polls', {
      data: {
        title: 'Embed Test Poll',
        status: 'open',
        voting_mode: 'open',
        questions: [
          {
            type: 'single_choice',
            text: 'What is 2 + 2?',
            options: [{ label: '3' }, { label: '4' }, { label: '5' }]
          }
        ]
      }
    });

    const pollData = await createResponse.json();
    const { public_id, admin_token } = pollData.poll;

    // Enable embedding via API
    await api.put(`/api/polls/${public_id}/admin/${admin_token}`, {
      data: { allow_embedding: true }
    });

    // Generate embed token
    const tokenResponse = await api.post(`/api/polls/${public_id}/admin/${admin_token}/embed-token`);
    const tokenData = await tokenResponse.json();
    const embedUrl = tokenData.embed_url;

    // Extract just the path from the embed URL
    const embedPath = new URL(embedUrl, 'http://localhost').pathname;

    // Visit the embed page (use freshPage as this is unauthenticated)
    await freshPage.goto(embedPath);

    // Should see the poll
    await expect(freshPage.locator('h1')).toContainText('Embed Test Poll');
    await expect(freshPage.locator('text=What is 2 + 2?')).toBeVisible();

    // Select an answer
    await freshPage.locator('input[value]').nth(1).check(); // Select "4"

    // Submit
    await freshPage.click('button[type="submit"]');

    // Should see thank you page
    await expect(freshPage.locator('text=Thank you')).toBeVisible({ timeout: 5000 });
    await expect(freshPage.locator('text=Pref.Tools Vote')).toBeVisible();
  });

  test('embed page shows error for disabled embedding', async ({ freshPage, api }) => {
    // Create a poll without enabling embedding
    const createResponse = await api.post('/api/polls', {
      data: {
        title: 'Non-Embed Poll',
        status: 'open',
        voting_mode: 'open',
        questions: [
          {
            type: 'single_choice',
            text: 'Test',
            options: [{ label: 'A' }]
          }
        ]
      }
    });

    const pollData = await createResponse.json();
    const { public_id, admin_token } = pollData.poll;

    // Generate embed token (but don't enable embedding)
    const tokenResponse = await api.post(`/api/polls/${public_id}/admin/${admin_token}/embed-token`);
    const tokenData = await tokenResponse.json();
    const embedPath = new URL(tokenData.embed_url, 'http://localhost').pathname;

    // Try to visit the embed page
    await freshPage.goto(embedPath);

    // Should see error page
    await expect(freshPage.locator('text=Embedding Not Available')).toBeVisible();
  });

  test('embed page shows link to results when public', async ({ freshPage, api }) => {
    // Create poll with public results
    const createResponse = await api.post('/api/polls', {
      data: {
        title: 'Public Results Poll',
        status: 'open',
        voting_mode: 'open',
        visibility: 'full', // Public results
        questions: [
          {
            type: 'single_choice',
            text: 'Pick one',
            options: [{ label: 'A' }, { label: 'B' }]
          }
        ]
      }
    });

    const pollData = await createResponse.json();
    const { public_id, admin_token } = pollData.poll;

    // Enable embedding
    await api.put(`/api/polls/${public_id}/admin/${admin_token}`, {
      data: { allow_embedding: true }
    });

    // Get embed token
    const tokenResponse = await api.post(`/api/polls/${public_id}/admin/${admin_token}/embed-token`);
    const tokenData = await tokenResponse.json();
    const embedPath = new URL(tokenData.embed_url, 'http://localhost').pathname;

    // Visit embed page
    await freshPage.goto(embedPath);

    // Vote
    await freshPage.locator('input[type="radio"]').first().check();
    await freshPage.click('button[type="submit"]');

    // Should see View Results link on thank you page
    await expect(freshPage.locator('text=Thank you')).toBeVisible({ timeout: 5000 });
    await expect(freshPage.locator('a:has-text("View Results")')).toBeVisible();
  });

  test('copy embed code button works', async ({ page, api }) => {
    // Create and configure a poll
    const response = await api.post('/api/polls', {
      data: {
        title: 'Copy Test Poll',
        status: 'open',
        voting_mode: 'open',
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
    const { public_id, admin_token } = pollData.poll;

    // Go to admin and enable embedding
    await page.goto(`/${public_id}/admin/${admin_token}`);
    await page.locator('#settingAllowEmbedding').check();
    await page.click('#saveSettings');

    // Wait for embed section
    await page.waitForSelector('#embedCode');

    // Click copy button
    await page.click('.copy-embed-btn');

    // Should show success toast
    await expect(page.locator('.toast')).toContainText('copied');
  });
});
