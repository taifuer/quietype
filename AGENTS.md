# Repository Guidelines

## Project Structure & Module Organization

Quietype is a classic WordPress theme. Root-level PHP files implement the WordPress template hierarchy: `index.php`, `single.php`, `page.php`, `archive.php`, `search.php`, and `404.php`. Reusable list markup lives in `template-parts/`; page-specific templates use the `template-*.php` naming pattern. Theme setup, compatibility helpers, icons, and Customizer settings belong in `functions.php`.

Presentation is primarily in `style.css`, with editor defaults in `theme.json`. Browser behavior lives in `assets/js/`. Vendored libraries must remain under `assets/vendor/<library>/` with their upstream license. `screenshot.png` is the WordPress theme preview.

## Build, Test, and Development Commands

There is no asset compilation step. Develop against a local WordPress installation with this repository mounted or cloned as `wp-content/themes/quietype`.

```bash
php -l functions.php
find . -maxdepth 2 -name '*.php' -exec php -l {} \;
npm run test:js
git diff --check
```

These commands check PHP syntax, JavaScript syntax, and whitespace errors. For the isolated WordPress regression suite, run `npm ci`, `npm run env:start`, `npm run env:seed`, `npm run test:e2e`, and `npm run test:performance`; always finish with `npm run env:stop`. Before release, package from a clean tagged commit:

```bash
git archive --format=zip --prefix=quietype/ -o quietype.zip <tag>
```

## Coding Style & Naming Conventions

Follow WordPress PHP conventions: tabs for indentation, escaped output, and snake_case functions prefixed with `quietype_`. Use semantic HTML and preserve keyboard-visible focus. JavaScript uses two-space indentation, `const`/`let`, and small DOM-focused helpers. CSS selectors use descriptive kebab-case and existing component prefixes such as `.article-`, `.post-row__`, and `.reading-tools__`.

## Testing Guidelines

Playwright covers home, article, archive, search, links, about, and 404 views in desktop and mobile Chromium. It also runs axe checks, structural HTML rules, interaction assertions, and committed screenshots under `tests/e2e/visual.spec.js-snapshots/`. Update snapshots only after visually reviewing the intended change. Lighthouse budgets cover the home and representative article. Continue manual checks at 360px, 768px, and 1280px, plus formulas, historical Markdown, keyboard navigation, and print output.

## Commit & Pull Request Guidelines

Use concise, meaningful commit subjects and list key changes in the body. Commits must use `taifu <taifu@taifua.com>` as author. Agent-assisted commits must include:

`Co-Authored-By: Codex (GPT-5.6 Sol) <noreply@openai.com>`

Pull requests should explain user-visible changes, note manual checks, link related issues, and include desktop/mobile screenshots for layout changes.
