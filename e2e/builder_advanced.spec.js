// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Advanced Builder Features', () => {

  test('can duplicate a question', async ({ page }) => {
    await page.goto('/create');

    // Add first question
    await page.click('#addQuestionBtn');
    const q1 = page.locator('.question-wrapper').first();
    await q1.locator('.question-title-input').fill('Original Question');

    // Duplicate it
    await q1.locator('.copy-question').click();

    // Should have two questions now
    await expect(page.locator('.question-wrapper')).toHaveCount(2);
    await expect(page.locator('.question-wrapper').nth(1).locator('.question-title-input')).toHaveValue('Original Question (copy)');
  });

  test('can reorder questions via toolbar', async ({ page }) => {
    await page.goto('/create');

    // Add two questions
    await page.click('#addQuestionBtn');
    await page.locator('.question-title-input').fill('First');
    // Collapse it by clicking outside or adding another
    await page.click('#addQuestionBtn');
    await page.locator('.question-wrapper').last().locator('.question-title-input').fill('Second');

    // Second question is active. Move it up.
    await page.locator('.question-wrapper').last().locator('.move-up').click();

    // Verify order
    // The active question (formerly Second) should now be first and still in editor mode
    await expect(page.locator('.question-wrapper').first().locator('.question-title-input')).toHaveValue('Second');
    // The collapsed question (formerly First) should now be last and in display mode
    await expect(page.locator('.question-wrapper').last().locator('.question-display .question-text')).toContainText('First');
  });

  test('saves draft to localStorage and can clear it', async ({ page }) => {
    await page.goto('/create');
    await page.fill('#pollTitle', 'Draft Poll');
    await page.click('#addQuestionBtn');
    await page.locator('.question-title-input').fill('Draft Question');

    // Wait for auto-save (every 5 seconds)
    // Or we can manually trigger it if we knew how, but let's just wait a bit or reload and check if it stayed
    // Actually builder.js calls saveToLocalStorage on every markDirty() too!
    await page.reload();

    await expect(page.locator('#pollTitle')).toHaveValue('Draft Poll');
    await expect(page.locator('.question-display .question-text')).toContainText('Draft Question');

    // Clear draft
    await page.click('#clearBtn');
    // Confirm dialog
    // wait, clearBtn doesn't have a confirm in JS, it just resets state.
    await expect(page.locator('#pollTitle')).toHaveValue('Untitled Poll');
    await expect(page.locator('.question-wrapper')).toHaveCount(0);
  });

  test('can add and edit a section header', async ({ page }) => {
    await page.goto('/create');

    await page.click('#addQuestionBtn');
    const q1 = page.locator('.question-wrapper').first();
    await q1.locator('.question-type-select').selectOption('section_header');

    await q1.locator('.question-title-input').fill('New Section');
    await q1.locator('.question-description-input').fill('Section Description');

    // Click outside to collapse
    await page.click('h1');

    await expect(page.locator('.section-header-text')).toContainText('New Section');
    await expect(page.locator('.section-header-description')).toContainText('Section Description');
    // Verify no number
    await expect(page.locator('.section-header')).not.toContainText('1.');
  });
});
