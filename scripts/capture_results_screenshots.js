/**
 * Standalone script to capture high-quality screenshots of the results page.
 * 
 * Usage:
 * 1. Start the server: php -S localhost:8005
 * 2. Run this script: node scripts/capture_results_screenshots.js
 */

const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const BASE_URL = 'http://localhost:8005';
const SCREENSHOT_DIR = path.join(__dirname, '..', 'screenshots');

async function captureResultsScreenshots() {
  console.log('🚀 Starting results screenshot capture...');

  if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR);
  }

  const pollData = {
    title: 'Product Strategy & Team Feedback',
    description: 'Help us prioritize our roadmap and share your thoughts on the recent release.',
    voting_mode: 'open',
    status: 'open',
    visibility: 'full',
    questions: [
      {
        type: 'single_choice',
        text: 'Which feature is most critical for your workflow right now?',
        options: [
          { label: 'Real-time collaboration' },
          { label: 'Offline mode' },
          { label: 'Advanced reporting' },
          { label: 'Mobile app' }
        ],
        required: false,
        sort_order: 1
      },
      {
        type: 'approval',
        text: 'Which technologies should we prioritize for our next integrations?',
        description: 'Select all that apply (max 3)',
        options: [
          { label: 'GitHub' },
          { label: 'Slack' },
          { label: 'Discord' },
          { label: 'Jira' },
          { label: 'Notion' }
        ],
        settings: { min: 1, max: 3 },
        required: false,
        sort_order: 2
      },
      {
        type: 'ranking',
        text: 'Rank our core values in order of importance to you:',
        options: [
          { label: 'User Privacy' },
          { label: 'Performance' },
          { label: 'Ease of Use' },
          { label: 'Customizability' }
        ],
        required: true,
        sort_order: 3
      },
      {
        type: 'ranking_truncated',
        text: 'Which of these experimental features would you like to see first?',
        description: 'Rank your top 3 choices from the available list.',
        options: [
          { label: 'Dark Mode' },
          { label: 'API Access' },
          { label: 'White-labeling' },
          { label: 'AI Summaries' },
          { label: 'Native Desktop App' },
          { label: 'Keyboard Shortcuts' },
          { label: 'Multi-factor Auth' },
          { label: 'Single Sign-On' }
        ],
        settings: { min: 1, max: 3 },
        required: false,
        sort_order: 4
      },
      {
        type: 'ranking_with_ties',
        text: 'Evaluate the proposed UI layouts:',
        options: [
          { label: 'Layout A: Minimalist' },
          { label: 'Layout B: Data-dense' },
          { label: 'Layout C: Card-based' }
        ],
        required: false,
        sort_order: 5
      },
      {
        type: 'star',
        text: 'How would you rate the following aspects of our support?',
        options: [
          { label: 'Response Time' },
          { label: 'Technical Knowledge' },
          { label: 'Friendliness' }
        ],
        settings: { starCount: 5 },
        required: false,
        sort_order: 6
      },
      {
        type: 'grade',
        text: 'Rate the quality of our documentation:',
        options: [
          { label: 'Installation Guide' },
          { label: 'API Reference' }
        ],
        settings: { preset: 'default', grades: ['Excellent', 'Very Good', 'Good', 'Fair', 'Poor', 'Reject'] },
        required: false,
        sort_order: 7
      },
      {
        type: 'grade',
        text: 'How do you feel about the following proposed changes?',
        options: [
          { label: 'Remove legacy export' },
          { label: 'New sidebar navigation' }
        ],
        settings: { preset: 'plus-minus', grades: ['++', '+', '0', '−', '−−'] },
        required: false,
        sort_order: 8
      },
      {
        type: 'yes_no_abstain',
        text: 'Should we move to a monthly release cycle?',
        options: [{ label: 'I support more frequent, smaller updates.' }],
        settings: { allowAbstain: true },
        required: false,
        sort_order: 9
      }
    ]
  };

  let browser;
  try {
    browser = await chromium.launch();
    const context = await browser.newContext({
      viewport: { width: 800, height: 1200 },
      deviceScaleFactor: 2,
    });
    const page = await context.newPage();

    console.log('📝 Creating poll...');
    const response = await page.request.post(`${BASE_URL}/api/polls`, { data: pollData });
    if (!response.ok()) throw new Error(`Failed to create poll: ${response.status()}`);
    const result = await response.json();
    const publicId = result.poll.public_id;
    const adminToken = result.poll.admin_token;
    const questions = result.poll.questions;

    console.log(`🗳️ Submitting 4 responses...`);
    const responsesToSubmit = [
      // Response 1
      {
        answers: {
          [questions[0].id]: questions[0].options[0].id,
          [questions[1].id]: [questions[1].options[0].id, questions[1].options[1].id],
          [questions[2].id]: [questions[2].options[0].id, questions[2].options[1].id, questions[2].options[2].id, questions[2].options[3].id],
          [questions[3].id]: [questions[3].options[0].id, questions[3].options[1].id, questions[3].options[2].id],
          [questions[4].id]: { [questions[4].options[0].id]: 1, [questions[4].options[1].id]: 2, [questions[4].options[2].id]: 2 },
          [questions[5].id]: { [questions[5].options[0].id]: 5, [questions[5].options[1].id]: 4, [questions[5].options[2].id]: 5 },
          [questions[6].id]: { [questions[6].options[0].id]: 'excellent', [questions[6].options[1].id]: 'very good' },
          [questions[7].id]: { [questions[7].options[0].id]: '++', [questions[7].options[1].id]: '+' },
          [questions[8].id]: { [questions[8].options[0].id]: 'yes' },
        }
      },
      // Response 2
      {
        answers: {
          [questions[0].id]: questions[0].options[0].id,
          [questions[1].id]: [questions[1].options[0].id, questions[1].options[2].id],
          [questions[2].id]: [questions[2].options[1].id, questions[2].options[0].id, questions[2].options[3].id, questions[2].options[2].id],
          [questions[3].id]: [questions[3].options[1].id, questions[3].options[2].id, questions[3].options[4].id],
          [questions[4].id]: { [questions[4].options[1].id]: 1, [questions[4].options[0].id]: 1, [questions[4].options[2].id]: 2 },
          [questions[5].id]: { [questions[5].options[0].id]: 4, [questions[5].options[1].id]: 5, [questions[5].options[2].id]: 4 },
          [questions[6].id]: { [questions[6].options[0].id]: 'good', [questions[6].options[1].id]: 'excellent' },
          [questions[7].id]: { [questions[7].options[0].id]: '+', [questions[7].options[1].id]: '0' },
          [questions[8].id]: { [questions[8].options[0].id]: 'no' },
        }
      },
      // Response 3
      {
        answers: {
          [questions[0].id]: questions[0].options[2].id,
          [questions[1].id]: [questions[1].options[1].id, questions[1].options[3].id],
          [questions[2].id]: [questions[2].options[2].id, questions[2].options[0].id, questions[2].options[1].id, questions[2].options[3].id],
          [questions[3].id]: [questions[3].options[2].id, questions[3].options[0].id, questions[3].options[5].id],
          [questions[4].id]: { [questions[4].options[2].id]: 1, [questions[4].options[1].id]: 2, [questions[4].options[0].id]: 3 },
          [questions[5].id]: { [questions[5].options[0].id]: 3, [questions[5].options[1].id]: 3, [questions[5].options[2].id]: 5 },
          [questions[6].id]: { [questions[6].options[0].id]: 'very good', [questions[6].options[1].id]: 'good' },
          [questions[7].id]: { [questions[7].options[0].id]: '0', [questions[7].options[1].id]: '-' },
          [questions[8].id]: { [questions[8].options[0].id]: 'abstain' },
        }
      },
      // Response 4
      {
        answers: {
          [questions[0].id]: questions[0].options[1].id,
          [questions[1].id]: [questions[1].options[0].id, questions[1].options[4].id],
          [questions[2].id]: [questions[2].options[0].id, questions[2].options[3].id, questions[2].options[1].id, questions[2].options[2].id],
          [questions[3].id]: [questions[3].options[4].id, questions[3].options[1].id, questions[3].options[3].id],
          [questions[4].id]: { [questions[4].options[0].id]: 1, [questions[4].options[2].id]: 1, [questions[4].options[1].id]: 1 },
          [questions[5].id]: { [questions[5].options[0].id]: 5, [questions[5].options[1].id]: 5, [questions[5].options[2].id]: 5 },
          [questions[6].id]: { [questions[6].options[0].id]: 'excellent', [questions[6].options[1].id]: 'fair' },
          [questions[7].id]: { [questions[7].options[0].id]: '++', [questions[7].options[1].id]: '−−' },
          [questions[8].id]: { [questions[8].options[0].id]: 'yes' },
        }
      }
    ];

    for (const rData of responsesToSubmit) {
      await page.request.post(`${BASE_URL}/api/polls/${publicId}/responses`, { data: rData });
    }

    console.log('📊 Adding reports...');
    const reportsToAdd = [
      { qIndex: 0, type: 'choice_counts' },
      { qIndex: 1, type: 'choice_counts' },
      { qIndex: 1, type: 'approval_winner' },
      { qIndex: 2, type: 'borda_scores' },
      { qIndex: 2, type: 'pairwise_margins' },
      { qIndex: 2, type: 'voting_rule_winner', config: { rule: 'schulze' } },
      { qIndex: 3, type: 'voting_rule_winner', config: { rule: 'irv' } },
      { qIndex: 4, type: 'condorcet_winner' },
      { qIndex: 5, type: 'majority_judgment' },
      { qIndex: 6, type: 'majority_judgment' },
      { qIndex: 7, type: 'majority_judgment' },
      { qIndex: 8, type: 'yna_counts' }
    ];

    for (const r of reportsToAdd) {
      console.log(`   - Adding ${r.type} to question ${r.qIndex}...`);
      const rResp = await page.request.post(`${BASE_URL}/api/polls/${publicId}/admin/${adminToken}/reports`, {
        data: {
          question_id: questions[r.qIndex].id,
          report_type: r.type,
          config: r.config || null,
          is_public: true
        }
      });
      if (!rResp.ok()) {
        const err = await rResp.text();
        console.error(`      ❌ Failed to add report ${r.type}: ${err}`);
      }
    }

    console.log('📸 Navigating to results page...');
    await page.goto(`${BASE_URL}/${publicId}/results`, { waitUntil: 'networkidle' });
    
    console.log('⏳ Waiting for reports to render...');
    try {
      await page.waitForSelector('.report-card', { timeout: 10000 });
    } catch (e) {
      const html = await page.content();
      console.error('❌ Timeout waiting for .report-card. Current HTML:', html);
      throw e;
    }
    
    // Give charts and animations a moment to settle
    console.log('⏳ Settling animations...');
    await page.waitForTimeout(2000);

    console.log('🧹 Cleaning up UI for results screenshot...');
    await page.evaluate(() => {
      // Hide infra
      const toHide = ['header', 'footer', 'nav', '.breadcrumbs', '.results-footer'];
      toHide.forEach(sel => {
        const el = document.querySelector(sel);
        if (el) el.style.display = 'none';
      });

      document.body.style.background = 'white'; // Use white background for the results
      const mainContent = document.querySelector('.main-content');
      if (mainContent) mainContent.style.background = 'transparent';
      
      const container = document.querySelector('.results-container');
      if (container) {
        container.style.maxWidth = '100%';
        container.style.margin = '0';
        container.style.boxShadow = 'none';
        container.style.border = 'none';
        container.style.borderRadius = '0';
        container.style.padding = '20px';
        container.style.background = 'white';
      }

      // Ensure all charts are fully visible
      const charts = document.querySelectorAll('canvas');
      charts.forEach(c => {
        c.style.maxWidth = '100%';
        // Disable responsiveness/animations if possible via style (though Chart.js might need more)
      });
    });

    const resultsContainer = page.locator('.results-container');
    console.log('📸 Capturing screenshot...');
    await resultsContainer.screenshot({
      path: path.join(SCREENSHOT_DIR, 'full_results_container.png'),
      omitBackground: false, // Don't omit for results, we want the white background
      animations: 'disabled'
    });
    console.log('   - Saved full_results_container.png');

    console.log('\n✨ Done! Results screenshot is in the "screenshots" folder.');

  } catch (error) {
    console.error('❌ Error:', error.message);
  } finally {
    if (browser) await browser.close();
  }
}

captureResultsScreenshots();
