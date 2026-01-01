// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Voter Experience', () => {

  test('saves progress to localStorage', async ({ freshPage, request }) => {
    // Create a poll via API
    const response = await request.post('/api/polls', {
      data: {
        title: 'Progress Test Poll',
        status: 'open',
        questions: [
          { type: 'text_single', text: 'Question 1', required: true },
          { type: 'text_single', text: 'Question 2', required: true }
        ]
      }
    });
    const { poll } = await response.json();

    await freshPage.goto(`/${poll.public_id}`);

    // Fill only the first question
    await freshPage.fill('input[type="text"]', 'Answer 1');

    // Reload the page
    await freshPage.reload();

    // Answer should still be there
    await expect(freshPage.locator('input[type="text"]').first()).toHaveValue('Answer 1');
    await expect(freshPage.locator('input[type="text"]').nth(1)).toHaveValue('');
  });

  test('can edit own response (cookie-based)', async ({ freshPage, request }) => {
    // Create poll with allow_edit_own = true
    const response = await request.post('/api/polls', {
      data: {
        title: 'Edit Own Test',
        status: 'open',
        allow_edit_own: true,
        questions: [
          { type: 'text_single', text: 'What is your name?', required: true }
        ]
      }
    });
    const { poll } = await response.json();

    // 1. Submit first time
    await freshPage.goto(`/${poll.public_id}`);
    await freshPage.fill('input[type="text"]', 'Initial Answer');
    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();

    // 2. Return to the poll page
    await freshPage.goto(`/${poll.public_id}`);

    // Should show editing banner and pre-fill value
    await expect(freshPage.locator('.editing-banner')).toBeVisible();
    await expect(freshPage.locator('input[type="text"]')).toHaveValue('Initial Answer');

    // 3. Update answer
    await freshPage.fill('input[type="text"]', 'Updated Answer');
    await freshPage.click('button:has-text("Update Response")');
    await expect(freshPage.locator('text=Your response has been updated')).toBeVisible();

    // 4. Verify update persisted
    await freshPage.reload();
    await expect(freshPage.locator('input[type="text"]')).toHaveValue('Updated Answer');
  });

  test('accesses password protected poll', async ({ freshPage, request }) => {
    // Create poll with password via API
    const pollResponse = await request.post('/api/polls', {
      data: {
        title: 'Secret Poll',
        status: 'open',
        access_mode: 'password',
        access_password: 'correct-password',
        questions: [{ type: 'text_single', text: 'Secret Question' }]
      }
    });
    const { poll } = await pollResponse.json();

    // 1. Try to access - should see password screen
    await freshPage.goto(`/${poll.public_id}`);
    await expect(freshPage.locator('h1')).toContainText('Password Required');

    // 2. Try wrong password
    await freshPage.fill('#access_password', 'wrong-password');
    await freshPage.click('button[type="submit"]');
    // Wait for the page to reload and show error
    await expect(freshPage.locator('.error-message')).toBeVisible();
    await expect(freshPage.locator('.error-message')).toContainText('Incorrect password');

    // 3. Enter correct password
    await freshPage.fill('#access_password', 'correct-password');
    await freshPage.click('button[type="submit"]');

    // 4. Should now see the poll
    await expect(freshPage.locator('h1')).toContainText('Secret Poll');
    await expect(freshPage.locator('text=Secret Question')).toBeVisible();
  });

});
