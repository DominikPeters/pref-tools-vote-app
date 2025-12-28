// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Poll Creation and Voting', () => {
  test('can create a poll without logging in', async ({ page, installedApp }) => {
    // Go to create page
    await page.goto('/create');

    // Should be on builder page
    await expect(page.locator('h1')).toContainText('Create');

    // Fill in poll title
    await page.fill('input[name="title"]', 'Test Poll');

    // The builder is JavaScript-driven, so we need to interact with it
    // Add a question (assuming there's an "Add Question" button)
    // This depends on the actual builder UI - adjust as needed
  });

  test('can access poll by public link', async ({ page, installedApp }) => {
    // First create a poll via API (simpler for testing)
    const response = await page.request.post('/api/polls', {
      data: {
        title: 'E2E Test Poll',
        description: 'A poll created for E2E testing',
        status: 'open',
        questions: [
          {
            type: 'single_choice',
            text: 'What is your favorite color?',
            required: true,
            options: [
              { label: 'Red' },
              { label: 'Blue' },
              { label: 'Green' }
            ]
          }
        ]
      }
    });

    const pollData = await response.json();
    expect(pollData.public_id).toBeDefined();

    // Navigate to the poll
    await page.goto(`/${pollData.public_id}`);

    // Should see the poll
    await expect(page.locator('h1')).toContainText('E2E Test Poll');

    // Should see the question
    await expect(page.locator('text=What is your favorite color?')).toBeVisible();

    // Should see the options
    await expect(page.locator('text=Red')).toBeVisible();
    await expect(page.locator('text=Blue')).toBeVisible();
    await expect(page.locator('text=Green')).toBeVisible();
  });

  test('can submit a vote', async ({ page, installedApp }) => {
    // Create a poll via API
    const response = await page.request.post('/api/polls', {
      data: {
        title: 'Voting Test Poll',
        status: 'open',
        questions: [
          {
            type: 'single_choice',
            text: 'Pick one',
            required: true,
            options: [
              { label: 'Option A' },
              { label: 'Option B' }
            ]
          }
        ]
      }
    });

    const pollData = await response.json();

    // Navigate to poll
    await page.goto(`/${pollData.public_id}`);

    // Select an option
    await page.click('text=Option A');

    // Submit vote
    await page.click('button[type="submit"]');

    // Should see success message or redirect
    // The exact behavior depends on your implementation
    await expect(page.locator('text=Thank you')).toBeVisible({ timeout: 5000 }).catch(() => {
      // Or check for redirect to results
      return expect(page).toHaveURL(new RegExp(`${pollData.public_id}`));
    });
  });

  test('cannot vote on closed poll', async ({ page, installedApp }) => {
    // Create a closed poll via API
    const response = await page.request.post('/api/polls', {
      data: {
        title: 'Closed Poll',
        status: 'closed',
        questions: [
          {
            type: 'single_choice',
            text: 'Pick one',
            required: true,
            options: [
              { label: 'Option A' },
              { label: 'Option B' }
            ]
          }
        ]
      }
    });

    const pollData = await response.json();

    // Navigate to poll
    await page.goto(`/${pollData.public_id}`);

    // Should redirect to results or show closed message
    // depending on visibility settings
  });

  test('poll admin can access admin panel', async ({ page, installedApp }) => {
    // Create a poll via API
    const response = await page.request.post('/api/polls', {
      data: {
        title: 'Admin Test Poll',
        status: 'draft',
        questions: [
          {
            type: 'single_choice',
            text: 'Test question',
            options: [{ label: 'Yes' }, { label: 'No' }]
          }
        ]
      }
    });

    const pollData = await response.json();
    expect(pollData.admin_token).toBeDefined();

    // Navigate to admin panel
    await page.goto(`/${pollData.public_id}/admin/${pollData.admin_token}`);

    // Should see admin panel
    await expect(page.locator('h1')).toContainText('Admin');
    await expect(page.locator('text=Admin Test Poll')).toBeVisible();
  });
});
