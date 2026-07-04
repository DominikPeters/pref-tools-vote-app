# i18n String Replacement Instructions

This document provides instructions for extracting and replacing hardcoded strings with translation keys. The i18n infrastructure is already in place - this task is about the actual string replacements.

## Important: Document Your Keys!

Translation files should be self-documenting. When adding keys to `src/i18n/en.php`:

1. **Use section dividers** to group related keys:
   ```php
   // =========================================================================
   // Poll Form (templates/poll.php)
   // =========================================================================
   ```

2. **Add inline comments** to clarify context:
   ```php
   'your_name' => 'Your Name', // label for voter name input field
   'submit_vote' => 'Submit Vote', // primary submit button on poll form
   'remaining' => 'Remaining:', // distribution question - shows points left
   ```

3. **Explain ambiguous terms** - translators need context:
   ```php
   'save' => 'Save', // save poll as draft (not "save money")
   'close' => 'Close', // close the poll to new votes (not "close window")
   ```

See `src/i18n/en.php` for the established format with sections and comments.

## Scope

**In scope (public-facing pages):**
- `templates/poll.php` - Voter form
- `templates/results.php` - Public results page
- `assets/js/question-renderer.js` - Question rendering
- `assets/js/results-core.js` - Results rendering
- `assets/js/results.js` - Results page logic
- `assets/js/report-types/*.js` - Report renderers (ALREADY DONE - see below)

**Out of scope (admin/builder - English only for now):**
- `templates/builder.php`
- `assets/js/builder.js`
- Admin pages, sysadmin pages

## How It Works

### PHP Templates
Use the `__()` helper function:
```php
// Before
<label>Your Name</label>
<button>Submit Vote</button>

// After
<label><?= __('your_name') ?></label>
<button><?= __('submit_vote') ?></button>
```

### JavaScript
Import and use the `t()` function from `i18n.js`:
```javascript
// At top of file
import { t } from './i18n.js';

// Before
hint.textContent = 'Drag to reorder (top = best)';

// After
hint.textContent = t('ranking_hint');
```

### Adding Translation Keys
Add new keys to `src/i18n/en.php` in the appropriate section with context comments:
```php
return [
    // =========================================================================
    // Poll Form (templates/poll.php)
    // =========================================================================

    'your_name' => 'Your Name', // label for voter name input field
    'submit_vote' => 'Submit Vote', // primary submit button

    // =========================================================================
    // Question Types (assets/js/question-renderer.js)
    // =========================================================================

    'ranking_hint' => 'Drag to reorder (top = best)', // hint shown below ranking questions
];
```

## Strings to Replace

### templates/poll.php

| Line | Current String | Translation Key |
|------|---------------|-----------------|
| ~31 | "Preview Mode - This is how your poll will appear to voters." | `preview_mode_message` |
| ~35 | "This poll is not yet open for submissions." | `poll_not_open` |
| ~48 | "Thank you!" | `thank_you` |
| ~49 | "Your response has already been recorded." | `response_recorded` |
| ~53 | "View Results" | `view_results` |
| ~57 | "You can now close this page." | `can_close_page` |
| ~64 | "You have already submitted a response. You can update it below." | `already_submitted_can_update` |
| ~70 | "Your Name" | `your_name` |
| ~82 | "Submit Vote (Disabled in Preview)" | `submit_disabled_preview` |
| ~84 | "Update Response" | `update_response` |
| ~84 | "Submit Vote" | `submit_vote` |
| ~91 | "Voting is Closed" | `voting_closed` |
| ~92 | "This poll is no longer accepting responses." | `poll_no_longer_accepting` |
| ~106 | "Report this poll" | `report_poll` |

### templates/results.php

| Line | Current String | Translation Key |
|------|---------------|-----------------|
| ~11 | "Poll" (breadcrumb) | `poll` |
| ~13 | "Results" (breadcrumb) | `results` |
| ~17 | "Results" (h1) | `results` |
| ~20 | "Live Results" | `live_results` |
| ~26 | "Loading results..." | `loading_results` |
| ~31 | "Back to Poll" | `back_to_poll` |
| ~37 | "Report this poll" | `report_poll` |

### assets/js/question-renderer.js

| Location | Current String | Translation Key |
|----------|---------------|-----------------|
| Ranking hint | "Drag to reorder (top = best)" | `ranking_hint` |
| Ranking with ties | "Drag to reorder. Items in the same group are tied." | `ranking_ties_hint` |
| Truncated ranking | "Available options" | `available_options` |
| Truncated ranking | "Your ranking" | `your_ranking` |
| Truncated ranking | "Drag options here to rank them" | `drag_to_rank` |
| Grade dropdown | "Select..." | `select_placeholder` |
| Default grades | ["Excellent", "Very Good", "Good", "Fair", "Poor", "Reject"] | Use existing `grade_*` keys |
| Yes/No/Abstain | "Yes", "No", "Abstain" | Use existing `yes`, `no`, `abstain` keys |
| Distribution | "Remaining:" | `remaining` |
| Distribution | "points" | `points` |
| Text placeholders | "Short answer", "Long answer" | `short_answer`, `long_answer` |

### assets/js/results-core.js

| Location | Current String | Translation Key |
|----------|---------------|-----------------|
| Summary | "response" / "responses" | `response_count` (use plural: `:count response|:count responses`) |
| Summary | "question" / "questions" | `question_count` (use plural) |
| Date format | "Closed {date}" | `closed_on` |
| Date format | "Created {date}" | `created_on` |
| Empty state | "No responses yet." | `no_responses` |

