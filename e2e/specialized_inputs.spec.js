// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Specialized Input Types', () => {

  test('can create and vote on Star Rating question', async ({ page, freshPage }) => {
    await page.goto('/create');
    await page.fill('#pollTitle', 'Star Rating Test');

    // Add Star Rating question
    await page.click('#addQuestionBtn');
    await page.click('.type-btn[data-type="star"]');
    const q1 = page.locator('.question-wrapper').last();
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
    await page.click('.type-btn[data-type="grade"]');
    const q1 = page.locator('.question-wrapper').last();
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
    await page.click('.type-btn[data-type="yes_no_abstain"]');
    const q1 = page.locator('.question-wrapper').last();
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
    await page.click('.type-btn[data-type="approval"]');
    const q1 = page.locator('.question-wrapper').last();
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

  test('can create and vote on Distribution (point voting) question', async ({ page, freshPage }) => {
    await page.goto('/create');
    await page.fill('#pollTitle', 'Distribution Test');

    // Add Distribution question
    await page.click('#addQuestionBtn');
    await page.click('.type-btn[data-type="distribution"]');
    const q1 = page.locator('.question-wrapper').last();
    await q1.locator('.question-title-input').fill('Distribute 100 points');

    // Add a third option
    await q1.locator('.btn-add-option').click();
    await q1.locator('.option-label-input').nth(0).fill('Project A');
    await q1.locator('.option-label-input').nth(1).fill('Project B');
    await q1.locator('.option-label-input').nth(2).fill('Project C');

    // Check default budget is 100
    await expect(q1.locator('.setting-budget')).toHaveValue('100');

    await page.click('#publishBtn');
    await expect(page).toHaveURL(/\/admin\//);
    const publicUrl = await page.locator('#publicLink').inputValue();

    // Vote
    await freshPage.goto(publicUrl);

    // Verify budget display
    await expect(freshPage.locator('.budget-remaining')).toHaveText('100');
    await expect(freshPage.locator('.budget-total')).toHaveText('100');

    // Verify we have 3 distribution rows
    await expect(freshPage.locator('.distribution-row')).toHaveCount(3);

    // Add points to Project A using +10 button (budget >= 50, so big buttons exist)
    const projectA = freshPage.locator('.distribution-row', { hasText: 'Project A' });
    await projectA.locator('.dist-plus-big').click(); // +10
    await projectA.locator('.dist-plus-big').click(); // +10
    await projectA.locator('.dist-plus-big').click(); // +10
    await expect(projectA.locator('.dist-input')).toHaveValue('30');

    // Remaining should be 70
    await expect(freshPage.locator('.budget-remaining')).toHaveText('70');

    // Add points to Project B using +1 button
    const projectB = freshPage.locator('.distribution-row', { hasText: 'Project B' });
    await projectB.locator('.dist-plus').click(); // +1
    await projectB.locator('.dist-plus').click(); // +1
    await projectB.locator('.dist-plus').click(); // +1
    await expect(projectB.locator('.dist-input')).toHaveValue('3');

    // Remaining should be 67
    await expect(freshPage.locator('.budget-remaining')).toHaveText('67');

    // Use input field for Project C
    const projectC = freshPage.locator('.distribution-row', { hasText: 'Project C' });
    await projectC.locator('.dist-input').fill('67');
    await projectC.locator('.dist-input').blur();

    // Remaining should be 0
    await expect(freshPage.locator('.budget-remaining')).toHaveText('0');

    // Verify plus buttons are now disabled (budget exhausted)
    await expect(projectA.locator('.dist-plus')).toBeDisabled();
    await expect(projectB.locator('.dist-plus')).toBeDisabled();
    await expect(projectC.locator('.dist-plus')).toBeDisabled();

    // Minus buttons should still work
    await expect(projectA.locator('.dist-minus')).not.toBeDisabled();

    // Submit
    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();
  });

  test('enforces distribution requireAll constraint', async ({ page, freshPage }) => {
    await page.goto('/create');
    await page.fill('#pollTitle', 'Distribution RequireAll Test');

    await page.click('#addQuestionBtn');
    await page.click('.type-btn[data-type="distribution"]');
    const q1 = page.locator('.question-wrapper').last();
    await q1.locator('.question-title-input').fill('Use all 30 points');

    // Set budget to 30 and require all (30 is in the 25-49 range, so +5 buttons)
    await q1.locator('.setting-budget').fill('30');
    await q1.locator('.setting-require-all').check();

    await page.click('#publishBtn');
    const publicUrl = await page.locator('#publicLink').inputValue();

    // Vote
    await freshPage.goto(publicUrl);

    // Verify big steps are -5/+5 (budget 30 is in range 25-49)
    await expect(freshPage.locator('.dist-plus-big').first()).toContainText('+5');

    // Add only 20 points (not all 30)
    const option1 = freshPage.locator('.distribution-row').first();
    await option1.locator('.dist-input').fill('20');
    await option1.locator('.dist-input').blur();

    // Try to submit - should fail because we require all 30 points
    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Please use all 30 points')).toBeVisible();

    // Use remaining 10 points
    const option2 = freshPage.locator('.distribution-row').nth(1);
    await option2.locator('.dist-input').fill('10');
    await option2.locator('.dist-input').blur();

    // Now submit should succeed (20 + 10 = 30)
    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();
  });

});
