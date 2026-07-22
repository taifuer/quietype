#!/usr/bin/env bash
set -euo pipefail

compose=(docker compose -f tests/docker-compose.yml)
test_port=${QUIETYPE_TEST_PORT:-8888}

case "${1:-}" in
  start)
    "${compose[@]}" up -d
    for attempt in $(seq 1 60); do
      if curl --fail --silent "http://localhost:${test_port}/wp-admin/install.php" >/dev/null; then
        printf 'Quietype test WordPress is ready on port %s.\n' "$test_port"
        exit 0
      fi
      sleep 2
    done
    "${compose[@]}" logs
    printf 'Timed out waiting for the Quietype test environment.\n' >&2
    exit 1
    ;;
  seed)
    "${compose[@]}" exec -T wordpress php /var/www/html/wp-content/themes/quietype/tests/bin/seed-wordpress.php
    ;;
  stop)
    "${compose[@]}" down --volumes --remove-orphans
    ;;
  *)
    printf 'Usage: %s {start|seed|stop}\n' "$0" >&2
    exit 2
    ;;
esac
