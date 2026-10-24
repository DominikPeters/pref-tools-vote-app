/** Targeted results refresh; see scripts/SCREENSHOTS.md for full generation. */
const { capture, cliOptions } = require('./screenshot-capture');
if (require.main === module) {
  capture({ ...cliOptions(process.argv.slice(2)), kind: 'results' }).catch(error => { console.error(error); process.exitCode = 1; });
}
