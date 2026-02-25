# Crowdsignal Dashboard Plugin

Legacy WordPress plugin for creating and managing Crowdsignal polls and ratings. WordPress.org slug is `polldaddy`.

## Development

```bash
npm install
composer install
```

### Linting

```bash
composer phpcs        # WordPress Coding Standards check
composer phpcbf       # Auto-fix
```

### Testing

```bash
wp-env start                    # Local WordPress at localhost:8888 (admin/password)
composer test                   # Unit + integration tests
composer test:unit              # Unit tests only (no WordPress bootstrap)
composer test:integration       # Integration tests (requires wp-env)

# Single test
./vendor/bin/phpunit --filter testMethodName --testsuite=unit
```

## Building and Releasing

All build tasks use `build.sh` (no Grunt dependency required):

```bash
./build.sh clean              # Remove tmp/ directory
./build.sh build              # Clean and copy plugin files to tmp/build/
./build.sh package            # Build and zip to tmp/polldaddy.zip
```

### Deploying to WordPress.org

```bash
./build.sh deploy
```

The version is read from the `Stable tag` field in `readme.txt`. A GitHub Action automatically creates the git tag when the version is bumped.

The `deploy` command automates the full release workflow (requires [GitHub CLI](https://cli.github.com/)):

1. Reads the version from `readme.txt`.
2. Verifies you are on `develop`.
3. Creates a PR from `develop` → `main` and merges it via `gh`.
4. Checks out `main` and pulls.
5. Builds and deploys to WordPress.org SVN.
6. Returns to `develop` and merges `main` back.

To build without deploy (for local testing only):

```bash
./build.sh deploy-unsafe      # Build without branch/tag/SVN checks
```

## Branching

- **`develop`** — default branch for day-to-day work and PR target.
- **`main`** — release branch. HEAD is always a tagged release.
