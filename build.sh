#!/usr/bin/env bash
set -euo pipefail

# Crowdsignal / Polldaddy release helper
# - build:   rsync repo -> tmp/build (excluding dev files)
# - package: build + zip to tmp/polldaddy.zip
# - deploy:  (optionally) merge develop->main, then rsync build -> WP.org SVN trunk and tag

BUILD_DIR="tmp/build"
SVN_DIR="tmp/release-svn"
TMP_DIR="tmp"
PLUGIN_SLUG="polldaddy"
SVN_URL_DEFAULT="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}"

# --- defaults (overridable by flags) ---
INTERACTIVE=1
DRY_RUN=0
MERGE_PR=1          # create+merge PR develop->main via gh
PUSH_DEVELOP=1
REQUIRE_CLEAN=1
DO_PACKAGE=0        # if set, also zip during deploy
ZIP_NAME="${PLUGIN_SLUG}.zip"

# --- helpers ---

log() { printf '%s\n' "$*"; }
err() { printf 'Error: %s\n' "$*" >&2; }
die() { err "$*"; exit 1; }

run() {
  if (( DRY_RUN )); then
    log "[dry-run] $*"
  else
    "$@"
  fi
}

require_cmds() {
  local missing=0
  for c in "$@"; do
    command -v "$c" >/dev/null 2>&1 || { err "Missing command: $c"; missing=1; }
  done
  (( missing == 0 )) || exit 1
}

require_clean_worktree() {
  (( REQUIRE_CLEAN )) || return 0
  git diff --quiet || die "Working tree has unstaged changes. Commit or stash before proceeding."
  git diff --cached --quiet || die "Working tree has staged but uncommitted changes. Commit or stash before proceeding."
}

current_branch() {
  git rev-parse --abbrev-ref HEAD
}

ensure_on_branch() {
  local want="$1"
  local have
  have="$(current_branch)"
  [[ "$have" == "$want" ]] || die "Must be on '$want' branch (currently on '$have')."
}

read_version_from_readme() {
  local v
  v="$(grep -m1 '^Stable tag:' readme.txt | awk '{print $NF}' || true)"
  [[ -n "${v:-}" ]] || die "Could not read version from 'Stable tag' in readme.txt"
  [[ "$v" != "trunk" ]] || die "Stable tag is 'trunk' (not a version). Update readme.txt Stable tag."
  printf '%s' "$v"
}

clean_tmp() {
  run rm -rf "$TMP_DIR/"
  log "Cleaned $TMP_DIR/"
}

copy_to_build() {
  run mkdir -p "$BUILD_DIR"
  # Note: --exclude='.*' excludes all dotfiles. If you ever need a dotfile shipped, remove/adjust that line.
  run rsync -a --delete \
    --exclude='*.log' \
    --exclude='node_modules' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='.svn' \
    --exclude='tests' \
    --exclude='bin' \
    --exclude='phpunit.xml' \
    --exclude='phpunit.xml.dist' \
    --exclude='vendor' \
    --exclude='composer.lock' \
    --exclude='composer.phar' \
    --exclude='composer.json' \
    --exclude='.*' \
    --exclude='**/*~' \
    --exclude='tmp' \
    --exclude='CONTRIBUTING.md' \
    --exclude='README.md' \
    --exclude='phpcs.xml.dist' \
    --exclude='tools' \
    --exclude='screenshot-1.png' \
    --exclude='screenshot-2.png' \
    --exclude='banner-1544x500.png' \
    --exclude='build.sh' \
    --exclude='Makefile' \
    ./ "$BUILD_DIR/"

  log "Copied files to $BUILD_DIR/"
}

build() {
  require_cmds rsync
  clean_tmp
  copy_to_build
}

package() {
  require_cmds rsync zip
  build
  (cd "$BUILD_DIR" && run zip -r -X "../${ZIP_NAME}" .)
  log "Packaged to tmp/${ZIP_NAME}"
}

main_contains_develop() {
  # true if develop is an ancestor of origin/main (i.e., main already has develop)
  git merge-base --is-ancestor develop origin/main
}

create_and_merge_pr() {
  local version="$1"
  require_cmds gh

  if main_contains_develop; then
    log "origin/main already contains develop; skipping PR."
    return 0
  fi

  if (( DRY_RUN )); then
    log "[dry-run] gh pr create --base main --head develop --title 'Release ${version}'"
    log "[dry-run] gh pr merge <pr_url> --merge --auto"
    log "[dry-run] gh pr checks <pr_url> --watch"
    return 0
  fi

  local pr_url
  pr_url="$(gh pr create \
    --base main \
    --head develop \
    --title "Release ${version}" \
    --body "Merge develop into main for release ${version}." \
  )"

  log "Created PR: ${pr_url}"

  gh pr merge "$pr_url" --merge --auto
  log "Waiting for PR checks/merge..."
  gh pr checks "$pr_url" --watch || true

  # Poll until merged (timeout after 10 minutes)
  local max_wait=600
  local elapsed=0
  while [[ "$(gh pr view "$pr_url" --json state --jq .state)" != "MERGED" ]]; do
    local state
    state="$(gh pr view "$pr_url" --json state --jq .state)"
    [[ "$state" == "CLOSED" ]] && die "PR was closed without merging."
    (( elapsed >= max_wait )) && die "Timed out waiting for PR to merge after ${max_wait}s."
    sleep 5
    (( elapsed += 5 ))
  done
  log "PR merged."
}

