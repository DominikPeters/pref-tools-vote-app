# Vote App

A voting web app where users create votes/polls (similar to Google Forms) with specialized input types for social choice theory. Supports multiple questions per vote and various voting methods.

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
  Models/         # User, Vote, Question, Option, Response, Answer
  Services/       # VoteService, TokenService, etc.
templates/        # PHP HTML templates
config/           # Configuration (config.php is gitignored)
migrations/       # Database schema SQL
tests/            # PHPUnit tests
```

## Running Tests

```bash
phpunit
```

Tests are in `tests/` with Feature tests (API integration) and Unit tests.

## Key Concepts

**Input Types:** single choice, approval (with min/max), rankings (full/truncated/with ties), utility scores, star ratings, grades, yes/no/abstain, free text

**Privacy Settings:**
- (a) Everyone sees all votes with names
- (b1) Anonymous votes visible
- (b2) Anonymous votes + list of voter names
- (c) Votes not visible

**Access Modes:** public link, password-protected, one-time tokens, email invitations

**URL Structure:**
- `/vote/` - Landing page
- `/vote/create` - Form builder
- `/vote/{publicId}` - Voter form
- `/vote/{publicId}/admin/{adminToken}` - Admin panel
- `/api/*` - API endpoints

## Database

Schema in `migrations/001_initial_schema.sql`. Votes reference options by ID (not label text) so option names can be changed after votes are cast.

## Detailed Documentation

For more detail, see:
- `vote-app-spec.md` - Full specification with all features and requirements
- `implementation-plan.md` - File structure, database schema, API design, and implementation phases
