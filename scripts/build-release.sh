#!/usr/bin/env bash
# Builds a production release zip containing only what the server needs.
#
#   bash scripts/build-release.sh            # -> build/release.zip
#
# Requires PHP 8.1+ with composer, and Node 18+ with npm.
# Secrets (.env, Firebase service account) are NOT packaged; they live on the server.
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)
OUT="$ROOT/build"
STAGE="$OUT/release"

rm -rf "$OUT"
mkdir -p "$STAGE"

echo "==> Building front-end assets"
npm ci --no-audit --no-fund
npm run prod

echo "==> Copying application files"
rsync -a "$ROOT/" "$STAGE/" \
  --exclude-from="$ROOT/scripts/release-exclude.txt"

echo "==> Installing PHP dependencies (no dev)"
# ext-grpc is needed at runtime for Firestore chat; install it on the server (pecl install grpc).
(cd "$STAGE" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction \
  --no-scripts --ignore-platform-req=ext-grpc)

echo "==> Creating zip"
(cd "$STAGE" && zip -qr "$OUT/release.zip" .)
echo "Release ready: $OUT/release.zip"
