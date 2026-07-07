#!/usr/bin/env bash
#
# Assembles build/polldaddy/ and build/polldaddy.zip from the tracked files at
# HEAD (via `git archive`), minus dev-only paths.
#
# Using `git archive` rather than copying the working tree means untracked or
# ignored files can never leak into the shipped plugin. This is a pure,
# non-interactive build step: it runs both locally (`make build`) and in CI as
# part of the release workflow.

set -euo pipefail

command -v git >/dev/null || { >&2 echo "git is required"; exit 1; }
command -v zip >/dev/null || { >&2 echo "zip is required"; exit 1; }

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PLUGIN_SLUG="polldaddy"
BUILD_DIR="build"
PLUGIN_DIR="$BUILD_DIR/$PLUGIN_SLUG"

rm -rf "$BUILD_DIR"
mkdir -p "$PLUGIN_DIR"

# Export only tracked files at HEAD.
git archive HEAD | tar -x -C "$PLUGIN_DIR"

# Remove tracked dev-only paths that must not ship (including this release tooling).
dev_paths=(
	tests
	bin
	phpunit.xml.dist
	phpcs.xml.dist
	composer.json
	package.json
	Makefile
	CONTRIBUTING.md
	README.md
	release.config.json
	scripts
	screenshot-1.png
	screenshot-2.png
	banner-1544x500.png
)
for path in "${dev_paths[@]}"; do
	rm -rf "${PLUGIN_DIR:?}/${path}"
done

# Drop top-level dotfiles (.github, .editorconfig, .gitignore, .wp-env.json, ...).
find "$PLUGIN_DIR" -mindepth 1 -maxdepth 1 -name '.*' -exec rm -rf {} +

( cd "$BUILD_DIR" && zip -rqX "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG" )

echo "Built $BUILD_DIR/$PLUGIN_SLUG.zip"
