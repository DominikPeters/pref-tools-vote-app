import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, mkdir, writeFile, readFile, rm, symlink } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { spawnSync } from 'node:child_process';
import { collectAssets, uploadAssets } from '../../scripts/upload-assets.mjs';

const env = { FTP_SERVER: 'fake.invalid', FTP_USERNAME: 'private-user', FTP_PASSWORD: 'private-password', FTP_ROOTDIR: 'vote/assets/build/assets' };
const remoteRoot = '/login/vote/assets/build/assets';

async function fixture(t, files = { 'app-abcdefgh.js': 'new javascript' }) {
    const root = await mkdtemp(join(tmpdir(), 'vote-assets-'));
    t.after(() => rm(root, { recursive: true, force: true }));
    const localDir = join(root, 'assets/build/assets');
    await mkdir(localDir, { recursive: true });
    for (const [path, content] of Object.entries(files)) {
        const target = join(localDir, path);
        await mkdir(dirname(target), { recursive: true });
        await writeFile(target, content);
    }
    return { root, localDir };
}

function fakeServer({ existing = {}, fail } = {}) {
    const files = new Map(Object.entries(existing).map(([path, content]) => [path, Buffer.from(content)]));
    const calls = [];
    const clients = [];
    const createClient = async () => {
        const id = clients.length;
        const client = {
            id, closed: false, ftp: {},
            async access(options) {
                assert.deepEqual(options, { host: env.FTP_SERVER, user: env.FTP_USERNAME, password: env.FTP_PASSWORD, port: 21, secure: false });
                calls.push({ method: 'access', id });
                fail?.('access', { id, client });
            },
            async pwd() { return '/login'; },
            async ensureDir(path) {
                assert.ok(path.startsWith(remoteRoot), `unanchored directory: ${path}`);
                calls.push({ method: 'ensureDir', path, id });
                fail?.('ensureDir', { path, id, client });
            },
            async size(path) {
                calls.push({ method: 'size', path, id });
                fail?.('size', { path, id, client });
                if (!files.has(path)) throw Object.assign(new Error('Missing'), { code: 550 });
                return files.get(path).length;
            },
            async uploadFrom(localPath, path) {
                calls.push({ method: 'upload', path, id });
                files.set(path, await readFile(localPath));
                fail?.('upload', { path, id, client, files });
            },
            async rename(source, path) {
                calls.push({ method: 'rename', source, path, id });
                assert.ok(files.has(source));
                files.set(path, files.get(source));
                files.delete(source);
                fail?.('rename', { path, id, client });
            },
            close() { calls.push({ method: 'close', id }); client.closed = true; },
        };
        for (const method of ['list', 'downloadTo', 'remove', 'removeDir', 'clearWorkingDir']) {
            client[method] = () => assert.fail(`Append-only upload must not call ${method}`);
        }
        clients.push(client);
        return client;
    };
    return { files, calls, clients, createClient };
}

function options(localDir, server, logs = []) {
    return { localDir, env, createClient: server.createClient, log: message => logs.push(message), wait: async () => {} };
}

test('retains old hashes, skips matching sizes, repairs partial files by temporary upload and rename', async t => {
    const { localDir } = await fixture(t, {
        'app-abcdefgh.js': 'same',
        'js/admin-ijklmnop.js': 'complete',
        'css/style-qrstuvwx.css': 'styles',
    });
    const server = fakeServer({ existing: {
        [`${remoteRoot}/old-12345678.js`]: 'old content still in use',
        [`${remoteRoot}/app-abcdefgh.js`]: 'same',
        [`${remoteRoot}/js/admin-ijklmnop.js`]: 'par',
    } });
    const result = await uploadAssets(options(localDir, server));
    assert.deepEqual(result, { planned: 3, uploaded: 2, skipped: 1, dryRun: false });
    assert.equal(server.files.get(`${remoteRoot}/old-12345678.js`).toString(), 'old content still in use');
    assert.equal(server.files.get(`${remoteRoot}/js/admin-ijklmnop.js`).toString(), 'complete');
    assert.equal(server.files.get(`${remoteRoot}/css/style-qrstuvwx.css`).toString(), 'styles');
    const uploads = server.calls.filter(call => call.method === 'upload');
    assert.equal(uploads.length, 2);
    assert.ok(uploads.every(call => /\/\.[^/]+\.upload-[a-f0-9-]+$/.test(call.path)));
    assert.equal(server.files.size, 4, 'temporary names disappear after successful atomic rename');
    assert.ok(server.clients.every(client => client.closed));
});

