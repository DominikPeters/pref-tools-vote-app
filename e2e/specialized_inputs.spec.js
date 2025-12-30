// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Specialized Input Types', () => {

  test('can create and vote on Star Rating question', async ({ page, freshPage }) => {
    await page.goto('/create');
    await page.fill('#pollTitle', 'Star Rating Test');

    // Add Star Rating question
    await page.click('#addQuestionBtn');
    const q1 = page.locator('.question-wrapper').last();
    await q1.locator('.question-type-select').selectOption('star');
    await q1.locator('.question-title-input').fill('Rate these fruits');

    // Configure 3 options
    await q1.locator('.btn-add-option').click();
    await q1.locator('.option-label-input').nth(0).fill('Apple');
    await q1.locator('.option-label-input').nth(1).fill('Banana');
    await q1.locator('.option-label-input').nth(2).fill('Cherry');

    // Set star count to 3 for testing
    await q1.locator('.setting-star-count').fill('3');

    await page.click('#publishBtn');
    await expect(page).toHaveURL(/\/admin\//);
    const publicUrl = await page.locator('#publicLink').inputValue();

    // Set results visibility to public
    await page.selectOption('#settingVisibility', 'full');
    await page.click('#saveSettings');
    await expect(page.locator('text=Settings saved')).toBeVisible();

    // Vote
    await freshPage.goto(publicUrl);
    await expect(freshPage.locator('.star-rating')).toHaveCount(3);

    // Rate Apple 3 stars
    const appleRating = freshPage.locator('.star-row', { hasText: 'Apple' }).locator('.star-rating');
    await appleRating.locator('.star[data-value="3"]').click();
    await expect(appleRating.locator('.star[data-value="3"]')).toHaveClass(/active/);
    await expect(appleRating.locator('.star[data-value="1"]')).toHaveClass(/active/);

    // Rate Banana 1 star
    const bananaRating = freshPage.locator('.star-row', { hasText: 'Banana' }).locator('.star-rating');
    await bananaRating.locator('.star[data-value="1"]').click();

    // Submit
    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();

    // Verify in admin results
    await page.reload();
    await page.goto(`${publicUrl}/results`);
    await page.waitForSelector('.star-result-row');
    await expect(page.locator('text=Apple')).toBeVisible();
    // (Note: Results rendering depends on implementation, but checking visibility is a start)
  });

  test('can create and vote on Grade (Majority Judgment) question', async ({ page, freshPage }) => {
    await page.goto('/create');
    await page.fill('#pollTitle', 'Grades Test');

    await page.click('#addQuestionBtn');
    const q1 = page.locator('.question-wrapper').last();
    await q1.locator('.question-type-select').selectOption('grade');
    await q1.locator('.question-title-input').fill('Evaluate candidates');

    // Use a small preset for button mode (Pass/Fail)
    await q1.locator('.setting-grade-preset').selectOption('pass-fail');

    await page.click('#publishBtn');
    const publicUrl = await page.locator('#publicLink').inputValue();

    // Vote
    await freshPage.goto(publicUrl);
    const candidate1 = freshPage.locator('.grade-row').first();
    await candidate1.locator('.grade-btn[data-value="pass"]').click();
    await expect(candidate1.locator('.grade-btn[data-value="pass"]')).toHaveClass(/active/);

    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();
  });

  test('can create and vote on Yes/No/Abstain question', async ({ page, freshPage }) => {
    await page.goto('/create');
    await page.fill('#pollTitle', 'YNA Test');

    await page.click('#addQuestionBtn');
    const q1 = page.locator('.question-wrapper').last();
    await q1.locator('.question-type-select').selectOption('yes_no_abstain');
    await q1.locator('.question-title-input').fill('Proposals');

    await page.click('#publishBtn');
    const publicUrl = await page.locator('#publicLink').inputValue();

    // Vote
    await freshPage.goto(publicUrl);
    const proposal1 = freshPage.locator('.yna-row').first();
    await proposal1.locator('.yna-btn.yes').click();
    await expect(proposal1.locator('.yna-btn.yes')).toHaveClass(/active/);

    await proposal1.locator('.yna-btn.no').click();
    await expect(proposal1.locator('.yna-btn.no')).toHaveClass(/active/);
    await expect(proposal1.locator('.yna-btn.yes')).not.toHaveClass(/active/);

    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();
  });

  test('enforces approval min/max constraints', async ({ page, freshPage }) => {
    await page.goto('/create');
    await page.fill('#pollTitle', 'Approval Constraint Test');

    await page.click('#addQuestionBtn');
    const q1 = page.locator('.question-wrapper').last();
    await q1.locator('.question-type-select').selectOption('approval');
    await q1.locator('.question-title-input').fill('Select 2 to 3');

    // Add 4 options
    await q1.locator('.btn-add-option').click();
    await q1.locator('.btn-add-option').click();

    // Set min=2, max=3
    await q1.locator('.setting-min').fill('2');
    await q1.locator('.setting-max').fill('3');

    await page.click('#publishBtn');
    const publicUrl = await page.locator('#publicLink').inputValue();

    // Vote
    await freshPage.goto(publicUrl);

    // Try to submit with only 1 selected
    await freshPage.check('text=Option 1');
    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=at least 2')).toBeVisible();

    // Select more
    await freshPage.check('text=Option 2');
    await freshPage.check('text=Option 3');

    // Check that 4th option is disabled due to max=3
    await expect(freshPage.locator('text=Option 4').locator('xpath=..').locator('input')).toBeDisabled();

    // Submit valid
    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();
  });

});
