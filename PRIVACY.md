# Privacy Information for Pref.Tools Vote

This document lists features and behaviors of the Pref.Tools Vote application that are relevant for GDPR compliance and privacy policy drafting.

## 1. Data Collection and Processing

### 1.1 User Accounts
When a user registers for an account, the following data is collected:
- **Name**: Provided by the user.
- **Email Address**: Used for authentication, password resets, and account verification.
- **Password**: Stored as a secure hash (never in plain text).
- **Role**: Defaults to `user`, but can be elevated to `sysadmin`.

**Relevant Files:**
- `src/Models/User.php`
- `src/Controllers/AuthApiController.php`

### 1.2 Poll Creators
Poll creators provide content that is stored in the database:
- **Poll Details**: Title, description.
- **Question Details**: Text, description.
- **Option Details**: Labels, descriptions.

**Relevant Files:**
- `src/Models/Poll.php`
- `src/Models/Question.php`
- `src/Models/Option.php`

### 1.3 Voters and Responses
When a voter submits a response, the following data may be collected:
- **Voter Name**: Only if the poll is configured to "collect names" and the voter provides one.
- **Answers**: The voter's choices/responses to poll questions.
- **IP Address**: Collected for non-secret-ballot responses only. **Not collected for secret ballots.**
- **User Agent**: Collected for non-secret-ballot responses only. **Not collected for secret ballots.**
- **Voter Token**: A unique random token stored in a cookie (`voter_token_{publicId}`) to allow voters to edit their responses later (if permitted by poll settings). **Not set for secret ballots.**

**Relevant Files:**
- `src/Models/Response.php`
- `src/Models/Answer.php`
- `src/Controllers/PollApiController.php` (see `submitResponse`)

### 1.4 Email Invitations
If a poll creator uses the email invitation feature:
- **Recipient Email Addresses**: Stored in the `email_invitations` table.
- **Tracking**: The system tracks when an invitation is sent, clicked, and used.

**Relevant Files:**
- `src/Models/EmailInvitation.php`
- `src/Services/InvitationService.php`

## 2. Tracking and Logging

### 2.1 Action Logs
The system maintains an `action_log` table for auditing and security, visible only to the sysadmin. Every significant action (login, poll creation, voting, etc.) is logged with:
- **User ID** (if authenticated)
- **Poll ID** (if applicable)
- **Response ID** (if applicable)
- **Action Name**
- **IP Address**
- **Timestamp**
- **Additional Data** (JSON)

**Relevant Files:**
- `src/Services/LogService.php`
- `migrations/001_initial_schema.sql` (table `action_log`)

### 2.2 Cookies
- **Session Cookie**: Used for user authentication.
- **Voter Token Cookie**: `voter_token_{publicId}` is used to link a visitor to their specific response for editing purposes.

## 3. Anonymity and Privacy Settings

### 3.1 Voting Modes
Polls can be configured with different voting modes:
- **Open**: Anyone with the link can vote.
- **Identified**: Requires a unique token or email invitation. Links the response to the identity.
- **Secret Ballot**: Specifically designed for maximum anonymity.
    - No identity is linked to the response in the database.
    - No `voter_token` cookie is set.
    - **No IP address or user agent is stored** for the response.
    - Access tokens/invitations are marked as used but **no timestamp is recorded** (prevents timing correlation attacks).
    - **No action log entry** is created for `response.submitted` (prevents correlation).
    - Responses cannot be edited or deleted by the voter after submission.

**Relevant Files:**
- `src/Models/Poll.php` (see `votingMode`)
- `src/Models/Response.php` (see `create` method with `$isSecretBallot` parameter)
- `src/Controllers/PollApiController.php` (see `submitResponse`)

### 3.2 Visibility Settings
Poll owners can control the visibility of responses:
- **Visibility**: `private`, `anonymous` (responses visible but no names), `names_only`, `full`.
- **Timing**: `during` (immediate results) or `after_close` (results only visible after poll ends).

## 4. Third-Party Services

### 4.1 Cloudflare Turnstile
Used for CAPTCHA protection on registration and anonymous poll creation.
- **Data Shared**: The user's IP address and browser fingerprint are sent to Cloudflare in the U.S. for verification.

**Relevant Files:**
- `src/Services/TurnstileService.php`

### 4.2 OpenAI Moderation
Used to prevent the creation of harmful or inappropriate content.
- **Data Shared**: Poll titles, descriptions, question text, and option labels are sent to OpenAI's Moderation API, hosted in the U.S.

