# Pref.Tools Vote — Implementation Plan

## 1. File Structure

```
pref-tools-vote/
├── public/                     # Web root (point Apache/nginx here)
│   ├── index.php               # Entry point, routes all requests
│   ├── .htaccess               # mod_rewrite rules
│   ├── assets/
│   │   ├── css/
│   │   │   ├── main.css        # Global styles
│   │   │   ├── builder.css     # Form builder styles
│   │   │   └── poll.css        # Voter-facing form styles
│   │   ├── js/
│   │   │   ├── app.js          # Shared utilities, API client
│   │   │   ├── builder.js      # Form builder logic
│   │   │   ├── poll.js         # Voter form logic
│   │   │   ├── admin.js        # Admin panel logic
│   │   │   ├── dashboard.js    # User dashboard logic
│   │   │   └── lib/
│   │   │       └── sortable.min.js
│   │   └── img/
│   └── api.php                 # API entry point (or handled by index.php)
│
├── src/                        # PHP application code (outside web root)
│   ├── bootstrap.php           # Autoloader, config, DB init
│   ├── Config.php              # Configuration loader
│   ├── Database.php            # PDO wrapper, SQLite/MySQL abstraction
│   ├── Router.php              # Simple request router
│   ├── Auth.php                # Authentication & session handling
│   ├── Controllers/
│   │   ├── PageController.php      # Renders HTML pages
│   │   ├── ApiController.php       # Base API controller
│   │   ├── PollApiController.php   # Poll CRUD, submission
│   │   ├── AuthApiController.php   # Login, register, logout
│   │   └── AdminApiController.php  # Admin actions (close vote, etc.)
│   ├── Models/
│   │   ├── User.php
│   │   ├── Poll.php            # A vote/form instance
│   │   ├── Question.php        # A question within a vote
│   │   ├── Option.php          # An option/candidate within a question
│   │   ├── Response.php        # A voter's submission
│   │   └── Answer.php          # A single answer within a response
│   ├── Services/
│   │   ├── PollService.php     # Business logic for votes
│   │   ├── TokenService.php    # Generate secure tokens
│   │   ├── MailService.php     # Email sending abstraction
│   │   └── LogService.php      # Action logging
│   ├── Validation/
│   │   └── Validator.php       # Input validation helpers
│   └── i18n/
│       ├── en.php              # English voter-facing strings
│       └── Translator.php      # Simple translation helper
│
├── templates/                  # HTML templates
│   ├── layout.php              # Base layout
│   ├── home.php                # Landing page
│   ├── builder.php             # Form builder page
│   ├── poll.php                # Voter-facing form
│   ├── results.php             # Results page
│   ├── admin.php               # Admin panel
│   ├── dashboard.php           # User dashboard
│   ├── login.php               # Login/register page
│   └── partials/
│       ├── header.php
│       └── footer.php
│
├── config/
│   ├── config.example.php      # Example configuration
│   └── config.php              # Actual config (gitignored)
│
├── data/                       # SQLite database location (gitignored)
│   └── .gitkeep
│
├── migrations/
│   └── 001_initial_schema.sql  # Database schema
│
├── install.php                 # Web-based installer
├── README.md
├── LICENSE
└── .gitignore
```

### Notes on Structure

- **`public/`** is the only directory exposed to the web; everything else is above or outside web root
- **`src/`** uses a simple PSR-4-ish autoloader (no Composer required, but compatible with it)
- **`install.php`** handles first-run setup: creates config file, initializes database, creates first admin (optional)
- **ES modules**: JS files use `import`/`export`; loaded with `<script type="module">`

---

## 2. Database Schema

### Design Principles

- All tables use `id` as auto-increment primary key
- Timestamps stored as ISO 8601 strings (SQLite-friendly) or `DATETIME` (MySQL)
- Votes/answers reference options by `option_id`, not label text
- JSON fields used sparingly for flexibility (both SQLite 3.38+ and MySQL 5.7+ support JSON)

### Tables

