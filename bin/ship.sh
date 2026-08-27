#!/usr/bin/env bash
#
# Publish Velox: check, commit, push, tag, release.
#
#   bin/ship.sh
#
# The version comes from velox.php. There is nothing to type and nothing to
# remember. Running this twice in a row is safe — the second run finds nothing
# new and stops, so a stray double-click cannot ship anything twice.
#
# The tag is the part that matters. Pushing the branch changes nothing for
# anyone; pushing the tag builds a GitHub release, and every site running Velox
# polls releases/latest and offers it as an update. That is the point of no
# return, so it happens last and only when everything else has passed.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

BOLD=$'\033[1m'; RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; OFF=$'\033[0m'
fail() { printf '\n%sStopped:%s %s\n\n' "$RED" "$OFF" "$1" >&2; exit 1; }
step() { printf '\n%s%s%s\n' "$BOLD" "$1" "$OFF"; }

VERSION="$(sed -n "s/.*define( 'VELOX_VERSION', '\([0-9][0-9.]*\)' ).*/\1/p" velox.php)"
VERSION="${VERSION%%$'\n'*}"
[ -n "$VERSION" ] || fail "could not read VELOX_VERSION from velox.php"
TAG="v$VERSION"

printf '\n%sPublishing Velox %s%s\n' "$BOLD" "$VERSION" "$OFF"

# ------------------------------------------------------------------ sanity ---
BRANCH="$(git rev-parse --abbrev-ref HEAD)"
[ "$BRANCH" = "main" ] || fail "you are on branch '$BRANCH', not main"

git ls-remote --heads origin main >/dev/null 2>&1 || fail "cannot reach GitHub — check your connection"

# A release with no changelog entry is a release nobody can read. This has
# shipped before as an empty version bump; the check is cheap.
grep -q "^## $VERSION" CHANGELOG.md || fail "CHANGELOG.md has no '## $VERSION' entry — write one first"

# Already published? Then there is nothing to do and saying so is the whole job.
REMOTE_TAG="$(git ls-remote --tags origin "refs/tags/$TAG" 2>/dev/null || true)"
if [ -n "$REMOTE_TAG" ]; then
	if [ -z "$(git status --porcelain)" ] && [ "$(git rev-list --count origin/main..HEAD 2>/dev/null || echo 0)" = "0" ]; then
		printf '\n%sAlready published.%s Velox %s is on GitHub and nothing has changed since.\n\n' "$GREEN" "$OFF" "$VERSION"
		exit 0
	fi
	fail "$TAG is already published, but you have unpublished changes. Bump the version in velox.php and add a CHANGELOG entry."
fi

# ------------------------------------------------------------------ checks ---
step "Checking the plugin"
./bin/build.sh >/dev/null 2>&1 || {
	printf '%sThe checks failed. Running them again so you can see why:%s\n' "$YELLOW" "$OFF"
	./bin/build.sh || true
	fail "not publishing a build that does not pass"
}
echo "  everything passes"

# ------------------------------------------------------------------ commit ---
if [ -n "$(git status --porcelain)" ]; then
	step "Committing your changes"
	git add -A
	NOTES="$(sed -n "/^## $VERSION/,/^## /p" CHANGELOG.md | sed '1d;$d')"
	git commit -q -m "$VERSION" -m "$NOTES"
	echo "  committed"
fi

# -------------------------------------------------------------------- push ---
AHEAD="$(git rev-list --count origin/main..HEAD)"
if [ "$AHEAD" != "0" ]; then
	step "Pushing $AHEAD commit(s) to GitHub"
	git push -q origin main
	echo "  pushed"
fi

# --------------------------------------------------------------------- tag ---
# This is the step that reaches every site running Velox.
step "Releasing $TAG"
git tag "$TAG"
git push -q origin "$TAG"
echo "  tag pushed — GitHub is building the release now"

printf '\n%sPublished Velox %s%s\n' "$GREEN" "$VERSION" "$OFF"
printf 'Release:  https://github.com/cansumasearch-dev/velox/releases/tag/%s\n' "$TAG"
printf 'Progress: https://github.com/cansumasearch-dev/velox/actions\n'
printf '\nEvery site running Velox will offer this as an update within a few hours,\nor immediately if you hit "Check again" on its Plugins screen.\n\n'
