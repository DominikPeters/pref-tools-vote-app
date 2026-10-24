# vote-app
Allows people to hold polls online

Requires PHP and Node.js 20.19+ or 22.12+.

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
