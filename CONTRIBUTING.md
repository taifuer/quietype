# Contributing to Quietype

Quietype is a Chinese-first, integrated WordPress theme. Contributions should preserve its quiet reading experience, avoid unnecessary dependencies, and keep optional network features disabled until an administrator enables them.

## Development setup

Use the disposable WordPress environment when possible:

```bash
npm ci
npm run env:start
npm run env:seed
npm run test:e2e
npm run test:performance
npm run env:stop
```

PHP and JavaScript changes should also pass:

```bash
find . -name '*.php' -not -path './node_modules/*' -print0 | xargs -0 -n1 php -l
npm run test:js
git diff --check
```

## Change guidelines

- Escape rendered values and sanitize every saved setting or metadata field.
- Use the `quietype_` prefix for PHP functions, options, hooks, and post metadata.
- Keep site identities, domains, contact details, filing numbers, and third-party endpoints configurable rather than hardcoded.
- Update Playwright assertions for behavior changes and review visual snapshots at desktop and mobile sizes before committing them.
- Include third-party code only when it can be distributed under a GPL-compatible license, and retain its license file.

Pull requests should describe user-visible behavior, migration considerations, tests performed, and any new outbound requests or stored data. Include screenshots for layout changes and link related issues where applicable.
