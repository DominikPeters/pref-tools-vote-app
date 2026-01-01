// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Poll Creation and Voting', () => {
  test('can create a poll without logging in', async ({ freshPage }) => {
    // Use freshPage to verify no auth is required
    await freshPage.goto('/create');

    // Should be on builder page
    await expect(freshPage.locator('h1')).toContainText('Create');

    // Fill in poll title
    await freshPage.fill('#pollTitle', 'Test Poll');

    // Add a question
    await freshPage.click('#addQuestionBtn');

    // Fill in question text
    await freshPage.fill('.question-title-input', 'What is your favorite color?');

    // Fill in options (there are 2 default options)
    const optionInputs = freshPage.locator('.option-label-input');
    await optionInputs.nth(0).fill('Red');
    await optionInputs.nth(1).fill('Blue');

    // Publish the poll
    await freshPage.click('#publishBtn');

    // Should redirect to admin panel
    await expect(freshPage).toHaveURL(/\/admin\//);
    await expect(freshPage.locator('.admin-label')).toContainText('Poll Admin');
    await expect(freshPage.locator('h1')).toContainText('Test Poll');
  });

  test('can access poll by public link', async ({ freshPage, request }) => {
    // Create a poll via API
    const response = await request.post('/api/polls', {
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
    expect(pollData.poll.public_id).toBeDefined();

    // Navigate to the poll (use freshPage - voters don't need auth)
    await freshPage.goto(`/${pollData.poll.public_id}`);

    // Should see the poll
    await expect(freshPage.locator('h1')).toContainText('E2E Test Poll');

    // Should see the question
    await expect(freshPage.locator('text=What is your favorite color?')).toBeVisible();

    // Should see the options
    await expect(freshPage.locator('text=Red')).toBeVisible();
    await expect(freshPage.locator('text=Blue')).toBeVisible();
    await expect(freshPage.locator('text=Green')).toBeVisible();
  });

  test('can submit a vote', async ({ freshPage, request }) => {
    // Create a poll via API
    const response = await request.post('/api/polls', {
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
    await freshPage.goto(`/${pollData.poll.public_id}`);

    // Select an option
    await freshPage.click('text=Option A');

    // Submit vote
    await freshPage.click('button[type="submit"]');

    // Should see success message or confirmation
    await expect(freshPage.locator('text=Thank you')).toBeVisible({ timeout: 5000 });
  });

  test('cannot vote on closed poll', async ({ freshPage, request }) => {
    // Create a closed poll via API
    const response = await request.post('/api/polls', {
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
    await freshPage.goto(`/${pollData.poll.public_id}`);

    // Should show closed message or redirect to results
    await expect(freshPage.locator('h2:has-text("Closed")')).toBeVisible();
  });

  test('poll admin can access admin panel', async ({ freshPage, request }) => {
    // Create a poll via API
    const response = await request.post('/api/polls', {
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
    expect(pollData.poll.admin_token).toBeDefined();

    // Navigate to admin panel (no auth required - uses admin token in URL)
    await freshPage.goto(`/${pollData.poll.public_id}/admin/${pollData.poll.admin_token}`);

    // Should see admin panel
    await expect(freshPage.locator('.admin-label')).toContainText('Poll Admin');
    await expect(freshPage.locator('h1')).toContainText('Admin Test Poll');
  });

  test('poll shows on authenticated user dashboard', async ({ page, request }) => {
    // Create a poll via API (authenticated as admin via storage state)
    const response = await request.post('/api/polls', {
      data: {
        title: 'Dashboard Visible Poll',
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

    await response.json();

    // Go to dashboard
    await page.goto('/dashboard');

    // Poll should be visible
    await expect(page.locator('text=Dashboard Visible Poll')).toBeVisible();
  });
});
