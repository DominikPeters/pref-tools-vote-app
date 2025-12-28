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
```

Tests are in `tests/` with Feature tests (API integration) and Unit tests.

**E2E Tests (Playwright):**
```bash
npm run test:e2e        # Run all E2E tests headlessly
npm run test:e2e:ui     # Run with Playwright UI
npm run test:e2e:headed # Run with visible browser
npm run test:e2e:debug  # Run in debug mode
```

E2E tests are in `e2e/` and test full user flows (install, auth, polls, sysadmin).

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
- `/vote/{publicId}/admin/{adminToken}` - Poll admin panel (for poll creator)
- `/vote/sysadmin` - Sysadmin dashboard (requires sysadmin role)
- `/api/*` - API endpoints

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
