const { chromium } = require('@playwright/test');

module.exports = {
  ci: {
    collect: {
      url: [
        'http://localhost:8888/',
        'http://localhost:8888/quietype-reading-test/'
      ],
      numberOfRuns: 2,
      chromePath: chromium.executablePath(),
      settings: {
        chromeFlags: '--headless --no-sandbox --disable-dev-shm-usage',
        preset: 'desktop'
      }
    },
    assert: {
      assertions: {
        'categories:performance': ['error', { minScore: 0.9, aggregationMethod: 'median' }],
        'categories:accessibility': ['error', { minScore: 0.95, aggregationMethod: 'median' }],
        'categories:best-practices': ['error', { minScore: 0.9, aggregationMethod: 'median' }],
        'categories:seo': ['error', { minScore: 0.9, aggregationMethod: 'median' }],
        'first-contentful-paint': ['error', { maxNumericValue: 2000, aggregationMethod: 'median' }],
        'largest-contentful-paint': ['error', { maxNumericValue: 2500, aggregationMethod: 'median' }],
        'cumulative-layout-shift': ['error', { maxNumericValue: 0.1, aggregationMethod: 'median' }],
        'total-blocking-time': ['error', { maxNumericValue: 250, aggregationMethod: 'median' }],
        'resource-summary:font:count': ['error', { maxNumericValue: 0 }],
        'resource-summary:script:size': ['error', { maxNumericValue: 40000 }],
        'resource-summary:stylesheet:size': ['error', { maxNumericValue: 80000 }]
      }
    },
    upload: {
      target: 'filesystem',
      outputDir: './artifacts/lighthouse'
    }
  }
};
