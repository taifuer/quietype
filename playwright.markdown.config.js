const config = require('./playwright.config');

module.exports = {
  ...config,
  testDir: './tests/markdown',
  timeout: 45000,
  reporter: 'list',
  outputDir: 'artifacts/markdown-results'
};
