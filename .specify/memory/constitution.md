<!-- Sync Impact Report
Version change: 1.1.0 → 1.1.0 (no change)
Modified principles: None
Added sections: None
Removed sections: None
Templates requiring updates:
  ✅ plan-template.md - Fixed constitution version reference
  ✅ spec-template.md - Requirements aligned
  ✅ tasks-template.md - Task categories aligned
  ✅ agent-file-template.md - Template structure aligned
Follow-up TODOs: RATIFICATION_DATE needs to be confirmed
-->

# Crowdsignal Plugin Constitution

## Core Principles

### I. WordPress Standards Compliance
All code MUST follow WordPress coding standards and best practices.
This includes proper sanitization, escaping, nonces for security,
and adherence to plugin guidelines. WordPress APIs and hooks
MUST be used over custom implementations where available.
This ensures compatibility, security, and maintainability within
the WordPress ecosystem.

### II. Backward Compatibility
The plugin MUST maintain compatibility with at least the last
three major WordPress versions and PHP 5.6+. Breaking changes
require major version bumps and migration paths. All deprecated
features MUST have a minimum 6-month deprecation period with
clear documentation and console warnings.

### III. WordPress Test-Driven Development
All new code deployed MUST have comprehensive tests that follow
WordPress testing standards. Tests MUST use WordPress testing
frameworks (PHPUnit with WordPress test suite, QUnit for JavaScript).
Tests are written first using WordPress mock functions and factories,
approved by stakeholders, then code is implemented to make tests pass.
The Red-Green-Refactor cycle is strictly enforced. Every deployment
MUST pass all tests. Code coverage requirements: 85% minimum for
new code, 65% for legacy refactoring. Tests MUST cover WordPress
hooks, filters, AJAX handlers, REST endpoints, and database operations.

### IV. API-First Design
All functionality MUST be accessible via well-documented APIs.
The Crowdsignal API integration is the core data layer. New
features expose REST endpoints following WordPress REST API
conventions. All API changes require versioning and documentation
updates before implementation.

### V. Progressive Enhancement
Features MUST work without JavaScript where possible. JavaScript
enhances the experience but core functionality remains accessible.
AJAX operations have fallback mechanisms. The plugin gracefully
degrades on older browsers while leveraging modern capabilities
when available.

## Security & Privacy Standards

### Data Protection
User data handling MUST comply with GDPR and privacy regulations.
Poll responses are processed through secure Crowdsignal APIs only.
No sensitive data is stored in WordPress database without encryption.
All data transmission uses HTTPS. User consent mechanisms are
required for data collection.

### Authentication & Authorization
All admin actions require proper WordPress capabilities checks.
Nonces protect all forms and AJAX requests. API keys are stored
encrypted in database. User permissions follow WordPress role
system strictly. No hardcoded credentials in codebase.

## Development Workflow

### Code Review Process
All changes require pull request review before merging to develop.
At least one maintainer approval required. Automated tests must pass.
Code style checks via PHP_CodeSniffer must pass. Security review
for any authentication or data handling changes.

### Testing Standards
All tests MUST follow WordPress Core testing conventions and patterns.
Use WordPress test factories for creating test data. Mock WordPress
functions using Brain\Monkey or WP_Mock when appropriate. Integration
tests MUST use WordPress test database and clean up after execution.
Unit tests for PHP MUST extend WP_UnitTestCase. JavaScript tests
MUST follow WordPress QUnit patterns. No code can be deployed to
production without passing all tests. Test files MUST be organized
following WordPress plugin test structure: tests/phpunit for PHP,
tests/qunit for JavaScript.

### Release Management
Semantic versioning (MAJOR.MINOR.PATCH) strictly followed.
Release branches created from develop. Hotfixes branch from master.
Changelog updated for every release. WordPress.org repository
synchronized after testing. Beta testing period for major releases.

### Documentation Requirements
All public functions require PHPDoc blocks. README.md maintained
for developers. User documentation in WordPress.org format.
Inline comments for complex logic. API documentation auto-generated
from code comments.

## Governance

This constitution supersedes all other development practices for
the Crowdsignal Plugin project. Amendments require:
1. Proposal via GitHub issue with rationale
2. Discussion period of minimum 7 days
3. Approval from at least 2 maintainers
4. Documentation of changes and migration plan
5. Version increment following semantic versioning

All pull requests and code reviews MUST verify constitutional
compliance. Violations require explicit justification and
maintainer approval. Complex implementations that violate
simplicity principles must demonstrate necessity.

The DEVELOPMENT.md file provides runtime development guidance
and quick reference for common tasks.

**Version**: 1.1.0 | **Ratified**: TODO(RATIFICATION_DATE) | **Last Amended**: 2025-09-23