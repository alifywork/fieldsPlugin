#!/usr/bin/env bash
# These checks must fail before cloning files or connecting to any database.
set -euo pipefail
RUNNER="$(cd "$(dirname "$0")" && pwd)/run.sh"
refuses() {
  local expected="$1" output; shift
  if output=$(env -u ALIFY_ALLOW_MYSQL_TESTS -u ALIFY_MYSQL_DATABASE -u ALIFY_MYSQL_HOST -u ALIFY_MYSQL_USER -u ALIFY_MYSQL_PASSWORD "$@" bash "$RUNNER" 2>&1); then
    echo "Guard unexpectedly accepted: $expected"; exit 1
  fi
  [[ "$output" == *"$expected"* ]] || { echo "Unexpected refusal: $output"; exit 1; }
}
refuses 'DB_ENGINE must be' DB_ENGINE=unsupported
refuses 'ALIFY_ALLOW_MYSQL_TESTS=1' DB_ENGINE=mysql
refuses 'alify_test-prefixed' DB_ENGINE=mysql ALIFY_ALLOW_MYSQL_TESTS=1 ALIFY_MYSQL_DATABASE=production
refuses 'alify_test-prefixed' DB_ENGINE=mysql ALIFY_ALLOW_MYSQL_TESTS=1 ALIFY_MYSQL_DATABASE='alify_test;DROP DATABASE'
for host in db.example.com 127.0.0.1.evil localhost:/tmp/mysql.sock; do
  refuses 'loopback host' DB_ENGINE=mysql ALIFY_ALLOW_MYSQL_TESTS=1 ALIFY_MYSQL_DATABASE=alify_test ALIFY_MYSQL_HOST="$host"
done
refuses 'ALIFY_MYSQL_USER' DB_ENGINE=mysql ALIFY_ALLOW_MYSQL_TESTS=1 ALIFY_MYSQL_DATABASE=alify_test ALIFY_MYSQL_HOST=127.0.0.1
printf 'PASS: 8 database-runner safety guards\n'
