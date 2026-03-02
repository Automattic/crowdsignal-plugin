.DEFAULT_GOAL := help

## Setup
install: ## Install npm and composer dependencies
	npm install
	composer install

setup: install ## Install dependencies and start WordPress environment
	npx @wordpress/env start

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

## Build & Deploy
clean: ## Remove tmp/ directory
	./build.sh clean

build: ## Clean and copy plugin files to tmp/build/
	./build.sh build

package: ## Build and create zip archive
	./build.sh package

deploy: ## Full release: merge develop → main, deploy to WordPress.org SVN
	./build.sh deploy

deploy-unsafe: ## Build without branch/tag/SVN checks (local testing only)
	./build.sh deploy-unsafe

## Help
help: ## Show this help
	@grep -E '^[a-zA-Z0-9_-]+:.*##' $(MAKEFILE_LIST) \
		| awk -F ':.*## ' '{ printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }'

.PHONY: help install setup lint lint-fix test test-unit test-integration \
	i18n up down env-destroy clean build package deploy deploy-unsafe
