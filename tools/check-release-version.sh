#!/usr/bin/env bash
set -euo pipefail

expected="${1#v}"
style_version="$(sed -n 's/^Version:[[:space:]]*//p' style.css | head -1)"
php_version="$(sed -n "s/^define( 'QUIETYPE_VERSION', '\([^']*\)' );/\1/p" functions.php | head -1)"
package_version="$(node -p "require('./package.json').version")"

if [[ -z "${expected}" || "${style_version}" != "${expected}" || "${php_version}" != "${expected}" || "${package_version}" != "${expected}" ]]; then
  printf 'Version mismatch: tag=%s style.css=%s functions.php=%s package.json=%s\n' "${expected}" "${style_version}" "${php_version}" "${package_version}" >&2
  exit 1
fi

printf 'Quietype release version %s is consistent.\n' "${expected}"