```sql
-- Users (optional accounts)
CREATE TABLE users (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    email           VARCHAR(255) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Votes (a form/poll instance)
CREATE TABLE polls (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    public_id       VARCHAR(16) UNIQUE NOT NULL,   -- e.g., "ABC123" (in URL)
    admin_token     VARCHAR(32) NOT NULL,           -- e.g., "XYZ789..." (for admin URL)
    user_id         INTEGER NULL,                   -- NULL if created without login
    
    title           VARCHAR(500) NOT NULL,
    description     TEXT NULL,                      -- Markdown allowed
    
    -- Settings
    status          VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft, open, closed
    visibility      VARCHAR(20) NOT NULL DEFAULT 'private', -- (a), (b1), (b2), (c) encoded
    visibility_timing VARCHAR(20) NOT NULL DEFAULT 'after_close', -- during, after_close
    collect_name    BOOLEAN NOT NULL DEFAULT FALSE,
    name_visibility VARCHAR(20) NULL,               -- For per-field visibility override
    allow_edit_own  BOOLEAN NOT NULL DEFAULT TRUE,  -- Can voters edit their submission?
    allow_edit_any  BOOLEAN NOT NULL DEFAULT FALSE, -- Doodle-style: anyone can edit anyone
    randomize_options BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- Sharing
    access_mode     VARCHAR(20) NOT NULL DEFAULT 'link', -- link, password, token, email, login
    access_password VARCHAR(255) NULL,              -- Hashed, for password mode
    
    -- Locale for voter-facing strings
    locale          VARCHAR(10) NOT NULL DEFAULT 'en',
    
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at       DATETIME NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Questions within a vote
CREATE TABLE questions (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    poll_id         INTEGER NOT NULL,
    sort_order      INTEGER NOT NULL DEFAULT 0,
    
    type            VARCHAR(30) NOT NULL,           -- See "Question Types" below
    text            VARCHAR(1000) NOT NULL,         -- Question text (Markdown allowed)
    description     TEXT NULL,                      -- Optional longer description
    required        BOOLEAN NOT NULL DEFAULT TRUE,
    
    -- Type-specific settings stored as JSON
    settings        JSON NULL,                      -- e.g., {"min": 1, "max": 3} for approval
    
    visibility      VARCHAR(20) NULL,               -- Per-question override (NULL = inherit)
    
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
);

-- Options/candidates within a question
CREATE TABLE options (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    question_id     INTEGER NOT NULL,
    sort_order      INTEGER NOT NULL DEFAULT 0,
    
    label           VARCHAR(500) NOT NULL,          -- Display text
    description     TEXT NULL,                      -- Optional description (Markdown)
    
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Voter responses (one per submission)
CREATE TABLE responses (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    poll_id         INTEGER NOT NULL,
    
    voter_name      VARCHAR(255) NULL,              -- If collect_name is true
    voter_token     VARCHAR(64) NULL,               -- For edit-own-vote tracking (cookie-based)
    access_token_id INTEGER NULL,                   -- If using token-based access
    user_id         INTEGER NULL,                   -- If voter was logged in
    
    ip_address      VARCHAR(45) NULL,               -- Optional, for abuse prevention
    user_agent      VARCHAR(500) NULL,
    
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Individual answers within a response
CREATE TABLE answers (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    response_id     INTEGER NOT NULL,
    question_id     INTEGER NOT NULL,
    
    -- Polymorphic answer storage
    -- Only one of these is populated depending on question type
    value_text      TEXT NULL,                      -- For free text
    value_choice    INTEGER NULL,                   -- For single choice (option_id)
    value_json      JSON NULL,                      -- For complex types (rankings, approvals, etc.)
    
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Access tokens (for token-based access mode)
CREATE TABLE access_tokens (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    poll_id         INTEGER NOT NULL,
    token           VARCHAR(32) UNIQUE NOT NULL,
    
    label           VARCHAR(255) NULL,              -- Optional identifier (e.g., "Token for Alice")
    used_at         DATETIME NULL,                  -- NULL if unused
    response_id     INTEGER NULL,                   -- Links to the response if used
    
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE SET NULL
);

-- Email invitations (for email-based access mode)
CREATE TABLE email_invitations (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    poll_id         INTEGER NOT NULL,
    email           VARCHAR(255) NOT NULL,
    token           VARCHAR(32) UNIQUE NOT NULL,
    
    sent_at         DATETIME NULL,
    used_at         DATETIME NULL,
    response_id     INTEGER NULL,
    
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE SET NULL
);

-- Action log (for auditing/debugging, à la HotCRP)
CREATE TABLE action_log (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Optional foreign keys (nullable, for filtering/linking)
    user_id         INTEGER NULL,
    poll_id         INTEGER NULL,
    response_id     INTEGER NULL,
    
    -- Flexible payload
    action          VARCHAR(50) NOT NULL,   -- e.g., "vote.created", "response.submitted"
    ip_address      VARCHAR(45) NULL,
    data            JSON NULL               -- Action-specific details
);

-- Indexes
CREATE INDEX idx_action_log_poll_id ON action_log(poll_id);
CREATE INDEX idx_action_log_user_id ON action_log(user_id);
CREATE INDEX idx_action_log_created_at ON action_log(created_at);
CREATE INDEX idx_polls_public_id ON votes(public_id);
CREATE INDEX idx_polls_user_id ON votes(user_id);
CREATE INDEX idx_questions_poll_id ON questions(poll_id);
CREATE INDEX idx_options_question_id ON options(question_id);
CREATE INDEX idx_responses_poll_id ON responses(poll_id);
CREATE INDEX idx_answers_response_id ON answers(response_id);
CREATE INDEX idx_access_tokens_token ON access_tokens(token);
CREATE INDEX idx_email_invitations_token ON email_invitations(token);
```