### assets/js/results.js

| Location | Current String | Translation Key |
|----------|---------------|-----------------|
| Loading | "Loading results..." | `loading_results` |
| Error | Various error messages | `error_*` keys |

### assets/js/report-types/*.js

Check each report type file for hardcoded strings like:
- "Winner:", "Tied:", "No winner"
- Column headers
- Labels

## Plural Forms

For strings that need pluralization, use the `|` separator:
```php
'response_count' => ':count response|:count responses',
'question_count' => ':count question|:count questions',
```

In JavaScript:
```javascript
t('response_count', { count: 5 })  // => "5 responses"
t('response_count', { count: 1 })  // => "1 response"
```

## Date Formatting

Use the `formatDate()` helper from `i18n.js`:
```javascript
import { t, formatDate } from './i18n.js';

// Instead of: date.toLocaleDateString('en-US', {...})
formatDate(date)  // Uses locale from window.LOCALE
```

## Step-by-Step Process

1. **Check `src/i18n/en.php` first** - many keys are already defined! Use existing keys when possible.
2. **For each file**, identify all hardcoded user-facing strings
3. **Choose a key name** - use snake_case, be descriptive but concise
4. **Add the key to `src/i18n/en.php`** in the appropriate section with a context comment
5. **Replace the hardcoded string** with `__('key')` (PHP) or `t('key')` (JS)
6. **For JS files**, add the import at the top: `import { t } from './i18n.js';`
7. **Test** by viewing the page - strings should appear unchanged (since we're using English)

## Testing

After replacements:
1. View a poll page - all text should display correctly
2. View a results page - all text should display correctly
3. Check the browser console for any missing translation warnings (key shown instead of text)
4. Run `phpunit` to ensure no PHP syntax errors

## Voting Rule Names and Descriptions

Voting rules (from `*Registry.php` files) need special handling because:
- Many are **eponyms** (Schulze, Borda, Copeland) that should stay in English
- Some have **well-known translations** (Majority Judgment → Jugement Majoritaire)
- The **registry is the English source of truth** - don't duplicate in en.php

### Architecture

```
Registry files (PHP)          →  English names/descriptions (source of truth)
                                     ↓
fr.php / de.php / etc.        →  Only rules with known translations
                                     ↓
JS report renderers           →  tFallback('rule_xyz', registryName)
                                     ↓
                              →  Uses translation if exists, else English from registry
```

### How to Translate a Rule

1. **In JS** - Use `tFallback()` with the registry value as fallback:
```javascript
import { tFallback } from './i18n.js';

// rule = { key: 'majority_judgment', name: 'Majority Judgment', description: '...' }
const displayName = tFallback(`rule_${rule.key}`, rule.name);
const displayDesc = tFallback(`rule_${rule.key}_desc`, rule.description);
```

2. **In fr.php** (or other language files) - Add only rules that need translation:
```php
// Only rules with well-known French translations
'rule_majority_judgment' => 'Jugement Majoritaire',
'rule_majority_judgment_desc' => 'Le gagnant a la meilleure mention médiane',
'rule_plurality' => 'Pluralité',
// Don't add rule_schulze - it stays "Schulze" in all languages
```

3. **In en.php** - Do NOT add rule keys (registry is the source)

### Registry Files and Their Rules

| Registry | Rules to Translate (French) |
|----------|---------------------------|
| `VotingRulesRegistry.php` | majority_judgment, plurality, score_sum, score_mean |
| `MultiwinnerRulesRegistry.php` | av (Approval), equal_shares |
| `ApportionmentRulesRegistry.php` | hamilton, quota |
| `PBRulesRegistry.php` | mes (Equal Shares), greedy |
| `SocialWelfareFunctionRegistry.php` | (most are eponyms - keep English) |

### Rules That Stay in English (Eponyms)

These should NOT be added to translation files:
- Schulze, Borda, Copeland, Kemeny-Young (named after people)
- D'Hondt, Jefferson, Sainte-Laguë, Webster, Huntington-Hill, Dean, Adams
- Meek's STV, Warren's STV, Scottish STV (named variants)
- STAR Voting (acronym)

## Notes

- Don't translate user-provided content (poll titles, question text, option labels)
- Only translate system UI strings (buttons, labels, hints, status messages)
- The `window.TRANSLATIONS` and `window.LOCALE` are already embedded in poll.php and results.php
- Keys that already exist in en.php can be reused (check the file first)

## Already Completed

The following files have already been updated with i18n support:

**Report type renderers** (`assets/js/report-types/*.js`):
- `voting-rule-winner.js` - Uses `t()` and `tFallback()` for rule names
- `multi-rule-comparison.js` - Translated headers, labels, rule names
- `majority-judgment.js` - Rule name translation
- `approval-winner.js` - Winner/tie labels
- `condorcet-winner.js` - All UI strings
- `pb-winner.js` - Budget labels, rule names
- `multiwinner.js` - Committee labels, rule names
- `multiwinner-multi-rule-comparison.js` - All table headers and labels
- `apportionment-winner.js` - Seat/vote counts, rule names
- `apportionment-multi-rule-comparison.js` - All table headers
- `rank-aggregation.js` - SWF names, ranking labels

**Still needs string replacement:**
- `templates/poll.php` - PHP template strings
- `templates/results.php` - PHP template strings
- `assets/js/question-renderer.js` - Question type UI strings
- `assets/js/results-core.js` - Results summary strings
- `assets/js/results.js` - Loading/error messages
