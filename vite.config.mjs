import { readFileSync, readdirSync } from 'node:fs';
import { defineConfig } from 'vite';

// PHP renders the HTML; Vite builds the page scripts and their shared imports.
const scripts = [
    'app', 'admin', 'builder', 'dashboard', 'embed-poll', 'poll',
    'responses-admin', 'results', 'results-admin', 'stats-charts', 'sysadmin',
];
const input = Object.fromEntries([
    ...scripts.map(name => [`js/${name}`, `assets/js/${name}.js`]),
    ...readdirSync('assets/css').filter(name => name.endsWith('.css'))
        .map(name => [`css/${name.slice(0, -4)}`, `assets/css/${name}`]),
]);

export default defineConfig({
    // Keep imports relative so one build works at / and at /vote/.
    base: './',
    publicDir: false,
    input,
    plugins: [{
        name: 'standalone-embed-script',
        buildStart() {
            // External sites use a classic script tag. Keep the self-contained
            // web component classic, while giving it a content-hashed URL too.
            const path = 'assets/js/embed/vote-poll.js';
            this.addWatchFile(path);
            this.emitFile({ type: 'asset', name: 'vote-poll.js', originalFileName: path, source: readFileSync(path) });
        },
    }],
    build: {
        outDir: 'assets/build',
        manifest: true,
        modulePreload: false,
    },
});
