# Security Policy

## Supported versions

Security fixes are provided for the latest published Quietype release. Update WordPress, PHP, Quietype, and any installed plugins before reporting a problem that has already been fixed upstream.

## Reporting a vulnerability

Please use GitHub's private security advisory feature for the Quietype repository. Do not open a public issue for authentication bypasses, cross-site scripting, request forgery, secret exposure, or other vulnerabilities that could put deployed sites at risk.

Include the affected version, WordPress and PHP versions, reproduction steps, expected impact, and any proposed mitigation. Remove passwords, login-entry values, SMTP credentials, private URLs, personal data, and production database excerpts from the report.

## Deployment notes

- The custom login entrance reduces automated scans but is not a replacement for strong unique passwords, multifactor authentication, server-side rate limiting, backups, and timely updates.
- Prefer the `QUIETYPE_SMTP_PASSWORD` constant in `wp-config.php` over storing an SMTP password in the database. Keep configuration files and backups outside the public web root.
- Custom Head and Footer code is intentionally executable and must only be available to trusted administrators.
- Enabling avatar mirrors, link checks, remote images, or custom analytics creates outbound requests. Review each provider and document it in the site's privacy policy.
