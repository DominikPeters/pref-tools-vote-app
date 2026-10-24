/** See scripts/SCREENSHOTS.md. Uses a disposable app, never a shared poll database. */
const { capture, cliOptions } = require('./screenshot-capture');
if (require.main === module) {
  capture(cliOptions(process.argv.slice(2))).catch(error => { console.error(error); process.exitCode = 1; });
}
