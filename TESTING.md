# Testing Guide for Crowdsignal Plugin

This document outlines the testing infrastructure and practices for the Crowdsignal WordPress plugin.

## Overview

The Crowdsignal plugin uses a comprehensive testing suite that includes:

- **Unit Tests**: Test individual functions and classes in isolation
- **Integration Tests**: Test component interactions and WordPress integration
- **Coding Standards**: Automated code quality checks
- **Security Checks**: Vulnerability scanning and security validation
- **Continuous Integration**: Automated testing on multiple PHP/WordPress versions

## Quick Start

### Prerequisites

- PHP 7.4 or higher
- Composer
- WordPress test environment
- MySQL/MariaDB

### Installation

1. Install dependencies:
```bash
composer install
```

2. Set up WordPress test environment:
```bash
bash bin/install-wp-tests.sh wordpress_test wp wp localhost latest
```

3. Run all tests:
```bash
composer test
```

## Test Structure

```
tests/
├── bootstrap.php              # Test bootstrap file
├── class-crowdsignal-test-case.php  # Base test case class
├── unit/                      # Unit tests
│   ├── test-polldaddy-main.php
│   ├── test-polldaddy-shortcode.php
│   └── test-sample.php
├── integration/               # Integration tests
│   ├── test-admin-integration.php
│   └── test-frontend-integration.php
├── fixtures/                  # Test data and fixtures
├── mocks/                     # Mock objects and classes
├── data/                      # Test datasets
└── logs/                      # Test output logs
```

## Running Tests

### All Tests
```bash
composer test
```

### Unit Tests Only
```bash
composer test:unit
```

### Integration Tests Only
```bash
composer test:integration
```

### With Coverage Report
```bash
composer test:coverage
```

### Individual Test Files
```bash
vendor/bin/phpunit tests/unit/test-polldaddy-main.php
```

### Specific Test Methods
```bash
vendor/bin/phpunit --filter test_plugin_initialization
```

## Code Quality

### Coding Standards (PHPCS)
```bash
composer phpcs
```

### Auto-fix Coding Standards
```bash
composer phpcs:fix
```

### Static Analysis (PHPStan)
```bash
composer phpstan
```

### Security Checks
```bash
composer security-check
```

### All Quality Checks
```bash
composer lint
```

## Writing Tests

### Test File Naming

- Unit tests: `test-{component-name}.php`
- Integration tests: `test-{component-name}-integration.php`
- Group tests with `@group` annotations

### Example Unit Test

```php
<?php
/**
 * Tests for Example Component
 *
 * @package Crowdsignal_Forms
 */

/**
 * Test class for Example Component.
 *
 * @group example
 * @group unit
 */
class Test_Example_Component extends Crowdsignal_Test_Case {

    /**
     * Test example functionality.
     */
    public function test_example_function() {
        $result = example_function( 'input' );
        $this->assertEquals( 'expected', $result );
    }

    /**
     * Test with data provider.
     *
     * @dataProvider example_data_provider
     */
    public function test_with_data_provider( $input, $expected ) {
        $result = example_function( $input );
        $this->assertEquals( $expected, $result );
    }

    /**
     * Data provider for tests.
     *
     * @return array Test data.
     */
    public function example_data_provider() {
        return array(
            'case 1' => array( 'input1', 'expected1' ),
            'case 2' => array( 'input2', 'expected2' ),
        );
    }
}
```

### Base Test Case Features

The `Crowdsignal_Test_Case` class provides helpful methods:

```php
// Create test users with capabilities
$user_id = $this->create_test_user( array( 'edit_posts' ) );

// Create test polls
$poll = $this->create_test_poll( array(
    'title' => 'Test Poll',
    'answers' => array( 'Yes', 'No' )
) );

// Mock AJAX requests
$this->mock_ajax_request( 'action_name', $data, true );

// Security assertions
$this->assertNonceValid( $nonce, $action );
$this->assertProperlyEscaped( $output );
$this->assertUsesPreparation( $sql_query );
```

## Continuous Integration

### GitHub Actions

The project uses GitHub Actions for automated testing:

- **Multiple PHP versions**: 7.4, 8.0, 8.1, 8.2, 8.3
- **Multiple WordPress versions**: 6.0, 6.1, 6.2, 6.3, latest
- **Multisite testing**: Included for latest versions
- **Code coverage**: Generated and uploaded to Codecov
- **Security scanning**: Automated vulnerability checks

### Workflow Triggers

