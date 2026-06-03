# Crowdsignal Dashboard Plugin

Legacy WordPress plugin for creating and managing Crowdsignal polls and ratings. WordPress.org slug is `polldaddy`.

## Development

```bash
make install          # npm install && composer install
make setup            # install + start wp-env
```

### Linting

```bash
make lint             # WordPress Coding Standards check
make lint-fix         # Auto-fix
```

### Testing

```bash
make up                                 # Local WordPress at localhost:8888 (admin/password)
make test                               # Unit + integration tests
make test-unit                          # Unit tests only (no WordPress bootstrap)
make test-integration                   # Integration tests (requires wp-env)

# Single test
./vendor/bin/phpunit --filter testMethodName --testsuite=unit
```

### i18n

```bash
make i18n             # Generate languages/polldaddy.pot
```

Run `make help` to see all available targets.

## Building and Releasing

All build tasks use `build.sh` (no Grunt dependency required):

```bash
make clean            # Remove tmp/ directory
make build            # Clean and copy plugin files to tmp/build/
make package          # Build and zip to tmp/polldaddy.zip
```

### Deploying to WordPress.org

```bash
make deploy
```

The version is read from the `Stable tag` field in `readme.txt`. A GitHub Action automatically creates the git tag when the version is bumped.

The `deploy` command automates the full release workflow (requires [GitHub CLI](https://cli.github.com/)):

1. Reads the version from `readme.txt`.
2. Verifies you are on `develop`.
3. Creates a PR from `develop` → `main` and merges it via `gh`.
4. Checks out `main` and pulls.
5. Builds and deploys to WordPress.org SVN.
6. Returns to `develop` and cleans up.

## Branching

- **`develop`** — default branch for day-to-day work and PR target.
- **`main`** — release branch. HEAD is always a tagged release.
