# PHP Error Toolkit

[![PHP Version](https://img.shields.io/badge/PHP-8.1--8.4-blue.svg)](https://www.php.net/)
[![PSR-3 Compliant](https://img.shields.io/badge/PSR--3-Compliant-green.svg)](https://www.php-fig.org/psr/psr-3/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A PSR-3 compliant logging library built for PHP 8.1+ with a focus on console and file logging. Designed as a lightweight, reusable component for modern PHP applications.

## Features

- 🎯 **PSR-3 Compliant** - Full compatibility with PSR-3 logging standards
- 🖥️ **Console Logging** - Colored output with ANSI support and terminal detection
- 📄 **File Logging** - Automatic log rotation and fail-safe mechanisms
- 🏭 **Factory Pattern** - Clean instantiation with singleton behavior
- 🌐 **Global Registry** - Centralized logger management across your application
- ✨ **Magic Methods** - Convenient `logDebug()`, `logInfo()`, `logErrorAndThrow()` methods via trait
- ⏱️ **Timer Logging** - Measure execution time with `logInfoWithTimer()` and similar methods
- 🔄 **Conditional Logging** - `logInfoIf()`, `logErrorUnless()` for conditional log output
- 🔧 **OS Helper** - Cross-platform system detection and utilities
- 🎨 **Cross-Platform** - Windows, Linux and macOS terminal support
- 🧪 **Fully Tested** - Comprehensive test suite with PHPUnit

## Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require dschuppelius/php-error-toolkit
```

## Requirements

- PHP 8.1 to 8.4
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

## ErrorLog Trait Features

The `ErrorLog` trait provides comprehensive logging capabilities with multiple advanced features:

### Magic Methods

All PSR-3 log levels are available as magic methods:

```php
use ERRORToolkit\Traits\ErrorLog;

class MyClass {
    use ErrorLog;

    public function example() {
        // Instance methods - these methods are automatically available
        $this->logDebug('Debug message');
        $this->logInfo('Info message');
        $this->logNotice('Notice message');
        $this->logWarning('Warning message');
        $this->logError('Error message');
        $this->logCritical('Critical message');
        $this->logAlert('Alert message');
        $this->logEmergency('Emergency message');
        
        // All methods support context arrays
        $this->logError('Database error', ['table' => 'users', 'id' => 123]);
    }

    public static function staticExample() {
        // Static methods - all static magic methods are available
        self::logDebug('Static debug message');
        self::logInfo('Static info message');
        self::logNotice('Static notice message');
        self::logWarning('Static warning message');
        self::logError('Static error message');
        self::logCritical('Static critical message');
        self::logAlert('Static alert message');
        self::logEmergency('Static emergency message');
        
        // Static methods also support context
        self::logInfo('User action', ['user' => 'admin', 'action' => 'login']);
    }
}
```

### Conditional Logging

Log messages only when conditions are met:

```php
use ERRORToolkit\Traits\ErrorLog;

class DataProcessor {
    use ErrorLog;

    public function process(array $data, bool $verbose = false) {
        // Log only if condition is true
        $this->logDebugIf($verbose, 'Verbose mode enabled');
        
        // Log only if condition is false
        $this->logWarningUnless(count($data) > 0, 'Empty data received');
        
        // Works with all log levels
        $this->logInfoIf($verbose, 'Processing started', ['count' => count($data)]);
        $this->logErrorUnless($this->validate($data), 'Validation failed');
    }
}
```

### Log with Timer

Measure execution time of operations:

```php
use ERRORToolkit\Traits\ErrorLog;

class PerformanceService {
    use ErrorLog;

    public function heavyOperation() {
        // Execute callback and log duration
        $result = $this->logInfoWithTimer(function() {
            // Heavy processing...
            return $this->processData();
        }, 'Heavy operation');
        // Logs: "Heavy operation (completed in 123.45 ms)"
        
        return $result;
    }

    public function apiCall() {
        // Works with all log levels
        return $this->logDebugWithTimer(function() {
            return file_get_contents('https://api.example.com/data');
        }, 'API request');
    }
}
```

### Log and Return

Log a message and return a value in one call:

```php
use ERRORToolkit\Traits\ErrorLog;

class Calculator {
    use ErrorLog;

    public function calculate(int $value): int {
        $result = $value * 2;
        
        // Log and return in one call
        return $this->logDebugAndReturn($result, 'Calculation complete', ['input' => $value]);
    }
    
    public function findUser(int $id): ?User {
        $user = $this->repository->find($id);
        
        return $user !== null 
            ? $this->logInfoAndReturn($user, 'User found', ['id' => $id])
            : $this->logWarningAndReturn(null, 'User not found', ['id' => $id]);
    }
}
```

### Log Exceptions

Log exceptions with full stack trace:

```php
use ERRORToolkit\Traits\ErrorLog;
use Psr\Log\LogLevel;

class ExceptionHandler {
    use ErrorLog;

    public function handle(\Throwable $e): void {
        // Log exception with full context (file, line, trace)
        self::logException($e);
        
        // With custom log level
        self::logException($e, LogLevel::CRITICAL);
        
        // With additional context
        self::logException($e, LogLevel::ERROR, ['user_id' => 123]);
    }
}
```

### Log and Throw Exceptions

Combine logging and exception throwing in a single call with `log{Level}AndThrow()`:

```php
use ERRORToolkit\Traits\ErrorLog;
use RuntimeException;
use InvalidArgumentException;

class ValidationService {
    use ErrorLog;

    public function validateUser(array $data): void {
        if (empty($data['email'])) {
            // Logs error and throws exception in one call
            $this->logErrorAndThrow(
                InvalidArgumentException::class,
                'Email is required'
            );
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            // With context array
            $this->logWarningAndThrow(
                InvalidArgumentException::class,
                'Invalid email format',
                ['email' => $data['email']]
            );
        }
    }

    public function processPayment(float $amount): void {
        try {
            // Payment processing...
        } catch (\Exception $e) {
            // With exception chaining
            $this->logCriticalAndThrow(
                RuntimeException::class,
                'Payment processing failed',
                ['amount' => $amount],
                $e  // Previous exception for chaining
            );
        }
    }

    public static function validateConfig(array $config): void {
        if (!isset($config['api_key'])) {
            // Static usage
            self::logErrorAndThrow(
                RuntimeException::class,
                'API key not configured'
            );
        }
    }
}
```

**Available log-and-throw methods:**

| Method | Log Level |
| ------ | --------- |
| `logErrorAndThrow()` | ERROR |
| `logCriticalAndThrow()` | CRITICAL |
| `logAlertAndThrow()` | ALERT |
| `logEmergencyAndThrow()` | EMERGENCY |

**Method signature:**

```php
log{Level}AndThrow(
    string $exceptionClass,    // Exception class to throw (e.g., RuntimeException::class)
    string $message,           // Error message (used for both log and exception)
    array $context = [],       // Optional: Context array for logging
    ?Throwable $previous = null // Optional: Previous exception for chaining
): never
```

### Logger Management

The trait provides flexible logger management:

```php
use ERRORToolkit\Traits\ErrorLog;
use ERRORToolkit\Factories\FileLoggerFactory;

class MyService {
    use ErrorLog;
    
    public function __construct() {
        // Set a specific logger for this class
        self::setLogger(FileLoggerFactory::getLogger('service.log'));
        
        // Or use global logger from registry (automatic fallback)
        self::setLogger(); // Uses LoggerRegistry::getLogger()
    }
}
```

### Automatic Project Detection

The trait automatically detects project names from class namespaces:

```php
namespace MyCompany\ProjectName\Services;

use ERRORToolkit\Traits\ErrorLog;

class UserService {
    use ErrorLog;
    
    public function process() {
        // Project name "MyCompany" is automatically detected
        // Log entry: [2025-12-29 10:30:00] info [MyCompany::UserService::process()]: Processing user
        $this->logInfo('Processing user');
    }
}
```

### Fallback Logging System

When no logger is available, the trait provides intelligent fallbacks:

1. **Primary**: Uses configured PSR-3 logger
2. **Fallback 1**: PHP error_log() if configured
3. **Fallback 2**: System syslog with project-specific facility
4. **Fallback 3**: File logging to system temp directory

```php
class EmergencyService {
    use ErrorLog;
    
    public function criticalOperation() {
        // Even without explicit logger setup, this will work
        // Falls back through: error_log → syslog → temp file
        self::logEmergency('System failure detected');
    }
}
```

### Context Support

All logging methods support PSR-3 context arrays:

```php
class ApiService {
    use ErrorLog;
    
    public function handleRequest($request) {
        $context = [
            'method' => $request->method,
            'url' => $request->url,
            'user_id' => $request->user?->id,
            'timestamp' => time()
        ];
        
        $this->logInfo('API request received', $context);
        
        try {
            // Process request
        } catch (Exception $e) {
            $this->logError('API request failed', [
                ...$context,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
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

## Helper Classes

### OsHelper

Cross-platform operating system detection and utilities:

```php
use ERRORToolkit\Helper\OsHelper;

// OS Detection
OsHelper::isWindows();    // true on Windows
OsHelper::isLinux();      // true on Linux
OsHelper::isMacOS();      // true on macOS
OsHelper::isUnix();       // true on Linux or macOS
OsHelper::getOsName();    // 'Windows', 'Linux', 'macOS'

// Path Utilities
OsHelper::getPathSeparator();     // '\' on Windows, '/' on Unix
OsHelper::getEnvPathSeparator();  // ';' on Windows, ':' on Unix
OsHelper::getHomeDirectory();     // User's home directory
OsHelper::getTempDirectory();     // System temp directory

// Executable Utilities
OsHelper::isExecutable('/path/to/file');  // Check if file is executable
OsHelper::findExecutable('git');          // Find executable in PATH

// User Information
OsHelper::getCurrentUsername();   // Current user name
OsHelper::getCurrentUserId();     // UID (Unix only)
OsHelper::isPrivilegedUser();     // Check for root/admin

// System Information
OsHelper::getCpuCoreCount();      // Number of CPU cores
OsHelper::getArchitecture();      // System architecture (x86_64, arm64, etc.)
OsHelper::getKernelVersion();     // Kernel version
OsHelper::getSystemInfo();        // Complete system info array

// Environment Variables
OsHelper::getEnv('HOME', '/default');  // Get env var with default
OsHelper::setEnv('MY_VAR', 'value');   // Set env var
```

### TerminalHelper

Terminal detection and capabilities:

```php
use ERRORToolkit\Helper\TerminalHelper;

TerminalHelper::isTerminal();      // Check if running in terminal
TerminalHelper::isDebugConsole();  // Check if running in debug console
TerminalHelper::getCursorColumn(); // Get current cursor position
```

### PhpUnitHelper

PHPUnit-specific utilities:

```php
use ERRORToolkit\Helper\PhpUnitHelper;

PhpUnitHelper::isRunningInPhpunit();  // Check if running in PHPUnit
PhpUnitHelper::supportsColors();      // Check PHPUnit color configuration
```

## Architecture

- **Factory Pattern** - All loggers created via factories with singleton behavior
- **Strategy Pattern** - Different logging strategies (Console, File, Null)
- **Registry Pattern** - Global logger management
- **Trait-based** - Easy integration via `ErrorLog` trait
- **Helper Classes** - Reusable utilities for OS detection, terminal handling, PHPUnit support
- **PSR-3 Compliant** - Standard logging interface

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Author

Daniel Jörg Schuppelius

- Website: [schuppelius.org](https://schuppelius.org)
- Email: <info@schuppelius.org>

## Contributing

This is a personal toolkit for Daniel Schuppelius's projects. For bugs or feature requests, please open an issue.