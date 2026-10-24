import { readdirSync } from 'node:fs';
import { build, defineConfig } from 'vite';

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
        async buildStart() {
            // External sites use a classic script tag. Keep the self-contained
            // web component classic, while giving it a content-hashed URL too.
            const path = 'assets/js/embed/vote-poll-component.js';
            const result = await build({
                configFile: false, publicDir: false, logLevel: 'silent',
                build: {
                    write: false, modulePreload: false,
                    lib: { entry: path, formats: ['iife'], name: 'PrefToolsVote' },
                },
            });
            const bundle = Array.isArray(result) ? result[0] : result;
            const chunk = bundle.output.find(file => file.type === 'chunk' && file.isEntry);
            for (const dependency of Object.keys(chunk.modules)) this.addWatchFile(dependency);
            this.emitFile({ type: 'asset', name: 'vote-poll.js', originalFileName: 'assets/js/embed/vote-poll.js', source: chunk.code });
        },
    }],
    build: {
        outDir: 'assets/build',
        manifest: true,
        modulePreload: false,
    },
});
