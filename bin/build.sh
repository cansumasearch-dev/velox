#!/usr/bin/env bash
#
# Build a release zip from the repo, but only if the plugin actually passes.
#
# Run from anywhere:  bin/build.sh
#
# The version is READ FROM velox.php, never passed in and never hardcoded.
# A build script that takes a version as an argument is how you end up
# re-uploading an old zip and silently downgrading a live site.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

fail() { printf '\n\033[31mFAILED:\033[0m %s\n' "$1" >&2; exit 1; }
step() { printf '\n\033[1m%s\033[0m\n' "$1"; }

# ---------------------------------------------------------------- version ---
# Both places must agree. A bump that updates the header but not the constant
# (or the reverse) produces a plugin that reports one version and behaves as
# another, and WordPress will show the wrong one on the Plugins screen.
HEADER_V="$(sed -n 's/^ \* Version: *\([0-9][0-9.]*\).*/\1/p' velox.php)"
CONST_V="$(sed -n "s/.*define( 'VELOX_VERSION', '\([0-9][0-9.]*\)' ).*/\1/p" velox.php)"
HEADER_V="${HEADER_V%%$'\n'*}"
CONST_V="${CONST_V%%$'\n'*}"

[ -n "$HEADER_V" ] || fail "could not read the Version: header from velox.php"
[ -n "$CONST_V" ]  || fail "could not read VELOX_VERSION from velox.php"
[ "$HEADER_V" = "$CONST_V" ] || fail "version mismatch — header says $HEADER_V, VELOX_VERSION says $CONST_V"

VERSION="$CONST_V"
step "Building Velox $VERSION"

# ------------------------------------------------------------------ checks ---
step "PHP syntax"
ERRORS=0
while IFS= read -r f; do
	php -l "$f" >/dev/null 2>&1 || { echo "  $f"; ERRORS=$((ERRORS+1)); }
done < <(find . -name '*.php' -not -path './build/*' -not -path './.git/*')
[ "$ERRORS" -eq 0 ] || fail "$ERRORS PHP file(s) did not parse"
echo "  ok"

step "JavaScript syntax"
for f in admin/js/*.js; do
	node --check "$f" >/dev/null 2>&1 || fail "$f did not parse"
done
echo "  ok"

step "German translations"
php bin/check-i18n.php || fail "untranslated strings — every user-facing string needs a German entry"

# --------------------------------------------------------------------- zip ---
# Staged through a folder literally named "velox" so the archive unpacks to
# wp-content/plugins/velox/, which is what WordPress expects on upload.
step "Packaging"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/velox"
rsync -a \
	--exclude='.git' \
	--exclude='.github' \
	--exclude='.gitignore' \
	--exclude='.DS_Store' \
	--exclude='build' \
	./ "$STAGE/velox/"

mkdir -p build
ZIP="$ROOT/build/velox-$VERSION.zip"
rm -f "$ZIP"
( cd "$STAGE" && zip -qr "$ZIP" velox )

# ------------------------------------------------------------------ verify ---
# Read the version back out of the finished archive. This is the check that
# catches a stale or half-written zip before it reaches a live site.
step "Verifying the archive"
BUILT_V="$(unzip -p "$ZIP" velox/velox.php | sed -n "s/.*define( 'VELOX_VERSION', '\([0-9][0-9.]*\)' ).*/\1/p")"
BUILT_V="${BUILT_V%%$'\n'*}"
[ "$BUILT_V" = "$VERSION" ] || fail "the zip reports $BUILT_V, expected $VERSION"
LISTING="$(unzip -l "$ZIP")"
case "$LISTING" in *"velox/velox.php"*) ;; *) fail "velox.php is not at velox/ inside the archive" ;; esac

printf '\n\033[32mBuilt\033[0m %s (%s)\n' "$ZIP" "$(du -h "$ZIP" | cut -f1)"
printf 'Upload via wp-admin → Plugins → Add New → Upload → "Replace current with uploaded".\n'
