# vote-app
Allows people to hold polls online

Requires PHP and Node.js 20.19+ or 22.12+.

PHP must have the `intl` extension (ICU MessageFormatter) enabled.

```sh
npm ci
npm run build
php -S localhost:8005
```

PHP reads Vite's manifest in `assets/build/.vite/manifest.json` to serve hashed
JavaScript and CSS, including shared modules. The same build works at the root
URL and in a subfolder such as `/vote/`. Node.js is only needed when building;
the production server serves PHP and the generated static files.

Run `npm run watch` in another terminal while editing frontend files. E2E test
scripts build the assets automatically. Run `npm run build` before `phpunit`.
GitHub Actions builds the assets before testing and deploying. Deployment uploads
the hashed files first and retains previous hashes for already-open pages, then
uploads the application and its new manifest.

## Internationalization

The interface language is independent of a poll's voter-facing language. A saved
`ui_locale` cookie takes priority over the signed-in account's saved preference,
then the browser's weighted Accept-Language header, then English. The header
selector saves the preference with a CSRF-protected POST, a same-site HTTP-only
cookie, and the signed-in account's `preferred_locale`. A new poll starts in the
interface language; existing polls keep their own language. The builder loads a
preview catalog on demand without switching the editor's language.

`src/i18n/languages.json` registers native names and text direction.
`src/i18n/locales/{locale}.json` contains plain-text ICU MessageFormat messages
shared by PHP and JavaScript. Translator notes for ambiguous labels and their
locations live in `src/i18n/message-contexts.json`. For example:

```json
{
  "greeting": "Hello {name}",
  "response_count": "{count, plural, one {# response} other {# responses}}"
}
```

Use `__('key', ['name' => $name])` in PHP and `t('key', { name })` in JavaScript.
PHP callers escape HTML at the output location with `e()`. JavaScript uses
`textContent` for text, or `tHtml()` for an escaped message in HTML markup. Keep
links and other markup in templates/DOM code rather than translation messages.
ICU literal apostrophes are doubled (`l''ordre`); named parameters use braces.
Provide every argument declared by a message. Missing keys fall back to English,
using English formatting rules; unsupported locales never become file paths.

For an explicit backend language, including recipient-specific emails,
use `Translator::translateForLocale($locale, $key, $params)`; this does not change
the current renderer's locale. Public polls use the poll's locale for content
and the preferred interface locale for site navigation. HTML and embedded
components expose the effective `lang` and `dir` values. A future RTL catalog
also needs visual verification of the relevant layouts before release.

```sh
npm run check:i18n
npm run test:i18n
phpunit --filter 'MessageFormatTest|UiLocaleTest|TranslatorTest|LanguagesTest'
```

Every build checks ICU syntax, registered catalogs, source keys, duplicate keys,
argument parity and absence of embedded HTML. Existing translation gaps are
listed explicitly in `src/i18n/coverage-baseline.json`; CI rejects new gaps and
requires resolved ones to be removed. `npm run i18n:baseline` regenerates that
list for deliberate, reviewed scope changes. Do not use it to silently waive new
missing translations. The PHP and JS tests share one message corpus, including
plural categories beyond the currently available languages.

The English source and all twelve translated catalogs cover the creator,
administration, authentication, voter, validation, email and analysis flows.
Available languages are Bengali, Croatian, Dutch, English, French, German,
Hindi, Italian, Polish, Portuguese, Romanian, Russian and Spanish. Voting-rule
names follow established terminology; machine identifiers, participant-authored
poll text and the privacy-policy document remain in their original language.
The policy's English content is explicitly marked `lang="en"`.

Verification/reset emails and response notifications use the recipient's saved
language; invitations use the poll language. New accounts save the effective UI
language. Existing accounts keep a nullable preference until they choose one.
The portable `003_user_locale.sql` migration runs through the normal migration
service on existing installations.

Shared report caches contain locale-neutral facts and stable rule identifiers.
Labels, errors, anonymous voter numbers and calculation explanations are
localized when serializing each response. Old caches are regenerated once.
Public response matrices redact voter names independently of who warmed the
cache; admin views retain authorized names. Complete preview catalogs skip the
English catalog request, and PHP sends only genuinely missing fallback messages.