### Question Types (stored in `questions.type`)

| Type | `value_*` Column | Format |
|------|------------------|--------|
| `text_single` | `value_text` | String |
| `text_multi` | `value_text` | String |
| `single_choice` | `value_choice` | option_id |
| `approval` | `value_json` | `[option_id, option_id, ...]` |
| `ranking` | `value_json` | `[option_id, option_id, ...]` (ordered) |
| `ranking_truncated` | `value_json` | `[option_id, ...]` (partial) |
| `ranking_with_ties` | `value_json` | `[[option_id, ...], [option_id, ...], ...]` (tiers) |
| `utility` | `value_json` | `{option_id: score, ...}` |
| `star` | `value_json` | `{option_id: 1-5, ...}` |
| `grade` | `value_json` | `{option_id: "excellent", ...}` |
| `yes_no_abstain` | `value_json` | `{option_id: "yes"|"no"|"abstain", ...}` |

### Action Log Events

The `action_log` table records significant events for auditing and debugging:

| Action | Data Example |
|--------|--------------|
| `vote.created` | `{"title": "Board Election"}` |
| `vote.updated` | `{"fields": ["title", "description"]}` |
| `vote.closed` | `{}` |
| `vote.reopened` | `{}` |
| `vote.deleted` | `{"title": "Old Poll"}` |
| `response.submitted` | `{"voter_name": "Alice"}` |
| `response.edited` | `{"by": "voter"}` or `{"by": "admin"}` |
| `response.deleted` | `{"by": "admin", "voter_name": "Bob"}` |
| `user.registered` | `{}` |
| `user.login` | `{}` |
| `user.logout` | `{}` |
| `tokens.generated` | `{"count": 50}` |
| `invitations.sent` | `{"count": 12, "emails": ["a@b.com", ...]}` |

### Write-In Candidates (Future)

The current schema supports write-in candidates without modification:

- **Question setting**: `questions.settings = {"allow_write_in": true, ...}`
- **Answer storage** uses `value_json` flexibly:

| Type | Format with Write-In |
|------|---------------------|
| `single_choice` | `{"choice": option_id}` or `{"write_in": "Mario"}` |
| `approval` | `{"selected": [1, 2], "write_in": ["Mario", "Luigi"]}` |
| `ranking` | `{"ranking": [3, 1, "write_in:0"], "write_ins": ["Mario"]}` |