test('selects only hashed files under the asset subtree and never publishes a manifest', async t => {
    const { root, localDir } = await fixture(t, {
        'app-abcdefgh.js': 'application',
        'manifest.json': 'must stay private until PHP upload',
        '.vite/manifest-abcdefgh.json': 'not an asset',
        'unversioned.js': 'not immutable',
    });
    await mkdir(join(root, 'assets/build/.vite'), { recursive: true });
    await writeFile(join(root, 'assets/build/.vite/manifest.json'), 'new manifest');
    const server = fakeServer();
    await uploadAssets(options(localDir, server));
    assert.deepEqual([...server.files.keys()], [`${remoteRoot}/app-abcdefgh.js`]);
});

test('rejects symlinks instead of reading a file outside the build', async t => {
    const { root, localDir } = await fixture(t);
    const outside = join(root, 'outside.txt');
    await writeFile(outside, 'private fixture');
    await symlink(outside, join(localDir, 'secret-12345678.js'));
    await assert.rejects(collectAssets(localDir), /symlinks/);
});

test('reconnects with a fresh client after a data-socket disconnect and overwrites only its own temporary file', async t => {
    const { localDir } = await fixture(t);
    const logs = [];
    const server = fakeServer({ fail(method, { id, client, path, files }) {
        if (method === 'upload' && id === 0) {
            files.set(path, Buffer.from('partial'));
            client.closed = true;
            throw Object.assign(new Error(`Server echoed ${env.FTP_PASSWORD}`), { code: 'ECONNRESET' });
        }
    } });
    assert.equal((await uploadAssets(options(localDir, server, logs))).uploaded, 1);
    assert.equal(server.clients.length, 2);
    const uploads = server.calls.filter(call => call.method === 'upload');
    assert.equal(uploads[0].path, uploads[1].path);
    assert.notEqual(uploads[0].id, uploads[1].id);
    assert.equal(server.files.get(`${remoteRoot}/app-abcdefgh.js`).toString(), 'new javascript');
    assert.ok(!logs.join('\n').includes(env.FTP_PASSWORD));
});

test('a lost rename acknowledgment is recovered by SIZE without uploading the published hash again', async t => {
    const { localDir } = await fixture(t);
    const server = fakeServer({ fail(method, { id, client }) {
        if (method === 'rename' && id === 0) {
            client.closed = true;
            throw Object.assign(new Error('Lost acknowledgment'), { code: 'ECONNRESET' });
        }
    } });
    const result = await uploadAssets(options(localDir, server));
    assert.equal(result.skipped, 1);
    assert.equal(server.calls.filter(call => call.method === 'upload').length, 1);
    assert.equal(server.files.get(`${remoteRoot}/app-abcdefgh.js`).toString(), 'new javascript');
});

test('transient failures stop after three attempts and never expose raw FTP messages or configuration', async t => {
    const { localDir } = await fixture(t);
    const logs = [];
    const server = fakeServer({ fail(method) {
        if (method === 'ensureDir') throw Object.assign(new Error(Object.values(env).join(' ')), { code: 425 });
    } });
    await assert.rejects(uploadAssets(options(localDir, server, logs)), error => {
        assert.match(error.message, /FTP 425; attempt 3\/3/);
        for (const value of Object.values(env)) assert.ok(!error.message.includes(value));
        return true;
    });
    assert.equal(server.clients.length, 3);
    for (const value of Object.values(env)) assert.ok(!logs.join('\n').includes(value));
});

test('permanent authentication errors fail immediately without logging credentials', async t => {
    const { localDir } = await fixture(t);
    const server = fakeServer({ fail(method) {
        if (method === 'access') throw Object.assign(new Error(env.FTP_PASSWORD), { code: 530 });
    } });
    await assert.rejects(uploadAssets(options(localDir, server)), /FTP 530; attempt 1\/3/);
    assert.equal(server.clients.length, 1);
});

test('dry run requires neither credentials nor a client and does not read the environment', async t => {
    const { localDir } = await fixture(t);
    const logs = [];
    const result = await uploadAssets({
        localDir, dryRun: true,
        env: new Proxy({}, { get() { assert.fail('Dry run read deployment credentials'); } }),
        createClient() { assert.fail('Dry run created an FTP client'); },
        log: message => logs.push(message),
    });
    assert.deepEqual(result, { planned: 1, uploaded: 0, skipped: 0, dryRun: true });
    assert.match(logs.join('\n'), /Would upload app-abcdefgh\.js/);
});

test('CLI supports dry_run workflow input with no FTP configuration and never prints ambient secrets', async t => {
    const { root } = await fixture(t);
    const script = new URL('../../scripts/upload-assets.mjs', import.meta.url);
    const result = spawnSync(process.execPath, [script.pathname], {
        cwd: root, encoding: 'utf8', env: { DRY_RUN: 'true', UNRELATED_PRIVATE_VALUE: 'do-not-print-this' },
    });
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.stdout, /no FTP connection made/);
    assert.doesNotMatch(result.stdout + result.stderr, /do-not-print-this/);
});
