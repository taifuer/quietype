const { test, expect } = require('@playwright/test');
const { HtmlValidate } = require('html-validate');

const validator = new HtmlValidate({
  rules: {
    'close-order': 'error',
    'element-permitted-content': 'error',
    'element-permitted-parent': 'error',
    'no-dup-id': 'error',
    'no-implicit-close': 'error',
    'no-missing-references': 'error',
    'void-content': 'error'
  }
});

for (const path of ['/', '/quietype-reading-test/', '/books/', '/photos/', '/archive/', '/about/', '/quietype-missing-page/']) {
  test(`${path} emits structurally valid HTML`, async ({ request }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-chromium', 'Markup is viewport-independent.');
    const response = await request.get(path);
    const report = await validator.validateString(await response.text());
    const errors = report.results.flatMap((result) => result.messages)
      .filter((message) => message.severity === 2)
      .map((message) => `${message.line}:${message.column} ${message.ruleId}: ${message.message}`);
    expect(errors).toEqual([]);
  });
}
