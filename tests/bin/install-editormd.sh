#!/usr/bin/env bash
set -euo pipefail

# This step runs after the plugin-free visual and performance checks.
plugin_tmp=$(mktemp -d /tmp/quietype-editormd.XXXXXX)
trap 'rm -r "$plugin_tmp"' EXIT
curl --fail --location --retry 2 --max-time 90 \
  https://downloads.wordpress.org/plugin/wp-editormd.10.2.1.zip \
  --output "$plugin_tmp/wp-editormd.zip"
printf '%s  %s\n' '717adc81761f1564d7eb6e6cc2c919f9d794da496a61476ce20ae863032003b6' "$plugin_tmp/wp-editormd.zip" | sha256sum --check
docker compose -f tests/docker-compose.yml cp "$plugin_tmp/wp-editormd.zip" wordpress:/tmp/quietype-editormd.zip
docker compose -f tests/docker-compose.yml exec -T wordpress php /var/www/html/wp-content/themes/quietype/tests/bin/seed-plugin-reading.php
