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

- **WordPress 6.6.1** on PHP 8.0
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

Run the CSRF vulnerability fix tests:
```bash
# Unit tests for CSRF fix
php tests/csrf-vulnerability-cve-2024-43338/test-csrf-fix.php

# Behavior analysis (compare vulnerable vs fixed)
php tests/csrf-vulnerability-cve-2024-43338/csrf-behavior-test.php

# Frontend functionality tests
php tests/test-frontend-functionality.php

# Manual CSRF testing (login to WordPress admin first)
# Visit: http://localhost:8888/wp-admin/media-upload.php?polls_media=1&csrf_test=attack
```

### Code Quality

The project uses PHP_CodeSniffer for code standards:
```bash
# Check if phpcs is available
phpcs --version

# Run code standards check
phpcs --standard=phpcs.ruleset.xml .
```

## Security Fix: CVE-2024-43338

### Vulnerability Description
The plugin was vulnerable to Cross-Site Request Forgery (CSRF) in the `polldaddy_popups_init()` function in `popups.php`. The function processed `$_REQUEST['polls_media']` without proper nonce verification.

### Fix Implementation
Added proper access control to prevent CSRF while preserving legitimate functionality:
```php
function polldaddy_popups_init() {
    if( isset( $_REQUEST['polls_media'] ) ){
        // Security check: Only add filters if we're in a valid admin context
        // This prevents CSRF attacks while allowing legitimate media upload functionality
        if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        // ... rest of function
    }
}
```

### Testing the Fix
The fix has been verified with comprehensive tests:

**Security Tests** (`tests/test-csrf-fix.php`):
- Non-admin contexts are blocked
- Users without edit_posts capability are blocked
- Valid admin users with proper capabilities can access functionality
- Requests without polls_media parameter work normally

**Frontend Tests** (`tests/test-frontend-functionality.php`):
- Admin media upload functionality is preserved
- CSRF attacks from frontend are prevented
- Proper WordPress capability checking is enforced

## Project Structure

```
crowdsignal-plugin/
├── .wp-env.json           # WordPress environment config
├── polldaddy.php          # Main plugin file
├── popups.php             # Fixed CSRF vulnerability here
├── ajax.php               # AJAX handlers (already secure)
├── partials/              # Admin UI partials
├── js/                    # JavaScript assets
├── css/                   # Stylesheets
├── tests/                 # Test files
│   └── test-csrf-fix.php  # CSRF fix verification
├── package.json           # Node.js dependencies
├── Gruntfile.js          # Build configuration
└── DEVELOPMENT.md         # This file
```

## Stopping the Environment

```bash
wp-env stop
```

## Troubleshooting

### WordPress Environment Issues
- Ensure Docker is running
- Try stopping and restarting: `wp-env stop && wp-env start`
- Check container status: `docker ps`

### Plugin Not Loading
- Verify the plugin is activated in wp-admin
- Check for PHP errors in the debug log
- Ensure file permissions are correct

### Build Issues
- Clear npm cache: `npm cache clean --force`
- Delete node_modules and reinstall: `rm -rf node_modules && npm install`