Aggregation logic would need to handle write-ins (e.g., grouping similar write-ins), but no schema changes required.

### SQLite vs MySQL Compatibility Notes

| Feature | SQLite | MySQL | Approach |
|---------|--------|-------|----------|
| Auto-increment | `INTEGER PRIMARY KEY` | `INT AUTO_INCREMENT` | Use `INTEGER PRIMARY KEY AUTO_INCREMENT`; SQLite ignores `AUTO_INCREMENT`, MySQL needs it |
| Booleans | Stored as 0/1 | `TINYINT(1)` | Use 0/1 everywhere |
| JSON | `JSON` (3.38+) | `JSON` | Use `JSON` type, but validate JSON in PHP |
| Datetime | Text or INTEGER | `DATETIME` | Use `DATETIME`, store ISO 8601 |

The `Database.php` wrapper will handle these differences with a simple dialect flag.

---

## 3. API Design

### Conventions

- **Base path**: `/api/`
- **Format**: JSON request/response
- **Auth**: PHP session cookie (credentials: 'same-origin')
- **Errors**: `{"error": "message", "code": "ERROR_CODE"}`
- **Success**: `{"ok": true, ...data}`

### Endpoints

#### Authentication

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/auth/register` | Create account | No |
| POST | `/api/auth/login` | Log in | No |
| POST | `/api/auth/logout` | Log out | Yes |
| GET | `/api/auth/me` | Get current user | Yes |

#### Polls (Form Management)

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/polls` | Create new poll | No* |
| GET | `/api/polls/:publicId` | Get poll (public data) | Via access |
| GET | `/api/polls/:publicId/admin/:adminToken` | Get poll (full admin data) | Via token |
| PUT | `/api/polls/:publicId/admin/:adminToken` | Update poll | Via token |
| DELETE | `/api/polls/:publicId/admin/:adminToken` | Delete poll | Via token |
| POST | `/api/polls/:publicId/admin/:adminToken/close` | Close voting | Via token |
| POST | `/api/polls/:publicId/admin/:adminToken/reopen` | Reopen voting | Via token |

*Returns admin token in response; optionally linked to user account if logged in.

#### Questions & Options (managed via vote update, but could be separate)

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/polls/:publicId/admin/:adminToken/questions` | Add question | Via token |
| PUT | `/api/polls/:publicId/admin/:adminToken/questions/:qId` | Update question | Via token |
| DELETE | `/api/polls/:publicId/admin/:adminToken/questions/:qId` | Delete question | Via token |
| POST | `/api/polls/:publicId/admin/:adminToken/questions/:qId/options` | Add option | Via token |
| PUT | `/api/polls/:publicId/admin/:adminToken/options/:optId` | Update option | Via token |
| DELETE | `/api/polls/:publicId/admin/:adminToken/options/:optId` | Delete option | Via token |

Alternatively, the entire form structure can be saved in one `PUT /api/polls/:publicId/admin/:adminToken` call with nested questions/options.

#### Responses (Voter Submissions)

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/polls/:publicId/responses` | Submit vote | Via access |
| GET | `/api/polls/:publicId/responses` | Get all responses (if visible) | Via access |
| GET | `/api/polls/:publicId/responses/:respId` | Get single response | Via access |
| PUT | `/api/polls/:publicId/responses/:respId` | Update response | Via voter token |
| DELETE | `/api/polls/:publicId/responses/:respId` | Delete response | Via voter token |

#### Access Tokens (Admin)

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/polls/:publicId/admin/:adminToken/tokens` | Generate access tokens | Via token |
| GET | `/api/polls/:publicId/admin/:adminToken/tokens` | List access tokens | Via token |
| DELETE | `/api/polls/:publicId/admin/:adminToken/tokens/:tokId` | Revoke token | Via token |

#### Email Invitations (Admin)

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/polls/:publicId/admin/:adminToken/invitations` | Send email invites | Via token + logged in |
| GET | `/api/polls/:publicId/admin/:adminToken/invitations` | List invitations | Via token |

