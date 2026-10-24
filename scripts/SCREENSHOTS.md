# Localized homepage screenshots

Generate the actual ballot, nine question types, and public results in every registered
language, in both light and dark mode:

```sh
npm ci
npm run build
npx playwright install chromium
npm run screenshots:generate
```

Use Node.js 24 LTS for the capture runtime. PHP with SQLite and `intl`, Chromium from
Playwright, and the libwebp `cwebp` executable are required. Install libwebp with
`brew install webp` on macOS or `apt install webp` on Debian/Ubuntu. `PHP_BIN` and
`CWEBP_BIN` may select explicit executable paths.

The capture command starts its own disposable application at `http://127.0.0.1:18082`.
No development server, production URL, account credentials, or existing database is
required. The fixture has a fresh configuration, SQLite database, session name, disabled
mail and moderation, and a temporary administrator. It copies application source and
templates, shares read-only runtime assets/libraries, and deletes its own polls in
`finally` before removing the temporary application. A per-run marker prevents using
another server accidentally. No E2E files or MailHog state are read or modified.

Browser capture blocks all requests outside the fixture origin. The isolated template
uses the fixture-only local copy of the existing Sortable 1.15.6 dependency; production
HTML is unchanged. Its source and MIT license are in `scripts/resources/`.

## Targeted regeneration

```sh
node scripts/capture_screenshots.js --locale=fr,hu
node scripts/capture_screenshots.js --locale=nl --kind=questions
node scripts/capture_results_screenshots.js --locale=pl
node scripts/capture_screenshots.js --port=18083
```

`--kind=all` is the default. The only accepted options are `--locale`, `--kind`, and
`--port`; remote base URLs are never accepted. A locale must be registered and have
both a full UI catalog and all 19 example strings in `scripts/screenshot-examples.json`.
Add those example translations when adding a language. Example grade labels reuse
that language's UI catalog through the shared ICU translator.

Each locale uses the same four deterministic ballots. The fixture's poll date is fixed,
option randomization is disabled, browser viewport/scale/theme are fixed, and captures
wait for document fonts and rendered reports without arbitrary animation delays.
Images are screenshots of the production renderers, not recreated mockups.

## Output and validation

The PNG capture buffer is encoded as actual WebP by libwebp. Files live at:

```
screenshots/localized/<locale>/<stem>-<light|dark>-<sha256-prefix>.webp
```

`screenshots/manifest.json` records the relative path, pixel dimensions, and full SHA-256
of each image. It is replaced atomically after both themes for a locale finish. Targeted
runs preserve other locale/theme entries. Content-hashed images are immutable and can
be deployed before PHP and the manifest; older hashes may remain cached safely. The
original English PNGs remain available as the initial-generation fallback.

`ScreenshotAssets::forLocale($stem, $locale, $basePath)` returns the light/dark URLs,
image dimensions, fingerprints and effective language. Missing localized pairs fall back
together to English so a picture never mixes languages across themes.

```sh
node --test tests/js/screenshot-capture.test.mjs
phpunit --filter ScreenshotAssetsTest
```

The checks reject remote targets and incomplete example translations, and validate
that every registered locale has all 11 light/dark image pairs with matching actual
file dimensions and hashes. Review the generated images visually before committing.
