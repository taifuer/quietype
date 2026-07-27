# Changelog

All notable changes to Quietype will be documented in this file.

## [Unreleased]

### Added

- Added unobtrusive, copy-aware permalinks to rendered H2 and H3 article headings.
- Added a compact annual reading archive with administrator-defined categories and tags, short notes, whole-star ratings, covers, and direct Douban links.
- Added administrator-only Douban metadata previews with a separate manual confirmation step and validated cover imports.
- Added an optional navigation row between page content and the legal footer.

### Changed

- Polished code-block keyboard focus and horizontal scroll affordances without changing the established light syntax palette.
- Normalized the reading editor field widths and kept the footer focused on copyright, contact, and filing information.
- Versioned local assets from their modification times so iterative deployments cannot leave month-long stale CSS or JavaScript caches.
- Reduced reading records to month precision, added plain-text read states, renamed personal ratings, and removed visible local book permalinks.
- Made book covers fall back to generated text artwork when lookup, import, or front-end image loading fails.
- Moved the public bookshelf to `/books/` with permanent redirects from former `/reading/` routes.
- Preserved server-side cover imports when Douban prevents the browser from displaying its preview image.
- Made book notes visible by default, replaced the publishing-date column with compact reading metadata, and renamed the archive eyebrow to `BOOKS`.
- Extended revision disabling, counting, and explicit cleanup to book records while preserving autosaves.
- Consolidated each reading card's month, status, personal stars, and Douban score above the closing note while leaving only the title as its external link.
- Added optional HTTPS book-cover URLs, including `pic.taifua.com`, with priority over featured images and the existing text fallback.
- Kept Douban scores adjacent to each personal reading record instead of isolating them at the card edge.
- Normalized reading dates, states, and personal stars at 12px while tightening the vertical rhythm between book-card metadata and notes.
- Balanced incomplete mobile year-index rows with cell-owned borders and tightened the separators inside compact reading records.
- Purged the cached public bookshelf after book saves, status changes, and permanent deletions when a loopback server endpoint is configured.
- Served Media Library book covers through a dedicated 252 × 372 derivative with responsive display hints, preserving the original uploads.
- Kept the newest photo year open while collapsing older years, and deferred archived image requests until their sections approach the viewport.
- Reduced Xiaomi, Redmi, and POCO camera metadata to the shared Xiaomi brand label on the public photo archive.
- Decoupled PhotoSwipe slides from partially loaded native-lazy thumbnails so cached Edge sessions and adjacent navigation load reliably.
- Made browser Back close an open lightbox before leaving the current page, including mobile return gestures.
- Removed native lazy-loading markers from JavaScript-deferred archive images, reduced adjacent PhotoSwipe preloading, and retried one failed CDN request without cache.
- Restored compact touch-friendly previous and next controls at the middle edges of mobile photos, following the existing UI visibility state.
- Centered PhotoSwipe's native mobile arrow artwork symmetrically and restored its shared tap-to-hide transition with the other controls.

## [0.9.0] - 2026-07-22

### Added

- Added a disposable Docker-based WordPress fixture with deterministic long-form test content.
- Added Playwright interaction, console, markup, axe accessibility, and desktop/mobile visual regression coverage.
- Added Lighthouse CI budgets and a GitHub Actions quality gate across PHP 8.0, 8.2, and 8.4.

## [0.8.3] - 2026-07-22

### Changed

- Reworked Quietype administrative emails into a compact HTML template with structured details, action buttons, and a clear no-reply notice.
- Moved the mobile menu control to the outer edge with search immediately to its left.
- Matched the left-aligned 760px article layout to the breakpoint where the fixed table of contents is actually visible.
- Added soft break opportunities inside long URL identifiers so links use the remaining line before wrapping.

## [0.8.2] - 2026-07-21

### Added

- Added a theme-owned AJAX view counter that updates article totals independently of full-page caching.
- Added six-hour browser and server-side deduplication with common crawler exclusion.

### Changed

- Continued using the existing `views` post metadata while preventing duplicate increments if WP-PostViews is reactivated.

## [0.8.1] - 2026-07-20

### Changed

- Reworded the 404 page in a clearer, quieter voice and widened its desktop guidance line.

### Fixed

- Removed the disabled single-author archive provider from WordPress's XML sitemap index.

## [0.8.0] - 2026-07-20

### Changed

- Consolidated the historical CSS patch layers into a single component-oriented stylesheet without changing the established design.
- Raised meaningful secondary text to the accessible muted color while keeping decorative separators visually quiet.

### Fixed

- Offset article heading anchors below the sticky header on desktop and mobile.

## [0.7.9] - 2026-07-20

### Fixed