svn_tag_exists() {
  local svn_url="$1"
  local version="$2"
  svn ls "${svn_url}/tags/${version}" >/dev/null 2>&1
}

svn_sync_and_commit() {
  local svn_url="$1"
  local version="$2"

  require_cmds svn rsync awk grep xargs

  log "Deploying ${PLUGIN_SLUG} ${version} to WordPress.org SVN..."
  run rm -rf "$SVN_DIR"
  run svn checkout "${svn_url}/trunk" "${SVN_DIR}/trunk"

  # Sync build -> trunk
  run rsync -a --delete --exclude='.svn' "${BUILD_DIR}/" "${SVN_DIR}/trunk/"

  # Add new files and remove deleted ones
  if (( ! DRY_RUN )); then
    ( cd "${SVN_DIR}/trunk" && \
      svn add --force . && \
      (svn status | grep '^\!' | awk '{print $2}' | xargs -r svn rm) || true \
    )
  else
    log "[dry-run] svn add --force . (in ${SVN_DIR}/trunk)"
    log "[dry-run] svn rm <deleted files>"
  fi

  if (( INTERACTIVE )); then
    log "Review SVN trunk changes in: ${SVN_DIR}/trunk"
    log "Press RETURN to commit trunk to SVN."
    read -r
  fi

  if (( ! DRY_RUN )); then
    ( cd "${SVN_DIR}/trunk" && svn commit -m "Release ${version}" )
  else
    log "[dry-run] svn commit -m 'Release ${version}' (in ${SVN_DIR}/trunk)"
  fi

  # Create tag via server-side copy
  run svn copy "${svn_url}/trunk" "${svn_url}/tags/${version}" -m "Tag ${version}"

  log "SVN deployed + tagged ${version}"
}

deploy() {
  require_cmds git grep awk

  local svn_url="${SVN_URL_DEFAULT}"

  require_clean_worktree
  ensure_on_branch develop

  local version
  version="$(read_version_from_readme)"
  log "Preparing release version: ${version}"

  if (( PUSH_DEVELOP )); then
    run git push origin develop
  fi

  run git fetch origin main

  if (( MERGE_PR )); then
    create_and_merge_pr "$version"
  else
    log "Skipping PR merge step (--no-pr)."
  fi

  # Update local main to match origin/main
  run git checkout main
  run git pull origin main

  # Build from main (the code being released)
  build

  # Verify version on main matches what we read from develop
  local main_version
  main_version="$(read_version_from_readme)"
  [[ "$main_version" == "$version" ]] || \
    die "Version mismatch: develop has ${version}, main has ${main_version}."

  # Optional zip package during deploy
  if (( DO_PACKAGE )); then
    (cd "$BUILD_DIR" && run zip -r -X "../${ZIP_NAME}" .)
    log "Packaged to tmp/${ZIP_NAME}"
  fi

  # Prevent tagging over an existing SVN tag
  require_cmds svn
  if svn_tag_exists "$svn_url" "$version"; then
    die "SVN tag already exists: tags/${version} (bump version or delete tag manually in SVN)."
  fi

  svn_sync_and_commit "$svn_url" "$version"

  run git checkout develop

  if (( DO_PACKAGE )); then
    log "Kept tmp/ (contains ${ZIP_NAME}). Run 'make clean' when done."
  else
    clean_tmp
  fi
  log "Done."
}

usage() {
  cat <<-EOF
Usage: $0 <command> [options]

Commands:
  clean              Remove tmp/ directory
  build              Clean and copy plugin files to ${BUILD_DIR}/
  package            Build and zip to tmp/${ZIP_NAME}
  deploy             (Optional) merge develop->main, then deploy to WordPress.org SVN trunk + tag

Options (apply to deploy; some also affect package/build):
  --no-interactive    Skip interactive prompt before SVN commit
  --dry-run           Print commands instead of executing them
  --no-pr             Do not create/merge a PR (assumes main is already correct)
  --no-push           Do not push develop before creating PR
  --no-clean-check    Allow deploy with a dirty git working tree
  --with-zip          Also create tmp/${ZIP_NAME} during deploy
  --svn-url URL       Override SVN base URL (default: ${SVN_URL_DEFAULT})

Examples:
  $0 build
  $0 package
  $0 deploy --no-interactive
  $0 deploy --dry-run --no-pr --with-zip
EOF
}

# --- dispatch + option parsing ---

cmd="${1:-}"
shift || true

# parse flags (for any command; harmless if unused)
SVN_URL_OVERRIDE=""
while [[ "${1:-}" == --* ]]; do
  case "$1" in
    --no-interactive) INTERACTIVE=0 ;;
    --dry-run) DRY_RUN=1 ;;
    --no-pr) MERGE_PR=0 ;;
    --no-push) PUSH_DEVELOP=0 ;;
    --no-clean-check) REQUIRE_CLEAN=0 ;;
    --with-zip) DO_PACKAGE=1 ;;
    --svn-url)
      shift || die "--svn-url requires a value"
      SVN_URL_OVERRIDE="$1"
      ;;
    --help|-h) usage; exit 0 ;;
    *) die "Unknown option: $1 (use --help)" ;;
  esac
  shift || true
done

if [[ -n "$SVN_URL_OVERRIDE" ]]; then
  SVN_URL_DEFAULT="$SVN_URL_OVERRIDE"
fi

case "$cmd" in
  clean)   clean_tmp ;;
  build)   build ;;
  package) package ;;
  deploy)  deploy ;;
  ""|help|--help|-h) usage ;;
  *) die "Unknown command: $cmd (use --help)" ;;
esac
