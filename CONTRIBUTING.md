# Contributing to the Crowdsignal Plugin

Thank you for your interest in contributing to the Crowdsignal plugin! This document provides guidelines and information for contributors.

## Getting Started

### Prerequisites

- Docker and Docker Compose
- Node.js and npm
- PHP 7.4+ (recommended, minimum 5.6 for plugin compatibility)
- Composer

### Development Environment Setup

1. **Start the development environment:**

   ```bash
   wp-env start
   ```

2. **Access the local WordPress site:**

   - WordPress front end: <http://localhost:8888>
   - WordPress admin: <http://localhost:8888/wp-admin>
   - Username: `admin`
   - Password: `password`

3. **Install dependencies:**

   ```bash
   npm install
   composer install
   ```

4. **Build assets:**

   ```bash
   npm run build
   ```

## Environment Details

The development environment uses `@wordpress/env` which provides:

- **Latest WordPress** on **Latest PHP**
- **MySQL/MariaDB** database
- **Automatic plugin mounting** - the current directory is mounted as a plugin
- **Debug settings enabled** for development

Configuration is stored in `.wp-env.json`:

- Debug logging enabled
- Script debugging enabled
- Development ports: 8888 (main), 8889 (tests)

## Development Workflow

### Making Changes

1. Edit PHP files directly - changes are reflected immediately
2. For JavaScript/CSS changes, rebuild assets:

   ```bash
   npm run build
   ```

### Code Standards

The project follows WordPress Coding Standards. Before submitting any code:

1. **Run PHP_CodeSniffer:**

   ```bash
   composer phpcs
   ```

2. **Auto-fix coding standards issues:**

   ```bash
   composer phpcbf
   ```

### Testing

We maintain both unit and integration tests. Always run tests before submitting changes:

1. **Start environment with Xdebug for coverage:**

   ```bash
   wp-env start --xdebug=coverage
   ```

2. **Run all tests:**

   ```bash
   wp-env run tests-cli --env-cwd=wp-content/plugins/crowdsignal-plugin composer test
   ```

3. **Run specific test suites:**

   ```bash
   # Unit tests only
   composer test:unit
   
   # Integration tests only
   composer test:integration
   ```

## Contributing Guidelines

### Reporting Issues

When reporting bugs or requesting features:

1. **Search existing issues** first to avoid duplicates
2. **Use clear, descriptive titles**
3. **Provide detailed reproduction steps** for bugs
4. **Include environment information** (WordPress version, PHP version, etc.)
5. **Add screenshots or error logs** when relevant

### Submitting Pull Requests

1. **Fork the repository** and create a feature branch from `main`
2. **Follow the coding standards** outlined above
3. **Write or update tests** for your changes
4. **Ensure all tests pass** before submitting
5. **Write clear commit messages** following [Conventional Commits](https://www.conventionalcommits.org/)
6. **Update documentation** if your changes affect user-facing functionality
7. **Keep pull requests focused** - one feature or fix per PR

### Code Review Process

- All submissions require review before merging
- Reviewers may request changes or improvements
- Address feedback promptly and push updates to your branch
- Once approved, maintainers will merge your contribution

### Commit Message Format

Use clear, descriptive commit messages:

```text
type(scope): brief description

Longer explanation if needed, including:
- What changed and why
- Any breaking changes
- References to issues (#123)
```

Examples:

- `fix(polls): resolve XSS vulnerability in poll titles`
- `feat(ratings): add support for custom rating scales`
- `docs(readme): update installation instructions`

## Project Structure

### Key Files

- `polldaddy.php` - Main plugin file (filename preserved for compatibility)
- `polldaddy-client.php` - API client
- `rating.php` - Ratings functionality
- `partials/` - Admin interface templates
- `tests/` - Unit and integration tests
- `css/` - Stylesheets
- `js/` - JavaScript files

### Legacy Naming

Note: This plugin maintains some legacy "polldaddy" naming in filenames and code for backward compatibility. While the service is now "Crowdsignal," changing these references would be a breaking change.

## Translation

Help translate the plugin:

- Visit the [WordPress.org translation site](http://translate.wordpress.com/projects/polldaddy/plugin)
- Some strings require translation through [Crowdsignal.com](https://crowdsignal.com/) language packs

## Release Process

1. Update version numbers in relevant files
2. Update changelog in `readme.txt`
3. Run full test suite
4. Build and package for release
5. Deploy to WordPress.org repository

## Getting Help

- **Documentation**: Check this file and `readme.txt`
- **Issues**: Use GitHub Issues for bug reports and feature requests
- **Support**: Visit [Crowdsignal Support](https://crowdsignal.com/support/)
- **Community**: WordPress.org support forums

## License

This project is licensed under GPL-2.0-or-later. By contributing, you agree that your contributions will be licensed under the same terms.

## Stopping the Environment

```bash
wp-env stop
```

This will stop the WordPress development environment.
