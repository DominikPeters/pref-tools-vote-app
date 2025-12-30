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
- **IP Address**: Collected for every response and stored in the `responses` table.
- **User Agent**: Collected for every response to identify the browser/device.
- **Voter Token**: A unique random token stored in a cookie (`voter_token_{publicId}`) to allow voters to edit their responses later (if permitted by poll settings).

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
- **Secret Ballot**: Specifically designed for anonymity.
    - No identity is linked to the response in the database.
    - No `voter_token` cookie is set.
    - Responses cannot be edited or deleted by the voter after submission.

**Relevant Files:**
- `src/Models/Poll.php` (see `votingMode`)
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
- **User Deletion**: Sysadmins can delete user accounts. (Note: A self-service account deletion feature is currently not implemented in the UI).
- **Response Deletion**: Voters can delete their own response if they have the `voter_token` cookie and the poll settings allow editing.

**Relevant Files:**
- `src/Models/Poll.php::delete()`
- `src/Models/User.php::delete()`
- `src/Models/Response.php::delete()`

### 5.2 Right to Object (Unsubscribe)
Users can opt-out of receiving email invitations. Unsubscribed emails are stored in the `email_deliverability` table.

**Relevant Files:**
- `src/Services/UnsubscribeService.php`
- `src/Models/EmailDeliverability.php`

### 5.3 Data Access and Portability
Poll owners can export poll data (including all responses) in JSON, CSV, or PrefLib formats.

**Relevant Files:**
- `src/Controllers/PollApiController.php` (see `export`)
