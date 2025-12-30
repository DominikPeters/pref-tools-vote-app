/**
 * Standalone script to capture screenshots of each question type.
 * 
 * Usage:
 * 1. Start the test server: APP_ENV=test php -S localhost:18080
 * 2. Run this script: node scripts/capture_screenshots.js
 */

const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const BASE_URL = 'http://localhost:8005';
const SCREENSHOT_DIR = path.join(__dirname, '..', 'screenshots');

async function captureScreenshots() {
  console.log('🚀 Starting screenshot capture...');

  // 1. Ensure screenshot directory exists
  if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR);
    console.log(`📁 Created directory: ${SCREENSHOT_DIR}`);
  }

      // 2. Define the poll data

      const pollData = {

        title: 'Product Strategy & Team Feedback',

        description: 'Help us prioritize our roadmap and share your thoughts on the recent release.',

        voting_mode: 'open',

        status: 'open',

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

        // 3. Launch browser

        browser = await chromium.launch();

        const context = await browser.newContext({

          viewport: { width: 1280, height: 720 },

          deviceScaleFactor: 2, // High-quality screenshots

        });

        const page = await context.newPage();

    

        // 4. Create poll via API

        console.log('📝 Creating poll...');

        const response = await page.request.post(`${BASE_URL}/api/polls`, { data: pollData });

        

        if (!response.ok()) {

          const errorText = await response.text();

          throw new Error(`Failed to create poll: ${response.status()} ${response.statusText()}\n${errorText}\n\nIs the server running at ${BASE_URL}?`);

        }

    

        const result = await response.json();

        const publicId = result.poll.public_id;

        const pollId = result.poll.id;

        console.log(`✅ Poll created: ${BASE_URL}/${publicId}`);

    

        // 5. Submit a varied response via API

        console.log('🗳️ Submitting varied response...');

        const questionsData = result.poll.questions;

        const answers = {};

    

        questionsData.forEach(q => {

          const opts = q.options;

          switch (q.type) {

            case 'single_choice': answers[q.id] = opts[0].id; break;

            case 'approval': answers[q.id] = [opts[0].id, opts[1].id, opts[3].id]; break;

            case 'ranking': answers[q.id] = [opts[0].id, opts[2].id, opts[1].id, opts[3].id]; break;

            case 'ranking_truncated': answers[q.id] = [opts[3].id, opts[1].id, opts[0].id]; break;

            case 'ranking_with_ties': 

              // Option A first, B and C tied

              answers[q.id] = { [opts[0].id]: 1, [opts[1].id]: 2, [opts[2].id]: 2 }; 

              break;

            case 'star':

              answers[q.id] = { [opts[0].id]: 5, [opts[1].id]: 4, [opts[2].id]: 5 };

              break;

            case 'grade':

              if (q.settings?.preset === 'plus-minus') {

                answers[q.id] = { [opts[0].id]: '++', [opts[1].id]: '0' };

              } else {

                answers[q.id] = { [opts[0].id]: 'excellent', [opts[1].id]: 'good' };

              }

              break;

            case 'yes_no_abstain':

              answers[q.id] = { [opts[0].id]: 'yes' };

              break;

          }

        });

  

      const submitResponse = await page.request.post(`${BASE_URL}/api/polls/${publicId}/responses`, {

        data: { answers }

      });

      

      if (!submitResponse.ok()) {

        throw new Error(`Failed to submit response: ${submitResponse.status()}`);

      }

      const { voter_token } = await submitResponse.json();

  

      // 6. Navigate to poll with voter token cookie

      const url = new URL(BASE_URL);

      await context.addCookies([{

        name: `voter_token_${publicId}`,

        value: voter_token,

        domain: url.hostname,

        path: '/',

        httpOnly: true,

        sameSite: 'Lax'

      }]);

  

      await page.goto(`${BASE_URL}/${publicId}`);

      await page.waitForSelector('.question-display');

  

      // 7. Clean up UI for screenshots

      console.log('🧹 Cleaning up UI...');

      await page.evaluate(() => {

        const toHide = ['.poll-header', '.poll-footer', '.voter-info', '.form-actions', '.report-issue-btn', '#toast-container'];

        toHide.forEach(sel => {

          const el = document.querySelector(sel);

          if (el) el.style.display = 'none';

        });

  

        document.body.style.backgroundColor = 'white';

        const container = document.querySelector('.poll-container');

        if (container) {

          container.style.maxWidth = '800px';

          container.style.margin = '20px auto';

          container.style.boxShadow = 'none';

          container.style.border = 'none';

          container.style.padding = '0';

        }

      });

  

    // 8. Capture each question
    const questions = await page.locator('.question-display').all();
    console.log(`📸 Capturing ${questions.length} elements...`);

    const typeCounts = {};

    for (const question of questions) {
      const type = await question.getAttribute('data-type');
      if (['text_single', 'text_multi', 'section_header'].includes(type)) continue;

      typeCounts[type] = (typeCounts[type] || 0) + 1;
      const suffix = typeCounts[type] > 1 ? `_${typeCounts[type]}` : '';
      const filename = `question_${type}${suffix}.png`;
      const filepath = path.join(SCREENSHOT_DIR, filename);
      
      await question.screenshot({ 
        path: filepath,
        animations: 'disabled'
      });
      console.log(`   - Saved ${filename}`);
    }

    // 9. Capture full page screenshot of the container only
    console.log('📸 Capturing full container screenshot...');
    
    // Set viewport to 800px for this specific capture
    await page.setViewportSize({ width: 800, height: 1000 });

    await page.evaluate(() => {
      // Hide everything except the poll container for the full shot
      const editingBanner = document.querySelector('.editing-banner');
      if (editingBanner) editingBanner.style.display = 'none';
      
      const pollHeader = document.querySelector('.poll-header');
      if (pollHeader) pollHeader.style.display = 'block';
      
      // Make backgrounds transparent
      document.body.style.background = 'transparent';
      const mainContent = document.querySelector('.main-content');
      if (mainContent) mainContent.style.background = 'transparent';
      
      const container = document.querySelector('.poll-container');
      if (container) {
        container.style.maxWidth = '100%';
        container.style.margin = '0';
        container.style.boxShadow = 'none';
        container.style.border = 'none';
        container.style.borderRadius = '0';
        container.style.padding = '20px';
        container.style.background = 'white'; // Solid background for the form area
      }
      
      // Hide header/footer/other infra
      const toHide = ['header', 'footer', 'nav', '.form-actions'];
      toHide.forEach(sel => {
        const el = document.querySelector(sel);
        if (el) el.style.display = 'none';
      });
    });

    const pollContainer = page.locator('.poll-container');
    await pollContainer.screenshot({
      path: path.join(SCREENSHOT_DIR, 'full_poll_container.png'),
      omitBackground: true,
      animations: 'disabled'
    });
    console.log('   - Saved full_poll_container.png');

    console.log('\n✨ Done! Screenshots are in the "screenshots" folder.');

  } catch (error) {
    console.error('❌ Error:', error.message);
  } finally {
    if (browser) await browser.close();
  }
}

captureScreenshots();
