# PHP Error Toolkit

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![PSR-3 Compliant](https://img.shields.io/badge/PSR--3-Compliant-green.svg)](https://www.php-fig.org/psr/psr-3/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A PSR-3 compliant logging library built for PHP 8.2+ with a focus on console and file logging. Designed as a lightweight, reusable component for modern PHP applications.

## Features

- 🎯 **PSR-3 Compliant** - Full compatibility with PSR-3 logging standards
- 🖥️ **Console Logging** - Colored output with ANSI support and terminal detection
- 📄 **File Logging** - Automatic log rotation and fail-safe mechanisms
- 🏭 **Factory Pattern** - Clean instantiation with singleton behavior
- 🌐 **Global Registry** - Centralized logger management across your application
- ✨ **Magic Methods** - Convenient `logDebug()`, `logInfo()`, `logError()` methods via trait
- 🎨 **Cross-Platform** - Windows and Unix/Linux terminal support
- 🧪 **Fully Tested** - Comprehensive test suite with PHPUnit

## Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require dschuppelius/php-error-toolkit
```

## Requirements

- PHP 8.1 or higher
- PSR-3 Log Interface

## Quick Start

### Basic Usage with Console Logger

```php
use ERRORToolkit\Factories\ConsoleLoggerFactory;

// Create a console logger
$logger = ConsoleLoggerFactory::getLogger();

// Log messages with different levels
$logger->info('Application started');
$logger->warning('This is a warning message');
$logger->error('An error occurred');
```

### File Logging

```php
use ERRORToolkit\Factories\FileLoggerFactory;

// Create a file logger
$logger = FileLoggerFactory::getLogger('/path/to/logfile.log');

// Log with context
$logger->error('Database connection failed', [
    'host' => 'localhost',
    'port' => 3306,
    'error' => 'Connection timeout'
]);
```

### Using the ErrorLog Trait

Add logging capabilities to any class:

```php
use ERRORToolkit\Traits\ErrorLog;
use ERRORToolkit\Factories\ConsoleLoggerFactory;

class MyService {
    use ErrorLog;
    
    public function __construct() {
        // Set up logging
        self::setLogger(ConsoleLoggerFactory::getLogger());
    }
    
    public function doSomething() {
        $this->logInfo('Starting operation');
        
        try {
            // Your code here
            $this->logDebug('Operation completed successfully');
        } catch (Exception $e) {
            $this->logError('Operation failed: ' . $e->getMessage());
        }
    }
}
```

## Logger Types

### Console Logger
- Colored output with level-specific colors
- Automatic terminal detection
- Debug console support (VS Code, etc.)
- Clean formatting with newline management

### File Logger
- Automatic log rotation when size limit exceeded
- Fail-safe fallback to console/syslog
- Customizable file size limits
- Thread-safe file operations

### Null Logger
- Silent logger for testing/production environments
- PSR-3 compliant no-op implementation

## Global Logger Registry

Manage loggers globally across your application:

```php
use ERRORToolkit\LoggerRegistry;
use ERRORToolkit\Factories\FileLoggerFactory;

// Set a global logger
LoggerRegistry::setLogger(FileLoggerFactory::getLogger('app.log'));

// Use it anywhere in your application
if (LoggerRegistry::hasLogger()) {
    $logger = LoggerRegistry::getLogger();
    $logger->info('Using global logger');
}

// Reset when needed
LoggerRegistry::resetLogger();
```

## Log Levels

Supports all PSR-3 log levels with integer-based filtering:

- `EMERGENCY` (0) - System is unusable
- `ALERT` (1) - Action must be taken immediately
- `CRITICAL` (2) - Critical conditions
- `ERROR` (3) - Error conditions
- `WARNING` (4) - Warning conditions
- `NOTICE` (5) - Normal but significant condition
- `INFO` (6) - Informational messages
- `DEBUG` (7) - Debug-level messages

## Magic Methods via Trait

The `ErrorLog` trait provides convenient magic methods:

```php
use ERRORToolkit\Traits\ErrorLog;

class MyClass {
    use ErrorLog;

    public function example() {
        // These methods are automatically available
        $this->logDebug('Debug message');
        $this->logInfo('Info message');
        $this->logNotice('Notice message');
        $this->logWarning('Warning message');
        $this->logError('Error message');
        $this->logCritical('Critical message');
        $this->logAlert('Alert message');
        $this->logEmergency('Emergency message');
    }

    public static function staticExample() {
        // All static magic methods are available
        self::logDebug('Static debug message');
        self::logInfo('Static info message');
        self::logNotice('Static notice message');
        self::logWarning('Static warning message');
        self::logError('Static error message');
        self::logCritical('Static critical message');
        self::logAlert('Static alert message');
        self::logEmergency('Static emergency message');
    }
}
```

## Configuration

### Log Level Filtering

```php
use ERRORToolkit\Logger\ConsoleLogger;
use Psr\Log\LogLevel;

// Only log warnings and above
$logger = new ConsoleLogger(LogLevel::WARNING);
$logger->info('This will be ignored');
$logger->warning('This will be logged');
```

### File Logger Options

```php
use ERRORToolkit\Logger\FileLogger;

$logger = new FileLogger(
    logFile: '/var/log/app.log',
    logLevel: LogLevel::INFO,
    failSafe: true,           // Fallback to console/syslog on file errors
    maxFileSize: 5000000,     // 5MB before rotation
    rotateLogs: true          // Create .old backup when rotating
);
```

## Testing

Run the test suite:

```bash
composer test
```

Or run PHPUnit directly:

```bash
vendor/bin/phpunit
```

## Cross-Platform Terminal Support

The toolkit automatically detects:
- Windows VT100 support via `sapi_windows_vt100_support()`
- Unix/Linux TTY via `posix_isatty()`
- Debug consoles (VS Code, PHPStorm, etc.)
- PHPUnit color configuration

## Architecture

- **Factory Pattern** - All loggers created via factories with singleton behavior
- **Strategy Pattern** - Different logging strategies (Console, File, Null)
- **Registry Pattern** - Global logger management
- **Trait-based** - Easy integration via `ErrorLog` trait
- **PSR-3 Compliant** - Standard logging interface

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Author

**Daniel Jörg Schuppelius**
- Website: [schuppelius.org](https://schuppelius.org)
- Email: info@schuppelius.org

## Contributing

This is a personal toolkit for Daniel Schuppelius's projects. For bugs or feature requests, please open an issue.