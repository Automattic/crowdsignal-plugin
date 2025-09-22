# Crowdsignal Plugin Development Environment

This document describes the local development environment setup for the Crowdsignal Plugin.

## Prerequisites

- Docker and Docker Compose
- Node.js and npm
- PHP 7.4+ (for testing)

## Quick Start

1. **Start the development environment:**
   ```bash
   wp-env start
   ```

2. **Access the local WordPress site:**
   - WordPress frontend: http://localhost:8888
   - WordPress admin: http://localhost:8888/wp-admin
   - Username: `admin`
   - Password: `password`

3. **Install dependencies:**
   ```bash
   npm install
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

### Testing

For security testing, see the security fix PR which includes comprehensive CSRF vulnerability tests.

### Code Quality

The project uses PHP_CodeSniffer for code standards:
```bash
# Check if phpcs is available
phpcs --version

# Run code standards check
phpcs --standard=phpcs.ruleset.xml .
```



## Stopping the Environment

```bash
wp-env stop
```

This will stop the WordPress development environment.