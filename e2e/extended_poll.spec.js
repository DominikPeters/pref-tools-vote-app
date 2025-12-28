// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Extended Poll Features', () => {

  test('can create, edit, close, and delete a poll', async ({ page }) => {
    // 1. Create a poll
    await page.goto('/create');
    await page.fill('#pollTitle', 'Management Test Poll');
    await page.click('#addQuestionBtn');
    const mq1 = page.locator('.question-wrapper').last();
    await mq1.locator('.question-title-input').fill('Initial Question');
    await page.click('#publishBtn');

    // Should be on admin panel
    await expect(page).toHaveURL(/\/admin\//);
    const adminUrl = page.url();
    const publicUrlInput = page.locator('#publicLink');
    const publicUrl = await publicUrlInput.inputValue();

    // 2. Edit the poll
    await page.click('#editPoll');
    await expect(page).toHaveURL(/\/edit$/);
    await page.fill('#pollTitle', 'Updated Poll Title');

    // Add another question
    await page.click('#addQuestionBtn');
    const mq2 = page.locator('.question-wrapper').last();
    await mq2.locator('.question-title-input').fill('Second Question');

    await page.click('#publishBtn');

    // Back to admin panel, check title
    await expect(page).toHaveURL(adminUrl);
    await expect(page.locator('.poll-title')).toContainText('Updated Poll Title');

    // 3. Close the poll
    await page.click('#closePoll');
    await expect(page.locator('.poll-status')).toContainText('Closed');

    // Check public page - should say closed
    await page.goto(publicUrl);
    await expect(page.locator('h2:has-text("Closed")')).toBeVisible();

    // 4. Reopen the poll
    await page.goto(adminUrl);
    await page.click('#reopenPoll');
    await expect(page.locator('.poll-status')).toContainText('Open');

    // Check public page - should be open again
    await page.goto(publicUrl);
    await expect(page.locator('h1')).toContainText('Updated Poll Title');

    // 5. Delete the poll
    await page.goto(adminUrl);
    page.on('dialog', dialog => dialog.accept());
    await page.click('#deletePoll');

    // Should redirect to dashboard or home
    await page.waitForURL(url => url.pathname === '/' || url.pathname === '/dashboard');
    await expect(page.locator('text=Updated Poll Title')).not.toBeVisible();
  });

  test('can use multiple question types and view results', async ({ page, freshPage }) => {
    // Create poll with multiple question types
    await page.goto('/create');
    await page.fill('#pollTitle', 'Multi-type Poll');

    // Set results to be visible during voting
    await page.check('input[name="visibilityTiming"][value="during"]');
    await page.check('input[name="visibility"][value="anonymous"]');

    // Question 1: Single Choice (default)
    await page.click('#addQuestionBtn');
    const q1 = page.locator('.question-wrapper').last();
    await q1.locator('.question-title-input').fill('Single Choice Question');
    await q1.locator('.option-label-input').nth(0).fill('Choice A');
    await q1.locator('.option-label-input').nth(1).fill('Choice B');

    // Question 2: Approval
    await page.click('#addQuestionBtn');
    const q2 = page.locator('.question-wrapper').last();
    await q2.locator('.question-type-select').selectOption('approval');
    await q2.locator('.question-title-input').fill('Approval Question');
    await q2.locator('.btn-add-option').click();
    await q2.locator('.btn-add-option').click();
    await q2.locator('.option-label-input').nth(2).fill('Approve 1');
    await q2.locator('.option-label-input').nth(3).fill('Approve 2');

    // Question 3: Ranking
    await page.click('#addQuestionBtn');
    const q3 = page.locator('.question-wrapper').last();
    await q3.locator('.question-type-select').selectOption('ranking');
    await q3.locator('.question-title-input').fill('Ranking Question');
    await q3.locator('.btn-add-option').click();
    await q3.locator('.btn-add-option').click();
    await q3.locator('.option-label-input').nth(2).fill('Rank 1');
    await q3.locator('.option-label-input').nth(3).fill('Rank 2');

    // Question 4: Text
    await page.click('#addQuestionBtn');
    const q4 = page.locator('.question-wrapper').last();
    await q4.locator('.question-type-select').selectOption('text_single');
    await q4.locator('.question-title-input').fill('Text Question');

    await page.click('#publishBtn');
    await expect(page).toHaveURL(/\/admin\//);
    const publicUrl = await page.locator('#publicLink').inputValue();

    // Vote on the poll
    await freshPage.goto(publicUrl);

    // Answer Q1
    await freshPage.click('text=Choice A');

    // Answer Q2
    await freshPage.click('text=Approve 1');
    await freshPage.click('text=Approve 2');

    // Answer Q3
    // Default order is Rank 1, Rank 2.

    // Answer Q4
    await freshPage.fill('input[type="text"]', 'Hello E2E');

    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();

    // View results
    await freshPage.goto(`${publicUrl}/results`);
    await freshPage.waitForSelector('.result-question');
    await expect(freshPage.locator('h1')).toContainText('Results');
    await expect(freshPage.locator('text=Choice A')).toBeVisible();
    await expect(freshPage.locator('text=Approve 1')).toBeVisible();
    await expect(freshPage.locator('text=Hello E2E')).toBeVisible();
  });

  test('collects voter name when enabled', async ({ page, freshPage }) => {
    await page.goto('/create');
    await page.fill('#pollTitle', 'Name Collection Poll');
    await page.check('#collectName');

    await page.click('#addQuestionBtn');
    const nq1 = page.locator('.question-wrapper').last();
    await nq1.locator('.question-title-input').fill('Some Question');

    await page.click('#publishBtn');
    await expect(page).toHaveURL(/\/admin\//);
    const adminUrl = page.url();
    const publicUrl = await page.locator('#publicLink').inputValue();

    // Vote with name
    await freshPage.goto(publicUrl);
    await freshPage.fill('#voterName', 'John Doe');
    await freshPage.click('text=Option 1');
    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();

    // Check admin panel for name
    await page.goto(adminUrl);
    await page.waitForSelector('.response-item');
    await expect(page.locator('.voter-name')).toContainText('John Doe');
  });

});