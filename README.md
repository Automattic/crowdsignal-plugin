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

Releases use a two-phase, CI-deployed flow:

1. **`make release VERSION=x.y.z`** (run from `trunk`) bumps the version in `polldaddy.php`/`readme.txt`/`package.json`, assembles the changelog from the GitHub milestone `x.y.z`, and opens a PR with the changelog editable in the body.
2. Merging that PR runs `.github/workflows/create-release.yml`, which writes the changelog into `readme.txt`, tags, builds `build/polldaddy.zip`, creates the GitHub release, and deploys to WordPress.org SVN via the 10up action.

Create a GitHub milestone named exactly `x.y.z` and assign the release's PRs to it first. WordPress.org release confirmation is enabled, so the deploy is held pending an email confirmation before it goes live.

`make build` packages `build/polldaddy/` and `build/polldaddy.zip` from the tracked files at HEAD (via `git archive`, so untracked/ignored files never leak); `make i18n` regenerates the POT.

## Branching

- **`trunk`** — the single default branch. Work branches off it and PRs target it; releases are cut from it.
