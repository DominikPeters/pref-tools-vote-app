# E2E Test Observations

This file tracks unimplemented features, bugs, and other observations discovered during E2E testing.

## Tested Features

- **Undo Deletion**: Successfully tested undoing deletion of questions and options in the builder.

## Tested Features

- **Undo Deletion**: Successfully tested undoing deletion of questions and options in the builder.

## Unimplemented Features

- **Poll Password Protection**: Accessing a poll with `access_mode = 'password'` currently doesn't seem to block access or show a password prompt.
- **Identified Access Mode (Tokens/Invitations)**: Accessing a poll with `voting_mode = 'identified'` or `'secret_ballot'` without a token currently doesn't seem to block access or show an "Access Required" message.
- **Voter Names in Public Results**: In `'full'` visibility mode, voter names are not currently displayed on the public results page or within reports.

## Apparent Bugs

- **Participatory Budgeting Budget Enforcement**: (Fixed) The UI was treating `max` as a count of items rather than a total cost limit. *Update: User clarified that count-based limit is intentional, so the "fix" was reverted to match desired behavior.*
- **Results Page Header**: The results page `<h1>` contains the poll title, while tests were expecting "Results & Analysis" or "Results".

## Environment Constraints

- **Email Testing**: Tests for email invitations are limited because no SMTP server is configured in the test environment.
- **Content Moderation**: OpenAI API-based moderation cannot be tested without a valid API key.
