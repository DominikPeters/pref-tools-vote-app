-- Initial database schema for Pref.Tools Vote
-- Compatible with both SQLite and MySQL

-- Users (optional accounts)
CREATE TABLE users (
    id                         INTEGER PRIMARY KEY AUTO_INCREMENT,
    email                      VARCHAR(255) UNIQUE NOT NULL,
    name                       VARCHAR(255) NOT NULL,
    password_hash              VARCHAR(255) NOT NULL,
    role                       VARCHAR(20) NOT NULL DEFAULT 'user',
    email_verified_at          DATETIME NULL,
    email_verification_token   VARCHAR(64) NULL,
    email_verification_expires DATETIME NULL,
    password_reset_token       VARCHAR(64) NULL,
    password_reset_expires     DATETIME NULL,
    created_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Polls (a form/poll instance)
CREATE TABLE polls (
    id                INTEGER PRIMARY KEY AUTO_INCREMENT,
    public_id         VARCHAR(16) UNIQUE NOT NULL,
    admin_token       VARCHAR(32) NOT NULL,
    user_id           INTEGER NULL,

    title             VARCHAR(500) NOT NULL,
    description       TEXT NULL,

    status            VARCHAR(20) NOT NULL DEFAULT 'draft',
    visibility        VARCHAR(20) NOT NULL DEFAULT 'private',
    visibility_timing VARCHAR(20) NOT NULL DEFAULT 'after_close',
    collect_name      BOOLEAN NOT NULL DEFAULT 0,
    name_visibility   VARCHAR(20) NULL,
    allow_edit_own    BOOLEAN NOT NULL DEFAULT 1,
    allow_edit_any    BOOLEAN NOT NULL DEFAULT 0,
    randomize_options BOOLEAN NOT NULL DEFAULT 0,

    access_mode       VARCHAR(20) NOT NULL DEFAULT 'link',
    access_password   VARCHAR(255) NULL,

    voting_mode       VARCHAR(20) NOT NULL DEFAULT 'open',
    access_methods    TEXT NULL,
    mode_locked_at    DATETIME NULL,

    locale            VARCHAR(10) NOT NULL DEFAULT 'en',

    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at         DATETIME NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Questions within a poll
CREATE TABLE questions (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    poll_id         INTEGER NOT NULL,
    sort_order      INTEGER NOT NULL DEFAULT 0,

    type            VARCHAR(30) NOT NULL,
    text            VARCHAR(1000) NOT NULL,
    description     TEXT NULL,
    required        BOOLEAN NOT NULL DEFAULT 1,

    settings        TEXT NULL,

    visibility      VARCHAR(20) NULL,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
);

-- Options/candidates within a question
CREATE TABLE options (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    question_id     INTEGER NOT NULL,
    sort_order      INTEGER NOT NULL DEFAULT 0,

    label           VARCHAR(500) NOT NULL,
    description     TEXT NULL,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Voter responses (one per submission)
CREATE TABLE responses (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    poll_id         INTEGER NOT NULL,

    voter_name      VARCHAR(255) NULL,
    voter_token     VARCHAR(64) NULL,
    access_token_id INTEGER NULL,
    user_id         INTEGER NULL,

    ip_address      VARCHAR(45) NULL,
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

    value_text      TEXT NULL,
    value_choice    INTEGER NULL,
    value_json      TEXT NULL,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Reports (configurable result analyses per question)
CREATE TABLE reports (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    question_id     INTEGER NOT NULL,
    report_type     VARCHAR(50) NOT NULL,
    config          TEXT NULL,
    position        INTEGER NOT NULL DEFAULT 0,
    is_public       BOOLEAN NOT NULL DEFAULT 0,
    cached_result   TEXT NULL,
    computed_at     DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Access tokens (for token-based access mode)
CREATE TABLE access_tokens (
    id               INTEGER PRIMARY KEY AUTO_INCREMENT,
    poll_id          INTEGER NOT NULL,
    token            VARCHAR(32) UNIQUE NOT NULL,

    label            VARCHAR(255) NULL,
    used_at          DATETIME NULL,
    response_id      INTEGER NULL,
    is_secret_ballot BOOLEAN NOT NULL DEFAULT 0,

    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE SET NULL
);

-- Email invitations (for email-based access mode)
CREATE TABLE email_invitations (
    id               INTEGER PRIMARY KEY AUTO_INCREMENT,
    poll_id          INTEGER NOT NULL,
    email            VARCHAR(255) NOT NULL,
    token            VARCHAR(32) UNIQUE NOT NULL,

    sent_at          DATETIME NULL,
    clicked_at       DATETIME NULL,
    used_at          DATETIME NULL,
    response_id      INTEGER NULL,
    is_secret_ballot BOOLEAN NOT NULL DEFAULT 0,

    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE SET NULL
);

-- Email deliverability tracking (unsubscribes, future: soft blocks)
CREATE TABLE email_deliverability (
    email           VARCHAR(255) PRIMARY KEY,
    unsubscribed_at DATETIME NULL,
    data            TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Action log (for auditing/debugging)
CREATE TABLE action_log (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    user_id         INTEGER NULL,
    poll_id         INTEGER NULL,
    response_id     INTEGER NULL,

    action          VARCHAR(50) NOT NULL,
    ip_address      VARCHAR(45) NULL,
    data            TEXT NULL
);

-- Site settings (key-value configuration store)
CREATE TABLE site_settings (
    `key`           VARCHAR(100) PRIMARY KEY,
    value           TEXT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_polls_public_id ON polls(public_id);
CREATE INDEX idx_polls_user_id ON polls(user_id);
CREATE INDEX idx_questions_poll_id ON questions(poll_id);
CREATE INDEX idx_options_question_id ON options(question_id);
CREATE INDEX idx_responses_poll_id ON responses(poll_id);
CREATE INDEX idx_responses_voter_token ON responses(voter_token);
CREATE INDEX idx_answers_response_id ON answers(response_id);
CREATE INDEX idx_reports_question_id ON reports(question_id);
CREATE INDEX idx_access_tokens_token ON access_tokens(token);
CREATE INDEX idx_email_invitations_token ON email_invitations(token);
CREATE INDEX idx_action_log_poll_id ON action_log(poll_id);
CREATE INDEX idx_action_log_user_id ON action_log(user_id);
CREATE INDEX idx_action_log_created_at ON action_log(created_at);
