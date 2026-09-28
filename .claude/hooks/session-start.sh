#!/bin/bash
# Prepares a Claude Code on the web session: MariaDB with the dev and test databases,
# Composer packages and a local .env. Safe to run more than once.
set -euo pipefail

[ "${CLAUDE_CODE_REMOTE:-}" = "true" ] || exit 0
cd "$CLAUDE_PROJECT_DIR"

if ! command -v mariadbd >/dev/null 2>&1; then
    apt-get install -y -qq mariadb-server >/dev/null 2>&1 || { apt-get update -qq >/dev/null 2>&1 || true; apt-get install -y -qq mariadb-server >/dev/null; }
fi
if ! mariadb -uroot -e 'select 1' >/dev/null 2>&1; then
    mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld
    (nohup mysqld_safe >/dev/null 2>&1 &)
    for _ in $(seq 1 30); do mariadb -uroot -e 'select 1' >/dev/null 2>&1 && break; sleep 1; done
fi
mariadb -uroot <<'SQL'
CREATE DATABASE IF NOT EXISTS budgeteer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS budgeteer_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS budgeteer_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'budgeteer'@'localhost' IDENTIFIED BY 'budgeteer';
CREATE USER IF NOT EXISTS 'budgeteer'@'127.0.0.1' IDENTIFIED BY 'budgeteer';
GRANT ALL ON budgeteer.* TO 'budgeteer'@'localhost';
GRANT ALL ON budgeteer_test.* TO 'budgeteer'@'localhost';
GRANT ALL ON budgeteer_e2e.* TO 'budgeteer'@'localhost';
GRANT ALL ON budgeteer.* TO 'budgeteer'@'127.0.0.1';
GRANT ALL ON budgeteer_test.* TO 'budgeteer'@'127.0.0.1';
GRANT ALL ON budgeteer_e2e.* TO 'budgeteer'@'127.0.0.1';
SQL

# GitHub's zip downloads are not reachable from every sandbox; git clones are.
export COMPOSER_NO_INTERACTION=1
composer install --quiet 2>/dev/null || composer install --quiet --prefer-source

if [ ! -f .env ]; then
    cp .env.example .env
    sed -i 's/^BUDGETEER_DEV_LOGIN=false/BUDGETEER_DEV_LOGIN=true/' .env
    php artisan key:generate --quiet
fi
php artisan migrate --force --quiet
