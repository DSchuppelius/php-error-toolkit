# PHP Error Toolkit

[![PHP Version](https://img.shields.io/badge/PHP-8.1--8.5-blue.svg)](https://www.php.net/)
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
- 🔌 **Laravel Bridge** - auto-discovered `ErrorToolkitServiceProvider` routes toolkit logs into a Laravel log channel (`ERROR_TOOLKIT_LOG_CHANNEL`, see `config/error-toolkit.php`)
- 🧩 **Custom Resolver** - `LoggerRegistry::setLoggerResolver()` injects a closure that resolves the logger lazily, for hosts that build it from their own container
- 🗄️ **Archive Limit** - `FileLogger` keeps at most `maxArchiveFiles` rotated files (default 10; `null` keeps all)

## Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require dschuppelius/php-error-toolkit
```

## Requirements

- PHP 8.1 to 8.5
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

        // Optional: Nachricht zusätzlich als Hex-Bytes ausgeben
        $this->logInfoHex('Payload: äöü');
        
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

        // Auch statisch mit zusätzlicher Hex-Ausgabe
        self::logDebugHex('RAW');
    }
}
```

### Hex Output for String Messages

For debugging encoding or binary transport issues, you can log a message with an additional hex representation:

```php
$this->logInfoHex('ABC');
// Output contains three lines:
// [timestamp] info [caller]: ABC
// [timestamp] info [caller] [str]: A  B  C
// [timestamp] info [caller] [hex]: 41 42 43
```

Jedes Zeichen steht genau über seinem Hex-Code. Für Multibyte-Zeichen (z. B. `ä` → `C3 A4`) wird die Zeichenspalte entsprechend verbreitert:

```
// [str]: ä     B
// [hex]: C3 A4 42
```

You can also enable it on direct PSR-3 logger calls via context flag:

```php
use ERRORToolkit\Contracts\Abstracts\LoggerAbstract;

$logger->info('ABC', [LoggerAbstract::CONTEXT_KEY_MESSAGE_HEX => true]);
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
            $this->logErrorAndThrow(
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
    ?Throwable $previous = null, // Optional: Previous exception for chaining
    int $code = 0              // Optional: Exception code
): never
```

There is no `logWarningAndThrow()` (or any level below ERROR): calling it throws a `BadMethodCallException`.

### Logger Management

The trait provides flexible logger management:

```php
use ERRORToolkit\Traits\ErrorLog;
use ERRORToolkit\Factories\FileLoggerFactory;

class MyService {
    use ErrorLog;
    
    public function __construct() {
        // Set a logger - note: this also registers it globally (LoggerRegistry::setLogger())
        self::setLogger(FileLoggerFactory::getLogger('service.log'));
        
        // Or use global logger from registry (automatic fallback)
        self::setLogger(); // Uses LoggerRegistry::getLogger()
    }
}
```

`FileLoggerFactory::getLogger()` is a process-wide singleton ("configure once"): only the first call configures
the file, later calls return the same logger and ignore their arguments. For a separate file, call
`FileLoggerFactory::resetLogger()` first or create `new FileLogger(...)` directly.

### Automatic Project Detection

The trait derives a project name from the first namespace segment of the calling class. It is only used as the
syslog ident and for the name of the fallback temp file (see below); log entries name the full calling class:

```php
namespace MyCompany\ProjectName\Services;

use ERRORToolkit\Traits\ErrorLog;

class UserService {
    use ErrorLog;
    
    public function process() {
        // Project name "MyCompany" (syslog ident / fallback file)
        // Log entry: [2025-12-29 10:30:00] info [MyCompany\ProjectName\Services\UserService::process()]: Processing user
        // At logger level DEBUG the caller also carries " in <file>:<line>".
        $this->logInfo('Processing user');
    }
}
```

### Fallback Logging System

When no logger is available, the trait provides intelligent fallbacks. Without a logger only NOTICE and more
severe messages are written; DEBUG and INFO are dropped.

1. **Primary**: Uses configured PSR-3 logger
2. **Fallback 1**: PHP error_log() if the `error_log` ini setting is set
3. **Fallback 2**: STDERR on the command line (CLI)
4. **Fallback 3**: System syslog (ident = project name)
5. **Fallback 4**: File `php-error-toolkit-<hash>.log` in the system temp directory (mode 0600)

```php
class EmergencyService {
    use ErrorLog;
    
    public function criticalOperation() {
        // Even without explicit logger setup, this will work
        // Falls back through: error_log → STDERR (CLI) → syslog → temp file
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
use Psr\Log\LogLevel;

$logger = new FileLogger(
    logFile: '/var/log/app.log',
    logLevel: LogLevel::INFO,
    failSafe: true,           // Fallback to the temp directory if the log directory is not writable
    maxFileSize: 5000000,     // 5MB before rotation
    rotateLogs: true,         // Archive as <file>.YYYYmmdd_HHMMSS when rotating
    maxArchiveFiles: 10       // Keep at most 10 archives (null = unlimited)
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
OsHelper::getCpuCoreCount();      // Usable CPU cores (Linux: like nproc, read from /proc - no program started)
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

TerminalHelper::isTerminal();         // Check if running in terminal
TerminalHelper::isDebugConsole();     // Check if running in debug console
TerminalHelper::getCursorColumn();    // Get current cursor position
TerminalHelper::getTerminalWidth();   // COLUMNS, tput cols or stty size; 80 as fallback
TerminalHelper::getTerminalHeight();  // LINES, tput lines or stty size; 24 as fallback
```

### ProcessHelper

Starts short helper programs (`stty`, `tput`, `sysctl`, `nproc`) **without a shell and with a time limit**.
The toolkit uses it internally instead of `shell_exec()`/`system()`; it is public for packages that, like this
one, cannot depend on `php-common-toolkit` (whose `Shell` class is the tool of choice everywhere else):

```php
use ERRORToolkit\Helper\ProcessHelper;

ProcessHelper::canStartProcesses();                       // false if proc_open() is disabled
ProcessHelper::output(['tput', 'cols'], inheritStdin: true); // stdout on exit code 0, otherwise null
ProcessHelper::run(['sysctl', '-n', 'hw.ncpu'], timeout: 2.0);
// ['exitCode' => 0, 'output' => "8\n", 'timedOut' => false], or null if the program does not start
```

- Program and arguments are passed as a list (`proc_open()` without `/bin/sh` or `cmd.exe`): no quoting, no
  injection through arguments.
- stdout is read with a deadline (default 5 s, at most 1 MiB is kept); on expiry the program gets SIGTERM and,
  after one second, SIGKILL. Meant for programs without child processes of their own.
- stderr is discarded; stdin is empty unless `inheritStdin: true`.

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

## Versions

### 2.8.2 — Missing program is null on PHP 8.2 as well

- `ProcessHelper::run()` returns `null` for a program that does not exist on every supported PHP version. Before
  PHP 8.3, `proc_open()` starts via fork/exec and a missing program surfaced as exit code 127 with no output.

### 2.8.1 — No shell, time limit for helper programs

Same code as the Git tag v2.8.0. Packagist still lists an orphaned `v2.8` entry (it points to the v2.7.1
commit) and therefore skipped v2.8.0, which normalizes to the same version. Require `^2.8.1`.


- New `ERRORToolkit\Helper\ProcessHelper`: runs a program from an argument list without a shell, with a time
  limit (SIGTERM, then SIGKILL).
- `OsHelper::getCpuCoreCount()` no longer runs `nproc` through `shell_exec()`: on Linux it reads
  `/proc/self/status` (`Cpus_allowed_list`, like `nproc`, including `OMP_NUM_THREADS`/`OMP_THREAD_LIMIT`), then
  `/sys/devices/system/cpu/online` and `/proc/cpuinfo`; Windows reads `NUMBER_OF_PROCESSORS`; other systems use
  `posix_sysconf()`. Only as a last resort (e.g. under `open_basedir`) does it start `nproc` or `sysctl` via
  `ProcessHelper`. It now also works where `shell_exec()` is disabled.
- `TerminalHelper` runs `stty`, `tput` and `mode` via `ProcessHelper` instead of `shell_exec()`/`system()`; the
  saved terminal settings go to `stty` as one argument and are restored on every path.
- `OsHelper::canExecuteShellCommands()` stays, but the toolkit itself no longer needs a shell.

Releases are tagged in Git; `git tag --sort=-v:refname` lists them (latest: v2.8.2). There is no separate changelog file — the tags and the commit history are the record.