- Let each friend-link card draw its own border so an incomplete row keeps the page background instead of exposing the grid-line color.
- Removed the redundant category underline and separated category headings from cards with restrained whitespace.

## [0.7.8] - 2026-07-20

### Added

- Rendered friend links from administrator-created WordPress link categories with optional numeric display order.
- Added link-category order fields and an overview column to WordPress's native taxonomy screen.

### Changed

- Simplified the collapsed mobile article TOC label and close it after a section is selected.

## [0.7.7] - 2026-07-20

### Fixed

- Kept default-enabled checkboxes visibly checked when WordPress optimizes away an option equal to its registered default.

## [0.7.6] - 2026-07-20

### Added

- Added manual normal, pending, and offline states to WordPress's native link editor and overview table.
- Added SSRF-safe, batched daily availability checks that require three consecutive failures before suggesting review.
- Added a restrained, manually confirmed offline badge to friend-link cards.
- Restored WordPress's native Links screen for administrators while Quietype is active without permanently changing role data.

## [0.7.5] - 2026-07-20

### Added

- Added a configurable site start year for the footer copyright range.
- Accepted bare domains in the optional comment website field and normalized them to HTTPS.

### Fixed

- Removed the redundant search-icon tooltip and corrected tag archive eyebrows to `TAG`.
- Aligned the desktop TOC with the article title and bounded long lists with independent scrolling.
- Replaced hard-coded masthead and footer names with the WordPress site title.

## [0.7.4] - 2026-07-20

### Fixed

- Removed WP Editor.md's orphan KaTeX, Mermaid, and MindMap footer initializers on pages that do not load their matching libraries.
- Added semantic path and hostname break opportunities to visibly printed URLs without splitting ordinary linked labels.

## [0.7.3] - 2026-07-20

### Fixed

- Renamed the taxonomy eyebrow from `COLLECTION` to `CATALOGUE`.
- Added unobtrusive semantic break opportunities to mixed Chinese/Latin prose without applying indiscriminate `break-all` to every English word.

## [0.7.2] - 2026-07-19

### Fixed

- Added semantic soft-break opportunities to long inline-code identifiers so they use the current line instead of moving as a whole.
- Removed duplicated paragraph margins from single-paragraph list items emitted by Markdown renderers.

## [0.7.1] - 2026-07-19

### Added

- Added configurable footer ICP text and an optional article-end CC BY-NC-SA 4.0 attribution panel.
- Added front-end admin-bar and post/page revision controls, plus an explicit existing-revision cleanup tool.
- Added a one-day, credential-safe record of the latest SMTP transport error to the theme settings screen.

### Fixed

- Made SMTP authentication failures actionable instead of returning only a generic test-mail failure.

## [0.7.0] - 2026-07-19

### Added

- Added content-aware loading for WP Editor.md, Prism, KaTeX, PhotoSwipe, Mermaid, MindMap, Plyr, and their jQuery dependency.
- Added native image lazy loading, asynchronous remote dimension discovery, and editor warnings for missing or generic alternative text.
- Added a configurable default social image and article-first-image fallback for Open Graph and structured data.

### Security

- Replaced replayable comment challenges with ten-minute one-time transients, a honeypot, and short submission throttling.
- Exchanged the private login query for a signed, HttpOnly, SameSite cookie and removed it from subsequent URLs and forms.
- Removed public REST user routes and single-author archives, the login language selector, generator metadata, and unnecessary editor cookies.

### Changed

- Raised meaningful small metadata from the faint decorative color to the accessible muted text color.

## [0.6.0] - 2026-07-19

### Added

- Added conflict-aware description, keyword, Open Graph, social, and Schema.org metadata with per-entry overrides.
- Added optional SMTP transport, a protected test-mail action, and rate-limited administrator login and comment notifications.

### Changed

- Consolidated all Quietype-owned preferences into one navigable Appearance settings screen and migrated legacy Customizer values.
- Unified static, archive, taxonomy, search, and links page-hero width and vertical rhythm across desktop and mobile.

## [0.5.3] - 2026-07-19

### Added

- Added a dedicated Appearance settings screen with WordPress's code editor for trusted Head and Footer markup.
- Added an editable Gravatar base URL with a mainland-friendly default and an opt-out path.

### Fixed

- Corrected the comment verification instruction and restored a single, consistent Chinese masthead style.
- Escaped document-boundary examples so the Head and Footer setting descriptions render in full.

## [0.5.2] - 2026-07-19

### Added

- Added trusted-administrator Head and Footer code slots for analytics, verification markup, small styles, and deferred scripts.
- Added a required four-digit challenge to the public comment form without affecting signed-in users.

### Changed

- Limited front-end search results to posts, grouped mobile article dates and reading statistics into two clean lines, and refined the text masthead.

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