**Relevant Files:**
- `src/Services/ModerationService.php`

### 4.3 SMTP / Email Provider
Emails are sent through a configured SMTP server. In production, this SMTP server is typically provided by a third-party email service (e.g., SendGrid, Mailgun).
- **Data Shared**: Recipient email addresses and email content.

**Relevant Files:**
- `src/Services/MailService.php`

## 5. User Rights (GDPR)

### 5.1 Right to Erasure (Deletion)
- **Poll Deletion**: Poll owners can delete their polls, which performs a cascading delete of all questions, options, responses, and answers.
- **User Self-Deletion**: Users can delete their own account from the dashboard. This requires password confirmation and offers two options for owned polls:
    - "Delete All Polls": Permanently deletes all polls and their responses.
    - "Keep Polls": Unlinks polls from the account (sets `user_id = null`) so they remain accessible via admin links.
    - User's responses to other polls are orphaned (user_id set to null).
    - Sysadmins cannot delete their own account (must be demoted first).
- **User Deletion by Admin**: Sysadmins can delete any user account from the admin panel.
- **Response Deletion**: Voters can delete their own response if they have the `voter_token` cookie and the poll settings allow editing.
- **Delete All Responses**: Poll admins can delete all responses at once from the poll admin page (with confirmation).
- **Response Withdrawal**: Voters can withdraw their response at any time (except for secret ballots), regardless of poll edit settings:
    - Deletes all answer data (the actual vote content)
    - Clears personal data: voter_name, ip_address, user_agent
    - Preserves voter_token, access_token_id, user_id to **prevent re-voting**
    - Response is marked as `status='withdrawn'` with `withdrawn_at` timestamp
    - Withdrawn responses are excluded from results and exports
    - Poll admins can see withdrawal statistics (X active, Y withdrawn)

**Relevant Files:**
- `src/Models/Poll.php::delete()`
- `src/Models/User.php::delete()`
- `src/Models/Response.php::delete()`
- `src/Models/Response.php::withdraw()`
- `src/Controllers/PollApiController.php::withdrawResponse()`
- `src/Controllers/PollApiController.php::deleteAllResponses()`
- `src/Controllers/AuthApiController.php::deleteAccount()`
- `src/Controllers/AuthApiController.php::deletionPreview()`
- `assets/js/dashboard.js` (delete account UI)

### 5.2 Right to Object (Unsubscribe)
Users can opt-out of receiving email invitations. Unsubscribed emails are stored in the `email_deliverability` table.

**Relevant Files:**
- `src/Services/UnsubscribeService.php`
- `src/Models/EmailDeliverability.php`

### 5.3 Data Access and Portability

#### For Poll Owners
Poll owners can export poll data (including all responses) in JSON, CSV, or PrefLib formats.

#### For Logged-In Users (GDPR Art. 15 & 20)
Logged-in users can access and export all personal data stored about them from the dashboard:
- **View My Data** (`GET /api/user/data`): Shows all stored information including:
    - Profile information (email, name, account dates)
    - All poll responses with full metadata (IP address, user agent, timestamps, answers)
    - Activity logs (actions, timestamps, IP addresses)
- **Export My Data** (`GET /api/user/export`): Downloads a portable JSON file containing:
    - Profile information
    - Polls created by the user
    - Poll responses submitted by the user

**Note**: Anonymous voters (not logged in) cannot access data exports, as there is no reliable way to verify their identity.

**Relevant Files:**
- `src/Controllers/PollApiController.php` (see `export`)
- `src/Controllers/AuthApiController.php` (see `userData`, `exportData`)
- `assets/js/dashboard.js`

### 5.4 Data Retention and Automatic Cleanup

IP addresses and user agents are automatically anonymized (set to NULL) after a configurable retention period:
- **Default retention**: 90 days
- **Configurable**: Sysadmins can adjust via `privacy.retention_days` setting
- **Affected data**:
    - `action_log` table: IP addresses older than retention period
    - `responses` table: IP addresses and user agents older than retention period
- **Trigger**: Cleanup runs automatically with 10% probability on each page load, then checks if 24 hours have passed since last cleanup

**Relevant Files:**
- `src/Services/CleanupService.php`
- `src/Models/SiteSetting.php` (see `privacy.retention_days`, `privacy.last_cleanup`)
