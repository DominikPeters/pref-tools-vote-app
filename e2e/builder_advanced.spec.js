// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Advanced Builder Features', () => {

  test('can duplicate a question', async ({ page }) => {
    await page.goto('/create');

    // Add first question
    await page.click('#addQuestionBtn');
    await page.click('.type-btn[data-type="single_choice"]');
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
    await page.click('.type-btn[data-type="single_choice"]');
    await page.locator('.question-title-input').fill('First');
    // Collapse it by clicking outside or adding another
    await page.click('#addQuestionBtn');
    await page.click('.type-btn[data-type="single_choice"]');
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
    await page.click('.type-btn[data-type="single_choice"]');
    await page.locator('.question-title-input').fill('Draft Question');

    // Wait for auto-save (every 5 seconds)
    // Or we can manually trigger it if we knew how, but let's just wait a bit or reload and check if it stayed
    // Actually builder.js calls saveToLocalStorage on every markDirty() too!
    await page.reload();

    await expect(page.locator('#pollTitle')).toHaveValue('Draft Poll');
    await expect(page.locator('.question-display .question-text')).toContainText('Draft Question');

    // Clear draft
    await page.click('#clearBtn');
    // Confirm modal
    await page.click('.confirm-modal .btn-confirm');
    
    await expect(page.locator('#pollTitle')).toHaveValue('Untitled Poll');
    await expect(page.locator('.question-wrapper')).toHaveCount(0);
  });

  test('can add and edit a section header', async ({ page }) => {
    await page.goto('/create');

    await page.click('#addQuestionBtn');
    await page.click('.type-btn[data-type="section_header"]');
    const q1 = page.locator('.question-wrapper').first();

    await q1.locator('.question-title-input').fill('New Section');
    await q1.locator('.btn-add-description').click();
    await q1.locator('.question-description-input').fill('Section Description');

    // Click outside to collapse
    await page.click('h1');

    await expect(page.locator('.section-header-text')).toContainText('New Section');
    await expect(page.locator('.section-header-description')).toContainText('Section Description');
    // Verify no number
    await expect(page.locator('.section-header')).not.toContainText('1.');
  });

  test('can undo question deletion', async ({ page }) => {
    await page.goto('/create');

    // Add a question
    await page.click('#addQuestionBtn');
    await page.click('.type-btn[data-type="single_choice"]');
    await page.locator('.question-title-input').fill('Delete Me');
    
    // Collapse it
    await page.click('h1');
    
    const q1 = page.locator('.question-wrapper').first();
    await expect(q1).toBeVisible();

    // Click on it to edit and show delete button
    await q1.click();
    await q1.locator('.delete-question').click();

    // Verify hidden
    await expect(q1).not.toBeVisible();

    // Click Undo in toast
    await page.click('.toast-undo button');

    // Verify restored
    await expect(q1).toBeVisible();
    await expect(q1.locator('.question-title-input')).toHaveValue('Delete Me');
  });

  test('can change question type via dropdown after creation', async ({ page }) => {
    await page.goto('/create');

    // Add a single choice question
    await page.click('#addQuestionBtn');
    await page.click('.type-btn[data-type="single_choice"]');
    const q1 = page.locator('.question-wrapper').first();
    await q1.locator('.question-title-input').fill('Type Change Test');

    // Verify it starts as single_choice (radio indicators)
    await expect(q1.locator('.indicator-radio')).toHaveCount(2);

    // Change to approval via dropdown
    await q1.locator('.question-type-select').selectOption('approval');

    // Verify it changed to approval (checkbox indicators)
    await expect(q1.locator('.indicator-checkbox')).toHaveCount(2);

    // Change to star rating
    await q1.locator('.question-type-select').selectOption('star');

    // Verify star settings appear
    await expect(q1.locator('.setting-star-count')).toBeVisible();

    // Change to text (no options)
    await q1.locator('.question-type-select').selectOption('text_single');

    // Verify options editor is gone
    await expect(q1.locator('.editor-options')).not.toBeVisible();
  });

  test('can undo option deletion', async ({ page }) => {
    await page.goto('/create');

    // Add a question
    await page.click('#addQuestionBtn');
    await page.click('.type-btn[data-type="single_choice"]');
    const q1 = page.locator('.question-wrapper').first();
    
    // Add 3rd option (min 2 required)
    await q1.locator('.btn-add-option').click();
    const option3 = q1.locator('.option-editor').nth(2);
    await option3.locator('.option-label-input').fill('Undo Option');

    // Delete 3rd option
    await option3.locator('.delete-option').click();

    // Verify only 2 options left
    await expect(q1.locator('.option-editor')).toHaveCount(2);

    // Click Undo in toast
    await page.click('.toast-undo button');

    // Verify restored
    await expect(q1.locator('.option-editor')).toHaveCount(3);
    await expect(q1.locator('.option-editor').nth(2).locator('.option-label-input')).toHaveValue('Undo Option');
  });
});
