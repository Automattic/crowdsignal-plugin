.DEFAULT_GOAL := help

## Setup
install: ## Install npm and composer dependencies
	npm install
	composer install

setup: install ## Install dependencies and start WordPress environment
	npx @wordpress/env start

install-hooks: ## Install git hooks (blocks direct pushes to trunk)
	git config core.hooksPath .githooks
	@echo "Git hooks installed (core.hooksPath = .githooks)."

## Linting
lint: ## Run PHP_CodeSniffer
	composer phpcs

lint-fix: ## Auto-fix coding standards issues
	composer phpcbf

## Testing
test: ## Run all tests (unit + integration)
	composer test

test-unit: ## Run unit tests only
	composer test:unit

test-integration: ## Run integration tests (requires wp-env)
	composer test:integration

## i18n
i18n: ## Generate .pot translation file
	wp i18n make-pot . languages/polldaddy.pot

## WordPress environment
up: ## Start local WordPress environment
	npx @wordpress/env start

down: ## Stop local WordPress environment
	npx @wordpress/env stop

env-destroy: ## Destroy local WordPress environment
	npx @wordpress/env destroy

## Build & Release
build: ## Build build/polldaddy.zip from tracked files at HEAD
	./scripts/build-plugin.sh

clean: ## Remove the build/ directory
	rm -rf build

release: ## Prepare a release PR. Usage: make release VERSION=x.y.z
	@test -n "$(VERSION)" || { echo "Usage: make release VERSION=x.y.z"; exit 1; }
	node scripts/prepare-release.mjs $(VERSION)

## Help
help: ## Show this help
	@grep -E '^[a-zA-Z0-9_-]+:.*##' $(MAKEFILE_LIST) \
		| awk -F ':.*## ' '{ printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }'

.PHONY: help install setup install-hooks lint lint-fix test test-unit test-integration \
	i18n up down env-destroy clean build release
