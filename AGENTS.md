# Vote App

A voting web app where users create polls (similar to Google Forms) with specialized input types for social choice theory. Supports multiple questions per poll and various voting methods.

## Tech Stack

- **Backend:** PHP (no Composer/frameworks), supports SQLite and MySQL
- **Frontend:** Vanilla HTML + JavaScript (ES modules)
- **Routing:** mod_rewrite via `.htaccess`
- **Communication:** JSON API via fetch

## Project Structure

```
public/           # Web root (Apache/nginx document root)
  index.php       # Entry point, routes all requests
  api.php         # API entry point
  assets/         # CSS, JS, images
src/              # PHP application code (outside web root)
  Controllers/    # Page and API controllers
  Models/         # User, Poll, Question, Option, Response, Answer
  Services/       # LogService, TokenService, etc.
templates/        # PHP HTML templates
config/           # Configuration (config.php is gitignored)
migrations/       # Database schema SQL
tests/            # PHPUnit tests
```

## Running Tests

**PHP Unit Tests:**
```bash
phpunit
XDEBUG_MODE=coverage phpunit --coverage-text    # Run with code coverage
```

Tests are in `tests/` with Feature tests (API integration) and Unit tests.

Do NOT run `vendor/bin/phpunit`. It does not exist.

If there are failures, it makes sense to run `phpunit | grep -A 7 "✘"` to see details of failed tests (or use phpunit command line options).

**E2E Tests (Playwright):**
```bash
npm run test:e2e        # Run all E2E tests headlessly
npm run test:e2e:ui     # Run with Playwright UI
npm run test:e2e:headed # Run with visible browser
npm run test:e2e:debug  # Run in debug mode
```

E2E tests are in `e2e/` and test full user flows (install, auth, polls, sysadmin).

### E2E Test Architecture

**Projects** (in `playwright.config.js`):
- `setup` - Installs app, creates admin session, saves storage state
- `main` - Auth, poll, sysadmin tests (depends on setup, runs in parallel)
- `install` - Installation flow tests (isolated, runs serially with `workers: 1`)

**Key Fixtures** (in `e2e/fixtures.js`):
- `page` - Pre-authenticated as admin via storage state (default for `main` project)
- `freshPage` - No authentication, use for login/register flows or anonymous access
- `userAccount` - Creates a new regular user, returns `{ page, email, password }`
- `uniqueEmail` - Generates unique email with timestamp + random string
- `adminCredentials` - Returns `{ email, password }` for manual login
- `freshInstall` - Deletes database/config (only use in `install` project)

### Writing Good E2E Tests

**1. Don't invalidate shared state**
```javascript
// BAD: Logs out the shared admin session, breaks other tests
test('logout', async ({ page }) => {
  await page.click('button:has-text("Log Out")');
});

// GOOD: Use freshPage with its own login
test('logout', async ({ freshPage, adminCredentials }) => {
  await freshPage.goto('/login');
  // ... login with adminCredentials ...
  await freshPage.click('button:has-text("Log Out")');
});
```

**2. Use appropriate fixtures**
```javascript
// Testing admin-only features: use page (pre-authenticated)
test('admin dashboard', async ({ page }) => {
  await page.goto('/sysadmin');
});

// Testing public/anonymous access: use freshPage
test('view poll', async ({ freshPage }) => {
  await freshPage.goto('/some-poll-id');
});

// Testing regular user behavior: use userAccount
test('user cannot access admin', async ({ userAccount }) => {
  await userAccount.page.goto('/sysadmin');
  await expect(userAccount.page.locator('h1')).toContainText('Access Denied');
});
```

**3. Create test data via API when possible**
```javascript
// Faster and more reliable than UI interactions
const response = await request.post('/api/polls', {
  data: { title: 'Test Poll', status: 'open', questions: [...] }
});
const { poll } = await response.json();
await page.goto(`/${poll.public_id}`);
```

**4. Use unique identifiers to avoid conflicts**
```javascript
// BAD: Hardcoded email conflicts on retry or parallel runs
await page.fill('input[name="email"]', 'user@example.com');

// GOOD: Use uniqueEmail fixture
test('register', async ({ freshPage, uniqueEmail }) => {
  await freshPage.fill('input[name="email"]', uniqueEmail);
});
```

**5. Wait for specific elements, not arbitrary timeouts**
```javascript
// BAD
await page.waitForTimeout(1000);

// GOOD
await page.waitForSelector('#usersTable tbody tr');
await expect(page.locator('h1')).toContainText('Dashboard');
```

## Key Concepts

**Input Types:** single choice, approval (with min/max), rankings (full/truncated/with ties), utility scores, star ratings, grades, yes/no/abstain, free text

**Privacy Settings:**
- (a) Everyone sees all poll responses with names
- (b1) Anonymous responses visible
- (b2) Anonymous responses + list of voter names
- (c) Responses not visible

**Access Modes:** public link, password-protected, one-time tokens, email invitations

**User Roles:**
- `user` - Regular users who can create and manage their own polls
- `sysadmin` - System administrators with access to the sysadmin dashboard

**URL Structure:**
- `/vote/` - Landing page
- `/vote/create` - Form builder
- `/vote/{publicId}` - Voter form
- `/vote/{publicId}/results` - Public results page
- `/vote/{publicId}/admin/{adminToken}` - Poll admin panel (for poll creator)
- `/vote/{publicId}/admin/{adminToken}/results` - Admin results & analysis page
- `/vote/sysadmin` - Sysadmin dashboard (requires sysadmin role)
- `/api/*` - API endpoints

**Results & Reports System:**
Poll admins can add configurable report types per question. Reports are cached and invalidated when votes change.

Key components:
- `src/Models/Report.php` - Report model with caching
- `src/Services/ReportRegistry.php` - Maps report types to handlers
- `src/Services/Reports/*.php` - Individual report handlers (BaseReport, ChoiceCountsReport, BordaScoresReport, etc.)
- `src/Services/ProfileBuilder.php` - Converts responses to pref_voting library profiles
- `src/Controllers/ReportApiController.php` - CRUD API for reports
- `assets/js/results-core.js` - Shared frontend rendering
- `assets/js/report-types/*.js` - Frontend renderers for each report type

Available report types: `choice_counts`, `approval_winner`, `borda_scores`, `pairwise_margins`, `voting_rule_winner`

The `pref_voting` library (symlinked at `/pref_voting`) provides voting rule implementations (Schulze, Ranked Pairs, IRV, Borda, Copeland, etc.)

**Sysadmin Dashboard:**
The sysadmin dashboard (`/sysadmin`) provides system-wide administration:
- **Users**: View, change roles, delete user accounts
- **Polls**: View and delete any poll in the system
- **Logs**: View all action log entries for auditing
- **Stats**: System-wide statistics (user count, poll count, responses, etc.)

The initial sysadmin account is created during installation (`install.php`).

## Database

Schema in `migrations/001_initial_schema.sql`. Responses reference options by ID (not label text) so option names can be changed after votes are cast.

## Detailed Documentation

For more detail, see:
- `vote-app-spec.md` - Full specification with all features and requirements
- `implementation-plan.md` - File structure, database schema, API design, and implementation phases