#### User Dashboard

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/user/polls` | List user's votes | Yes |
| GET | `/api/user/responses` | List user's responses | Yes |
| POST | `/api/user/claim-poll` | Claim a poll by admin token | Yes |

#### Export

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/polls/:publicId/admin/:adminToken/export?format=json` | Export raw data | Via token |
| GET | `/api/polls/:publicId/admin/:adminToken/export?format=csv` | Export as CSV | Via token |
| GET | `/api/polls/:publicId/admin/:adminToken/export?format=preflib` | Export in PrefLib format | Via token |

#### Action Log

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/polls/:publicId/admin/:adminToken/log` | Get action log for this vote | Via token |
| GET | `/api/admin/log` | Get global action log (site owner) | Site admin |

---

## 4. Key Design Decisions

### URL Routing Strategy

```
pref.tools/vote/                      → Landing page
pref.tools/vote/create                → Form builder (new vote)
pref.tools/vote/ABC123                → Voter form OR results (if closed + public)
pref.tools/vote/ABC123/admin/XYZ789   → Admin panel
pref.tools/vote/ABC123/results        → Public results (if enabled)
pref.tools/vote/dashboard             → User dashboard (logged in)
pref.tools/vote/login                 → Login/register
pref.tools/vote/api/*                 → API endpoints
```

Handled via `.htaccess`:
```apache
RewriteEngine On
RewriteBase /vote/

# Static assets - serve directly
RewriteRule ^assets/ - [L]

# Everything else goes to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php [QSA,L]
```

### Authentication Architecture

```php
// Auth.php - designed for future expansion
class Auth {
    // Current implementation
    public function attempt(string $email, string $password): ?User;
    public function login(User $user): void;
    public function logout(): void;
    public function user(): ?User;
    public function check(): bool;
    
    // Future hooks
    public function viaOAuth(string $provider, array $profile): User;
    public function viaMagicLink(string $token): ?User;
    public function viaPasskey(string $credential): ?User;
}
```

### Voter Identification (edit-own-vote)

For non-logged-in voters in "allow_edit_own" mode:
1. On first submission, generate a random `voter_token`
2. Store in cookie and in `responses.voter_token`
3. On page reload, if cookie present, fetch and allow editing their response

### Form Builder State Management

```javascript
// builder.js - state structure
const formState = {
    title: "Untitled Vote",
    description: "",
    settings: {
        collectName: false,
        visibility: "private",
        // ...
    },
    questions: [
        {
            _id: "temp_1",          // Temporary ID until saved
            type: "single_choice",
            text: "",
            required: true,
            settings: {},
            options: [
                { _id: "temp_1_1", label: "" },
                { _id: "temp_1_2", label: "" }
            ]
        }
    ]
};

// Auto-save to localStorage
function autoSave() {
    localStorage.setItem('draft_vote', JSON.stringify(formState));
}

// Sync to server (creates or updates)
async function saveToServer() {
    const response = await api.saveVote(formState);
    // Update local IDs with server IDs
}
```

### i18n for Voter-Facing Strings

```php
// i18n/en.php
return [
    'submit' => 'Submit',
    'submit_success' => 'Your response has been recorded.',
    'required_field' => 'This field is required.',
    'name_placeholder' => 'Your name',
    'edit_response' => 'Edit your response',
    // ...
];

// Usage in templates
<?= __('submit') ?>

// Translator.php
function __(string $key, array $params = []): string {
    static $strings = null;
    if ($strings === null) {
        $locale = $GLOBALS['vote_locale'] ?? 'en';
        $strings = require __DIR__ . "/i18n/{$locale}.php";
    }
    $text = $strings[$key] ?? $key;
    return $params ? strtr($text, $params) : $text;
}
```

### Privacy/Visibility Logic

```php
// Who can see what
function canSeeResponses(Vote $vote, ?User $user, ?string $adminToken): bool {
    // Admin can always see
    if ($adminToken === $vote->admin_token) return true;
    
    // Private = no one else
    if ($vote->visibility === 'private') return false;
    
    // Check timing
    if ($vote->visibility_timing === 'after_close' && $vote->status !== 'closed') {
        return false;
    }
    
    return true;
}

function canSeeVoterNames(Vote $vote, ?string $adminToken): bool {
    if ($adminToken === $vote->admin_token) return true;
    return in_array($vote->visibility, ['full', 'names_only']); // (a) or (b2)
}
```

### Action Logging

```php
// LogService.php
class LogService {
    public function log(
        string $action,
        ?int $voteId = null,
        ?int $userId = null,
        ?int $responseId = null,
        array $data = []
    ): void {
        $this->db->insert('action_log', [
            'action' => $action,
            'poll_id' => $voteId,
            'user_id' => $userId,
            'response_id' => $responseId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'data' => !empty($data) ? json_encode($data) : null,
        ]);
    }
}

// Usage in PollService.php
public function createVote(array $data, ?User $user): Vote {
    $vote = $this->saveVote($data, $user);
    
    $this->log->log('vote.created', $vote->id, $user?->id, null, [
        'title' => $vote->title,
    ]);
    
    return $vote;
}
```

---

## 5. Implementation Phases

### Phase 1: Foundation (Backend Heavy)
- [x] File structure setup
- [ ] Database abstraction (SQLite + MySQL)
- [ ] Router and bootstrap
- [ ] User authentication (email/password)
- [ ] Poll CRUD API
- [ ] Question/Option CRUD API
- [ ] Basic templates (layout, home)
- [ ] Install script

### Phase 2: Form Builder (Frontend Heavy)
- [ ] Builder page template
- [ ] Builder JS: state management
- [ ] Builder JS: drag-and-drop (SortableJS)
- [ ] Input type: single choice
- [ ] Input type: approval
- [ ] Auto-save to localStorage
- [ ] Save draft to server for logged-in users

### Phase 3: Voting Flow
- [ ] Voter form rendering
- [ ] Response submission API
- [ ] Voter token (edit own vote)
- [ ] Access control (password, tokens)
- [-] Voter-facing i18n (delayed for now)

### Phase 4: Admin & Results
- [ ] Admin panel template
- [ ] Close/reopen poll
- [ ] Raw results display (approval matrix style)
- [ ] Action log viewer (per-poll)
- [ ] Export (JSON, CSV, PrefLib)

### Phase 5: Polish & Compliance
- [ ] User dashboard
- [ ] Email invitations
- [ ] Terms of Service page
- [ ] Privacy policy page
- [ ] Data export (GDPR)
- [ ] Account deletion
- [ ] Report button
- [ ] "Contribute to research" toggle
- [ ] Add "duplicate this poll" feature (copies all questions/options, but not responses)

### Phase 6: Additional Input Types
- [ ] Rankings (full)
- [ ] Truncated rankings
- [ ] Rankings with ties
- [ ] Utility assignment
- [ ] Star rating
- [ ] Grades (Majority Judgment)
- [ ] Yes/No/Abstain

### Future Phases
- [ ] Voting rule computation
- [ ] Results builder interface
- [ ] OAuth login
- [ ] Magic links
- [ ] Passkeys

---

## 6. Things That Would Be Painful Later (as requested)

These are fine to defer but worth knowing:

1. **Per-question visibility**: The schema supports it, but UI/logic adds complexity. If you're unsure you want it, we could drop `questions.visibility` for now.

2. **Write-in candidates**: Schema supports it (see "Write-In Candidates" above), but **aggregation logic** is the complex part — grouping similar write-ins, handling them in voting rules, displaying in results. Recommend deferring the aggregation work.

3. **"Throw away the key" mode**: Would need a `polls.admin_locked` flag that, once set, prevents even admin edits. Easy to add but worth deciding the UX.

4. **Multiple privacy settings per form**: Schema supports it, but the UI for configuring this and the logic for displaying responses gets complex. Could simplify to form-level only for v1.

5. **Email sending reliability**: PHP `mail()` often fails on shared hosting. Might want to require SMTP config from the start, or make email features (option D) require it.