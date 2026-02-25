#!/usr/bin/env bash
set -euo pipefail

BUILD_DIR="tmp/build"
SVN_DIR="tmp/release-svn"
PLUGIN_SLUG="polldaddy"

# --- helpers ---

clean() {
	rm -rf tmp/
	echo "Cleaned tmp/"
}

copy_to_build() {
	mkdir -p "$BUILD_DIR"
	rsync -a --delete \
		--exclude='*.log' \
		--exclude='node_modules' \
		--exclude='Gruntfile.js' \
		--exclude='package.json' \
		--exclude='package-lock.json' \
		--exclude='.git' \
		--exclude='.github' \
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
		./ "$BUILD_DIR/"
	echo "Copied files to $BUILD_DIR/"
}

build() {
	clean
	copy_to_build
}

package() {
	build
	(cd "$BUILD_DIR" && zip -r "../${PLUGIN_SLUG}.zip" .)
	echo "Packaged to tmp/${PLUGIN_SLUG}.zip"
}

deploy() {
	local version
	version=$(grep -m1 '^Stable tag:' readme.txt | awk '{print $NF}')
	if [[ -z "$version" ]]; then
		echo "Error: could not read version from 'Stable tag' in readme.txt" >&2
		exit 1
	fi

	local branch
	branch=$(git rev-parse --abbrev-ref HEAD)
	if [[ "$branch" != "develop" ]]; then
		echo "Error: must be on 'develop' branch to deploy (currently on '$branch')" >&2
		exit 1
	fi

	echo "Deploying version $version (from readme.txt)..."

	# push develop so the remote is up to date
	git push origin develop

	# fetch main and check if there's anything to merge
	git fetch origin main
	if git merge-base --is-ancestor develop origin/main; then
		echo "main already contains develop, skipping PR."
	else
		# create PR from develop → main and merge it
		local pr_url
		pr_url=$(gh pr create --base main --head develop \
			--title "Release $version" \
			--body "Merge develop into main for release $version.")
		echo "Created PR: $pr_url"

		gh pr merge "$pr_url" --merge --auto
		echo "Waiting for PR to merge..."
		gh pr checks "$pr_url" --watch || true
		# poll until the PR is actually merged
		while [[ "$(gh pr view "$pr_url" --json state --jq .state)" != "MERGED" ]]; do
			sleep 5
		done
		echo "PR merged."
	fi

	# update local main
	git checkout main
	git pull origin main

	build

	local svn_url="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}"

	echo "Deploying $PLUGIN_SLUG $version to WordPress.org SVN..."
	rm -rf "$SVN_DIR"
	svn checkout "$svn_url/trunk" "$SVN_DIR/trunk"

	# sync build to trunk
	rsync -a --delete --exclude='.svn' "$BUILD_DIR/" "$SVN_DIR/trunk/"
	cd "$SVN_DIR/trunk"
	svn add --force .
	svn status | grep '^\!' | awk '{print $2}' | xargs -r svn rm || true
	cd "$OLDPWD"

	echo "Press RETURN to commit trunk to SVN. Check $SVN_DIR/trunk".
	read

	(cd "$SVN_DIR/trunk" && svn commit -m "Release $version")

	# create tag via server-side copy
	svn copy "$svn_url/trunk" "$svn_url/tags/$version" -m "Tag $version"
	echo "Deployed $version"

	git checkout develop

	clean
	echo "Done."
}

deploy_unsafe() {
	build
	echo "Build complete in $BUILD_DIR/ — skipped branch/tag/clean checks."
	echo "Manual SVN deploy from $BUILD_DIR/ is required."
}

usage() {
	cat <<-EOF
	Usage: $0 <command>

	Commands:
	  clean              Remove tmp/ directory
	  build              Clean and copy plugin files to $BUILD_DIR/
	  package            Build and zip to tmp/${PLUGIN_SLUG}.zip
	  deploy             Merge develop into main, push, and deploy to WordPress.org SVN
	  deploy-unsafe      Build without branch/tag checks
	EOF
}

# --- dispatch ---

case "${1:-}" in
	clean)          clean ;;
	build)          build ;;
	package)        package ;;
	deploy)         deploy ;;
	deploy-unsafe)  deploy_unsafe ;;
	*)              usage ;;
esac
