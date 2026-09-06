const config = require('./playwright.config');

module.exports = {
  ...config,
  testDir: './tests/integration',
  timeout: 45000,
  reporter: process.env.CI
    ? [['list'], ['html', { outputFolder: 'artifacts/integration-report', open: 'never' }]]
    : 'list',
  outputDir: 'artifacts/integration-results'
};
