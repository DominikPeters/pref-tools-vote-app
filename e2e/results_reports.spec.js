// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Results and Reports System', () => {

  test('can manage reports on the results page', async ({ page, api }) => {
    // 1. Create a poll with responses via API
    const pollResponse = await api.post('/api/polls', {
      data: {
        title: 'Report Test Poll',
        status: 'open',
        questions: [
          {
            type: 'single_choice',
            text: 'Question 1',
            options: [{ label: 'Option A' }, { label: 'Option B' }]
          },
          {
            type: 'ranking',
            text: 'Question 2',
            options: [{ label: 'Rank X' }, { label: 'Rank Y' }, { label: 'Rank Z' }]
          }
        ]
      }
    });
    const { poll } = await pollResponse.json();
    const adminToken = poll.admin_token;
    const publicId = poll.public_id;

    // Add some responses via API
    await api.post(`/api/polls/${publicId}/responses`, {
      data: { answers: { [poll.questions[0].id]: poll.questions[0].options[0].id } }
    });
    await api.post(`/api/polls/${publicId}/responses`, {
      data: { answers: { [poll.questions[1].id]: [poll.questions[1].options[0].id, poll.questions[1].options[1].id, poll.questions[1].options[2].id] } }
    });

    // 2. Go to admin results page
    await page.goto(`/${publicId}/admin/${adminToken}/results`);
    await expect(page.locator('.results-header h1')).toContainText('Report Test Poll');
    await expect(page.locator('.results-label')).toContainText('Results & Analysis');

    // 3. Add a Choice Counts report for Q1
    const q1Section = page.locator('.result-question').first();
    await q1Section.locator('.btn-add-report').click();
    
    const drawer1 = q1Section.locator('.report-drawer');
    await expect(drawer1).toBeVisible();
    
    // Wait for the API response after clicking the card
    const createPromise1 = page.waitForResponse(r => r.url().includes('/api/polls/') && r.url().includes('/reports') && r.request().method() === 'POST');
    await drawer1.locator('.report-type-card[data-type="choice_counts"]').click();
    await createPromise1;

    // Verify report appears
    await expect(q1Section.locator('.report-card[data-type="choice_counts"]')).toBeVisible({ timeout: 1000 });
    await expect(q1Section.locator('text=Option A')).toBeVisible();

    // 4. Add a Borda Scores report for Q2
    const q2Section = page.locator('.result-question').nth(1);
    await q2Section.locator('.btn-add-report').click();
    
    const drawer2 = q2Section.locator('.report-drawer');
    await expect(drawer2).toBeVisible();
    
    const createPromise2 = page.waitForResponse(r => r.url().includes('/api/polls/') && r.url().includes('/reports') && r.request().method() === 'POST');
    await drawer2.locator('.report-type-card[data-type="borda_scores"]').click();
    await createPromise2;

    // Verify Borda report
    await expect(q2Section.locator('.report-card[data-type="borda_scores"]')).toBeVisible({ timeout: 1000 });
    await expect(q2Section.locator('text=Rank X')).toBeVisible();

    // 5. Delete a report
    const reportToDelete = q1Section.locator('.report-card').first();
    await reportToDelete.locator('.delete-report').click();
    
    // Verify report is hidden (it uses showUndoToast, so it's hidden from UI)
    await expect(q1Section.locator('.report-card[data-type="choice_counts"]')).not.toBeVisible();
  });

  test('can add and view pairwise margins report', async ({ page, api }) => {
    // Create ranking poll
    const pollResponse = await api.post('/api/polls', {
      data: {
        title: 'Pairwise Test',
        status: 'open',
        questions: [
          {
            type: 'ranking',
            text: 'Rank candidates',
            options: [{ label: 'Alice' }, { label: 'Bob' }]
          }
        ]
      }
    });
    const { poll } = await pollResponse.json();
    
    // Add a vote: Alice > Bob
    await api.post(`/api/polls/${poll.public_id}/responses`, {
      data: { answers: { [poll.questions[0].id]: [poll.questions[0].options[0].id, poll.questions[0].options[1].id] } }
    });

    await page.goto(`/${poll.public_id}/admin/${poll.admin_token}/results`);
    
    // Add pairwise report
    await page.locator('.btn-add-report').click();
    const createPromise = page.waitForResponse(r => r.url().includes('/api/polls/') && r.url().includes('/reports') && r.request().method() === 'POST');
    await page.locator('.report-type-card[data-type="pairwise_margins"]').click();
    await createPromise;

    // Verify SVG structure
    const graph = page.locator('.pairwise-graph');
    await expect(graph).toBeVisible();
    await expect(page.locator('.candidate-label:has-text("Alice")')).toBeVisible();
    await expect(page.locator('.candidate-label:has-text("Bob")')).toBeVisible();
    
    // Margin label should show 1
    await expect(page.locator('.margin-label')).toContainText('1');
  });

});