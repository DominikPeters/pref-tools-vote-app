// @ts-check
const { test, expect } = require('./fixtures');

test.describe('Results and Reports System', () => {

  test('default analyses are set up automatically and can be managed', async ({ page, api }) => {
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

    // 3. Default analyses were added automatically at poll creation
    const q1Section = page.locator('.result-question').first();
    const q2Section = page.locator('.result-question').nth(1);

    await expect(q1Section.locator('.report-card[data-type="choice_counts"]')).toBeVisible();
    await expect(q1Section.locator('text=Option A')).toBeVisible();
    await expect(q2Section.locator('.report-card[data-type="voting_rule_winner"]')).toBeVisible();
    await expect(q2Section.locator('.report-card[data-type="borda_scores"]')).toBeVisible();

    // The setup banner explains the automatic defaults and can be dismissed
    const banner = page.locator('.results-setup-banner');
    await expect(banner).toBeVisible();
    await banner.locator('.btn-close').click();
    await expect(banner).not.toBeVisible();

    // 4. Add another analysis for Q1 through the intent wizard
    await q1Section.locator('.btn-add-report').click();
    const drawer1 = q1Section.locator('.report-drawer');
    await expect(drawer1).toBeVisible();

    // Step 1: intents are shown
    await expect(drawer1.locator('.wizard-question')).toContainText('What do you want to show?');
    await drawer1.locator('.intent-card[data-intent="who_wins"]').click();

    // Step 2: matching analyses; add the approval winner (no config needed)
    const createPromise1 = page.waitForResponse(r => r.url().includes('/api/polls/') && r.url().includes('/reports') && r.request().method() === 'POST');
    await drawer1.locator('.report-type-card[data-type="approval_winner"]').click();
    await createPromise1;
    await expect(q1Section.locator('.report-card[data-type="approval_winner"]')).toBeVisible({ timeout: 1000 });

    // 5. Add a second voting-rule winner for Q2, configured via the rule picker
    await q2Section.locator('.btn-add-report').click();
    const drawer2 = q2Section.locator('.report-drawer');
    await expect(drawer2).toBeVisible();
    await drawer2.locator('.intent-card[data-intent="who_wins"]').click();
    await drawer2.locator('.report-type-card[data-type="voting_rule_winner"]').click();

    // Config step shows the rule picker with descriptions; pick IRV
    const irvRadio = drawer2.locator('.rule-radio-item input[value="irv"]');
    await expect(irvRadio).toBeVisible();
    const createPromise2 = page.waitForResponse(r => r.url().includes('/api/polls/') && r.url().includes('/reports') && r.request().method() === 'POST');
    await irvRadio.check();
    await drawer2.locator('button[type="submit"]').click();
    await createPromise2;
    await expect(q2Section.locator('.report-card[data-type="voting_rule_winner"]')).toHaveCount(2, { timeout: 1000 });

    // 6. The expert path: browse the full catalog and navigate back
    await q1Section.locator('.btn-add-report').click();
    const drawer3 = q1Section.locator('.report-drawer');
    await drawer3.locator('.browse-all').click();
    await expect(drawer3.locator('.report-category-header').first()).toBeVisible();
    await drawer3.locator('.btn-drawer-back').click();
    await expect(drawer3.locator('.wizard-question')).toBeVisible();
    await drawer3.locator('.btn-close').click();

    // 7. Delete a default report; the one-click restore button appears
    const reportToDelete = q1Section.locator('.report-card[data-type="choice_counts"]');
    await reportToDelete.locator('.delete-report').click();
    await expect(q1Section.locator('.report-card[data-type="choice_counts"]')).not.toBeVisible();

    // After the undo window passes, the server delete makes the
    // "Add recommended analyses" button appear
    await expect(q1Section.locator('.btn-add-recommended')).toBeVisible({ timeout: 10000 });

    // Clicking it restores the missing default
    const restorePromise = page.waitForResponse(r => r.url().includes('/api/polls/') && r.url().includes('/reports') && r.request().method() === 'POST');
    await q1Section.locator('.btn-add-recommended').click();
    await restorePromise;
    await expect(q1Section.locator('.report-card[data-type="choice_counts"]')).toBeVisible({ timeout: 1000 });
    await expect(q1Section.locator('.btn-add-recommended')).not.toBeVisible();
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

    // Add pairwise report through the wizard ("How did people vote?")
    await page.locator('.btn-add-report').click();
    await page.locator('.intent-card[data-intent="how_voted"]').click();
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
