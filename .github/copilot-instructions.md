# PHP Error Toolkit - AI Coding Agent Instructions

## Project Overview
This is a PSR-3 compliant logging library built for PHP 8.2+ with a focus on console and file logging, designed as a reusable component for Daniel Schuppelius's PHP projects.

## Architecture Patterns

### Factory Pattern with Singleton Behavior
- All logger factories implement `LoggerFactoryInterface` and use static singleton pattern
- **FileLoggerFactory**: `getLogger(?string $logfile = null)` - non-standard signature allows file path parameter  
- **ConsoleLoggerFactory**: Standard `getLogger()` - creates colored console output
- **NullLoggerFactory**: For testing/production where logging should be suppressed

### Global Logger Registry
- `LoggerRegistry` provides global access via static methods: `setLogger()`, `getLogger()`, `hasLogger()`, `resetLogger()`
- Used by the `ErrorLog` trait for project-wide logging consistency

### ErrorLog Trait Magic Methods
- Provides magic methods `logDebug()`, `logInfo()`, `logWarning()`, etc. that map to PSR-3 log levels
- Automatically detects project name from namespace (excludes 'ERRORToolkit' namespace)
- Include in any class needing logging: `use ERRORToolkit\Traits\ErrorLog;`

### Enum-Based Configuration
- `LogType` enum defines supported logger types: `CONSOLE`, `FILE`, `NULL`
- Use `LogType::fromString()` for string-to-enum conversion with validation

## Code Conventions

### Strict Typing & Headers
- All files use `declare(strict_types=1);`
- Consistent file headers with creation date, author (Daniel Jörg Schuppelius), and MIT license
- Namespace follows `ERRORToolkit\{Component}` pattern

### Console Output Handling
- `ConsoleLogger` uses ANSI color codes with level-specific colors (red=error, green=info, etc.)
- `TerminalHelper::isTerminal()` detects if running in interactive terminal vs. script
- `TerminalHelper::getCursorColumn()` handles cursor positioning for clean output formatting
- Output includes newline management to prevent formatting issues

### Exception Hierarchy
- Organized under `Exceptions\` with domain-specific folders (e.g., `FileSystem\`)
- Custom exceptions extend standard PHP exceptions for specific error scenarios

## Development Workflow

### Testing
- Run tests: `composer test` or `vendor/bin/phpunit`
- PHPUnit config excludes `tests/Contracts` directory
- Tests verify color codes in console output and log level filtering
- Test output buffering pattern: `ob_start()` → trigger log → `ob_get_clean()` → assertions

### Dependencies
- Requires `psr/log ^3.0@dev` for PSR-3 compliance  
- Uses `phpunit/phpunit ^11.3@dev` for testing
- No runtime dependencies beyond PSR-3

### File Structure Logic
- `src/Contracts/` - Interfaces and abstract classes
- `src/Factories/` - Logger factory implementations
- `src/Logger/` - Concrete logger implementations
- `src/Enums/` - Type-safe enumerations
- `src/Exceptions/` - Custom exception classes with subfolder organization
- `src/Helper/` - Utility classes (TerminalHelper)
- `src/Traits/` - Reusable functionality (ErrorLog)

## Key Integration Points

### PSR-3 Compliance
- All loggers extend `LoggerAbstract` which implements `LoggerInterface`
- Log level filtering built into abstract base with integer-based level comparison
- Context array support for structured logging

### Cross-Platform Terminal Detection
- Windows: Uses `sapi_windows_vt100_support()` for color support detection
- Unix/Linux: Uses `posix_isatty()` for terminal detection
- Graceful fallback to plain text when colors unsupported

When working with this codebase:
- Use factories to create loggers, never instantiate directly in production code
- Leverage `ErrorLog` trait for consistent logging across application classes  
- Test console output using PHPUnit's output buffering
- Follow the established exception naming and organization patterns
- Respect the namespace exclusion logic in `ErrorLog::detectProjectName()`