# Changelog

All notable changes to Quietype will be documented in this file.

## [Unreleased]

## [0.5.1] - 2026-07-19

### Fixed

- Contained mobile article layout, reading tools, inline code, links, and rendered formulas without disabling pinch zoom.
- Removed literal taxonomy markup from tag titles and restored article metadata separators on narrow screens.
- Released sticky touch focus from mobile icon controls and hid the unused login language selector.

### Changed

- Replaced decorative links/archive copy with useful site totals and tightened home, term, footer, and mobile spacing.
- Refined the local-first Chinese, Latin, and code font stacks plus restrained editorial category and tag marks.

## [0.5.0] - 2026-07-19

### Added

- Added a Quietype-styled WordPress authentication surface for login, lost-password, reset-password, and related core states.
- Added a responsive 420px login card with accessible focus states and a site-linked wordmark.
- Added configurable query-parameter login gating, unauthenticated wp-admin shielding, a one-time arithmetic challenge, and optional XML-RPC authentication protection without external dependencies.

### Security

- Preserved password-reset, logout, post-password, and signed WordPress recovery flows while returning 404 for unrecognized login requests.

## [0.4.1] - 2026-07-19

### Fixed

- Prevented duplicate Prism language and copy controls while keeping the theme's local Chinese copy fallback.
- Stacked mobile footer contacts above copyright and removed wrapping view counts from mobile post lists.
- Routed WordPress avatars through a configurable mainland-friendly Gravatar endpoint.

### Changed

- Refined the light syntax palette using restrained One Light and GHColors-inspired token roles.

## [0.4.0] - 2026-07-19

### Added

- Initial classic WordPress theme structure.
- Reading-first home, article, archive, links, search, comments, and 404 templates.
- Automatic H2/H3 table of contents and article reading progress.
- Three light reading backgrounds with local preference persistence.
- Responsive mobile header, navigation drawer, and back-to-top action.
- Markdown typography for code, tables, images, blockquotes, lists, footnotes, and KaTeX.
- WP Editor.md and Prism compatibility handling.

### Changed

- Raised small interface text to a clearer 13px minimum and refined article spacing.
- Simplified the text wordmark, article metadata, footer, pagination, and TOC progress treatment.
- Added archive tag counts and a left-language/right-copy code toolbar layout.
- Reduced KaTeX display sizing and aligned formula spacing with the reading rhythm.
- Reworked the Chinese wordmark, ICP footer, icon-only reading tools, post view metadata, category treatment, and comments.
- Unified the search and background panels, fixed background switching, and grouped the page navigation controls.
- Made category selection hierarchy-aware, removed home reading-time estimates, and moved Prism presentation fully into the theme.
- Tightened page and heading rhythm, refined category metadata, corrected Prism line-number spacing, and hardened the mobile drawer.
- Added an accessible, dependency-free image lightbox for article images.
- Aligned the header and footer to the content axis, unified reading-background colors, and added collapsible mobile submenus.
- Narrowed the main layout to 960px, moved home metadata below excerpts, restored concise mobile excerpts, and normalized reading-tool and term styling.
- Balanced the desktop article and TOC as one centered rail, compacted home metadata, equalized archive tag cells, fixed the top control, and adopted PhotoSwipe 5.4.4 for image zooming.
- Added configurable footer contacts, aligned TOC articles to the 960px site grid, refined threaded comments, contained mobile code, restored category rails, and styled nested Markdown lists.
- Centered footer contacts, corrected the GitHub icon, fixed short-page reading controls, aligned standard pages, and normalized the links-page hero rhythm.
- Removed empty mobile action tooltips, cleared pointer focus after closing navigation, and reduced the mobile drawer to a compact half-screen rail.
- Removed WordPress taxonomy prefixes, added linked parent-category context, and finalized the Quietype theme identity and preview artwork.
