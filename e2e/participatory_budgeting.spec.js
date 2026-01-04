// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Participatory Budgeting', () => {

  test('can create and vote on PB question with costs', async ({ page, freshPage }) => {
    await page.goto('/create');
    await page.fill('#pollTitle', 'PB Test Poll');

    // Add PB question
    await page.click('#addQuestionBtn');
    await page.click('.type-btn[data-type="participatory_budgeting"]');
    const q1 = page.locator('.question-wrapper').last();
    await q1.locator('.question-title-input').fill('Project Funding');

    // Add 3 projects with costs
    // Option 1
    await q1.locator('.option-label-input').nth(0).fill('Park Renovation');
    await q1.locator('.option-cost-input').nth(0).fill('50000');
    
    // Option 2
    await q1.locator('.option-label-input').nth(1).fill('Library Books');
    await q1.locator('.option-cost-input').nth(1).fill('10000');

    // Add 3rd option
    await q1.locator('.btn-add-option').click();
    await q1.locator('.option-label-input').nth(2).fill('New Sidewalks');
    await q1.locator('.option-cost-input').nth(2).fill('30000');

    // Set max to 2 (voter can pick up to 2 projects regardless of cost)
    await q1.locator('.setting-max').fill('2');
    // Set currency
    await q1.locator('.setting-currency').fill('$');

    await page.click('#publishBtn');
    const publicUrl = await page.locator('#publicLink').inputValue();

    // Vote
    await freshPage.goto(publicUrl);
    
    // Check costs are displayed
    await expect(freshPage.locator('text=$50000')).toBeVisible();
    await expect(freshPage.locator('text=$10000')).toBeVisible();
    await expect(freshPage.locator('text=$30000')).toBeVisible();

    // Select Park ($50000)
    await freshPage.check('text=Park Renovation');
    
    // Select Library ($10000)
    await freshPage.check('text=Library Books');
    
    // Sidewalks ($30000) should be DISABLED because we already selected 2 projects (max=2)
    const sidewalksCheckbox = freshPage.locator('.pb-option-card', { hasText: 'New Sidewalks' }).locator('input[type="checkbox"]');
    await expect(sidewalksCheckbox).toBeDisabled();

    // Submit valid
    await freshPage.click('button[type="submit"]');
    await expect(freshPage.locator('text=Thank you')).toBeVisible();
  });

});
