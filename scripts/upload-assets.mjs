import { readdir, stat } from 'node:fs/promises';
import { resolve, join, posix } from 'node:path';
import { pathToFileURL } from 'node:url';
import { randomUUID } from 'node:crypto';

const MAX_ATTEMPTS = 3;
const TRANSIENT_CODES = new Set([
    'ECONNRESET', 'ECONNREFUSED', 'ECONNABORTED', 'ETIMEDOUT', 'EPIPE',
    'ENETUNREACH', 'EHOSTUNREACH', 'EAI_AGAIN', 'ERR_SOCKET_CLOSED',
    'ERR_STREAM_PREMATURE_CLOSE', 'UPLOAD_SIZE_MISMATCH',
]);
// Vite outputs and generated screenshots contain a content hash before the extension.
const VERSIONED_FILE = /-[A-Za-z0-9_-]{8,}\.[A-Za-z0-9.]+$/;

/** Only hashed assets, never the sibling .vite manifest or other deployment files. */
export async function collectAssets(localDir) {
    const assets = [];
    async function walk(directory, relative = '') {
        const entries = await readdir(directory, { withFileTypes: true });
        entries.sort((a, b) => a.name.localeCompare(b.name, 'en'));
        for (const entry of entries) {
            if (entry.name.startsWith('.')) continue;
            if (!/^[A-Za-z0-9_.-]+$/.test(entry.name)) throw new Error('Unexpected asset filename.');
            if (entry.isSymbolicLink()) throw new Error('Asset symlinks are not supported.');
            const localPath = join(directory, entry.name);
            const remotePath = posix.join(relative, entry.name);
            if (entry.isDirectory()) await walk(localPath, remotePath);
            else if (entry.isFile() && VERSIONED_FILE.test(entry.name)) {
                assets.push({ localPath, remotePath, size: (await stat(localPath)).size });
            }
        }
    }
    await walk(resolve(localDir));
    if (assets.length === 0) throw new Error('No versioned assets found. Build the frontend first.');
    return assets;
}

function configuration(env) {
    const required = ['FTP_SERVER', 'FTP_USERNAME', 'FTP_PASSWORD', 'FTP_ROOTDIR'];
    const missing = required.filter(key => typeof env[key] !== 'string' || env[key].length === 0);
    if (missing.length) throw new Error(`Missing deployment configuration: ${missing.join(', ')}.`);
    const root = env.FTP_ROOTDIR;
    // The remote asset directory is relative to the FTP user's login directory.
    if (root.startsWith('/') || root.includes('\\') || /[\x00-\x1f\x7f]/.test(root)
        || root.split('/').some(segment => segment === '..') || posix.normalize(root) === '.') {
        throw new Error('FTP_ROOTDIR must name a relative asset directory without parent traversal.');
    }
    return { root, access: { host: env.FTP_SERVER, user: env.FTP_USERNAME, password: env.FTP_PASSWORD, port: 21, secure: false } };
}

function transient(error, client) {
    if (typeof error?.code === 'number') return error.code >= 400 && error.code < 500;
    if (TRANSIENT_CODES.has(error?.code)) return true;
    // basic-ftp closes connections on socket errors/timeouts, some without a code.
    return !error?.code && client?.closed === true;
}

function safeReason(error) {
    if (typeof error?.code === 'number' && error.code >= 100 && error.code <= 599) return `FTP ${error.code}`;
    if (TRANSIENT_CODES.has(error?.code)) return error.code;
    return 'transfer failed';
}

async function remoteSize(client, path) {
    try {
        return await client.size(path);
    } catch (error) {
        if (error?.code === 550) return null; // A missing file is normal on the first deployment.
        throw error;
    }
}

/** Append versioned assets; never list/download state, delete files, or upload a manifest. */
export async function uploadAssets({
    localDir = resolve('assets/build/assets'),
    dryRun = false,
    env = process.env,
    createClient = async () => new (await import('basic-ftp')).Client(30_000),
    log = console.log,
    wait = milliseconds => new Promise(resolveWait => setTimeout(resolveWait, milliseconds)),
} = {}) {
    const assets = await collectAssets(localDir);
    const summary = { planned: assets.length, uploaded: 0, skipped: 0, dryRun };
    if (dryRun) {
        for (const asset of assets) log(`Would upload ${asset.remotePath} (${asset.size} bytes)`);
        log(`Dry run: ${assets.length} versioned assets; no FTP connection made.`);
        return summary;
    }
    const config = configuration(env);
    let client;
    let root;
    const ensuredDirectories = new Set();
    const disconnect = () => {
        client?.close();
        client = undefined;
        root = undefined;
        ensuredDirectories.clear();
    };
    try {
        for (const asset of assets) {
            // Reuse this unique temporary name if a retry must overwrite a partial upload.
            const temporaryName = `.${posix.basename(asset.remotePath)}.upload-${randomUUID()}`;
            for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
                try {
                    if (!client) {
                        client = await createClient();
                        client.ftp.verbose = false;
                        // Disable library logging as well as verbosity; server replies may contain secrets.
                        client.ftp.log = () => {};
                        await client.access(config.access);
                        const initialDirectory = await client.pwd();
                        if (!posix.isAbsolute(initialDirectory)) throw new Error('Expected an absolute FTP login directory.');
                        root = posix.join(initialDirectory, config.root);
                        await client.ensureDir(root);
                        ensuredDirectories.add(root);
                    }
                    const destination = posix.join(root, asset.remotePath);
                    if (await remoteSize(client, destination) === asset.size) {
                        summary.skipped++;
                        log(`Already present ${asset.remotePath}`);
                        break;
                    }
                    const directory = posix.dirname(destination);
                    if (!ensuredDirectories.has(directory)) {
                        await client.ensureDir(directory);
                        ensuredDirectories.add(directory);
                    }
                    const temporaryPath = posix.join(directory, temporaryName);
                    await client.uploadFrom(asset.localPath, temporaryPath);
                    if (await remoteSize(client, temporaryPath) !== asset.size) {
                        const error = new Error('Uploaded size does not match.');
                        error.code = 'UPLOAD_SIZE_MISMATCH';
                        throw error;
                    }
                    await client.rename(temporaryPath, destination);
                    summary.uploaded++;
                    log(`Uploaded ${asset.remotePath} (${asset.size} bytes)`);
                    break;
                } catch (error) {
                    const retry = transient(error, client) && attempt < MAX_ATTEMPTS;
                    const reason = safeReason(error);
                    disconnect();
                    if (!retry) throw new Error(`Asset upload stopped at ${asset.remotePath} (${reason}; attempt ${attempt}/${MAX_ATTEMPTS}).`);
                    log(`Retrying ${asset.remotePath} after ${reason} (attempt ${attempt + 1}/${MAX_ATTEMPTS}).`);
                    await wait(250 * 2 ** (attempt - 1));
                }
            }
        }
    } finally {
        disconnect();
    }
    log(`Assets ready: ${summary.uploaded} uploaded, ${summary.skipped} already present.`);
    return summary;
}

export async function main(args = process.argv.slice(2)) {
    if (args.some(argument => !['--dry-run', '--screenshots'].includes(argument))) {
        throw new Error('Usage: node scripts/upload-assets.mjs [--dry-run] [--screenshots]');
    }
    return uploadAssets({
        localDir: resolve(args.includes('--screenshots') ? 'screenshots/localized' : 'assets/build/assets'),
        dryRun: args.includes('--dry-run') || process.env.DRY_RUN === 'true',
    });
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    main().catch(error => {
        // Never print a raw FTP error, stack, or environment object.
        console.error(error.message);
        process.exitCode = 1;
    });
}