Tests run automatically on:
- Push to `master` or `develop` branches
- Pull requests to `master` or `develop`
- Manual workflow dispatch

## Test Environment Setup

### Local Development

1. **Using wp-env** (recommended):
```bash
npm install -g @wordpress/env
wp-env start
```

2. **Manual setup**:
```bash
# Download WordPress test suite
bash bin/install-wp-tests.sh wordpress_test wp wp localhost latest

# Run tests
composer test
```

### Environment Variables

```bash
# WordPress test configuration
export WP_TESTS_DIR=/path/to/wordpress-tests-lib
export WP_CORE_DIR=/path/to/wordpress
export WP_TESTS_DOMAIN=example.org
export WP_TESTS_EMAIL=admin@example.org
export WP_TESTS_TITLE="Test Blog"

# Database configuration
export WP_TESTS_DB_NAME=wordpress_test
export WP_TESTS_DB_USER=wp
export WP_TESTS_DB_PASSWORD=wp
export WP_TESTS_DB_HOST=localhost

# Multisite testing
export WP_TESTS_MULTISITE=1
```

## Testing Best Practices

### 1. Test Isolation
- Each test should be independent
- Use `setUp()` and `tearDown()` methods
- Clean up test data after each test

### 2. Security Testing
- Always test nonce verification
- Validate input sanitization
- Check output escaping
- Test user capability requirements

### 3. Data Providers
- Use data providers for testing multiple scenarios
- Name test cases descriptively
- Include edge cases and invalid inputs

### 4. Assertions
- Use specific assertions (`assertEquals` vs `assertTrue`)
- Include meaningful assertion messages
- Test both positive and negative cases

### 5. WordPress Integration
- Test hook registration
- Verify proper use of WordPress APIs
- Check database interactions
- Test multisite compatibility

## Debugging Tests

### Verbose Output
```bash
vendor/bin/phpunit --verbose
```

### Debug Information
```bash
vendor/bin/phpunit --debug
```

### Stop on Failure
```bash
vendor/bin/phpunit --stop-on-failure
```

### Filter by Group
```bash
vendor/bin/phpunit --group=unit
vendor/bin/phpunit --exclude-group=integration
```

## Coverage Reports

### HTML Coverage Report
```bash
composer test:coverage
open coverage/index.html
```

### Text Coverage Summary
```bash
vendor/bin/phpunit --coverage-text
```

### Coverage Requirements

- **Minimum coverage**: 80% for new code
- **Critical paths**: 95% coverage required
- **Public APIs**: 100% coverage required

## Performance Testing

### Benchmarking Tests
```php
public function test_performance() {
    $start_time = microtime( true );

    // Code to test
    expensive_function();

    $end_time = microtime( true );
    $execution_time = $end_time - $start_time;

    $this->assertLessThan( 1.0, $execution_time, 'Function should complete in under 1 second' );
}
```

### Memory Usage Testing
```php
public function test_memory_usage() {
    $start_memory = memory_get_usage();

    // Code to test
    memory_intensive_function();

    $end_memory = memory_get_usage();
    $memory_used = $end_memory - $start_memory;

    $this->assertLessThan( 1024 * 1024, $memory_used, 'Should use less than 1MB' );
}
```

## Contributing

### Before Submitting Tests

1. Run the full test suite: `composer test`
2. Check coding standards: `composer phpcs`
3. Run static analysis: `composer phpstan`
4. Ensure good test coverage for new code
5. Update documentation if needed

### Test Review Checklist

- [ ] Tests follow naming conventions
- [ ] Proper use of assertions
- [ ] Good test coverage
- [ ] Tests are isolated and independent
- [ ] Security considerations addressed
- [ ] Performance implications considered
- [ ] Documentation updated

## Resources

- [WordPress Testing Handbook](https://make.wordpress.org/core/handbook/testing/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)

## Troubleshooting

### Common Issues

1. **Database connection errors**:
   - Check MySQL service is running
   - Verify database credentials
   - Ensure test database exists

2. **WordPress test suite not found**:
   - Run `bash bin/install-wp-tests.sh` again
   - Check `WP_TESTS_DIR` environment variable

3. **Memory errors**:
   - Increase PHP memory limit: `php -d memory_limit=512M vendor/bin/phpunit`

4. **Permission errors**:
   - Check file permissions
   - Ensure test directories are writable

### Getting Help

- Check the [GitHub Issues](https://github.com/automattic/polldaddy-plugin/issues)
- Review WordPress.org plugin support forums
- Consult WordPress testing documentation