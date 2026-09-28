#!/bin/bash
# Builds build/budgeteer-<commit>.zip, ready to upload to Afrihost and extract into ~/budgeteer:
# the committed code plus vendor/ (production packages only). No .env, tests or development files.
set -euo pipefail

cd "$(dirname "$0")/.."
if [ -n "$(git status --porcelain -- app bootstrap config database public resources routes composer.json composer.lock artisan)" ]; then
    echo "Commit your changes first: the zip is built from the last commit." >&2
    exit 1
fi

commit=$(git rev-parse --short HEAD)
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

git archive HEAD | tar -x -C "$work"
(
    cd "$work"
    export COMPOSER_NO_INTERACTION=1
    composer install --no-dev --optimize-autoloader --quiet 2>/dev/null \
        || composer install --no-dev --optimize-autoloader --quiet --prefer-source
    find vendor -type d -name .git -prune -exec rm -rf {} +
    rm -rf tests .github .claude scripts docs phpunit.xml .editorconfig .gitattributes README.md
    mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
)

mkdir -p build
zip_path="build/budgeteer-${commit}.zip"
rm -f "$zip_path"
(cd "$work" && zip -qr - .) > "$zip_path"
echo "Built $zip_path ($(du -h "$zip_path" | cut -f1))"
