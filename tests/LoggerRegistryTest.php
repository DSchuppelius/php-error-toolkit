<?php
/*
 * Created on   : Thu Apr 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoggerRegistryTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use ERRORToolkit\Logger\ConsoleLogger;
use ERRORToolkit\LoggerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LoggerRegistryTest extends TestCase {
    protected function setUp(): void {
        LoggerRegistry::resetLogger();
    }

    protected function tearDown(): void {
        LoggerRegistry::resetLogger();
    }

    public function test_initial_state_is_null(): void {
        $this->assertNull(LoggerRegistry::getLogger());
        $this->assertFalse(LoggerRegistry::hasLogger());
    }

    public function test_set_and_get_logger(): void {
        $logger = new NullLogger;
        LoggerRegistry::setLogger($logger);

        $this->assertSame($logger, LoggerRegistry::getLogger());
        $this->assertTrue(LoggerRegistry::hasLogger());
    }

    public function test_reset_logger(): void {
        LoggerRegistry::setLogger(new NullLogger);
        $this->assertTrue(LoggerRegistry::hasLogger());

        LoggerRegistry::resetLogger();
        $this->assertNull(LoggerRegistry::getLogger());
        $this->assertFalse(LoggerRegistry::hasLogger());
    }

    public function test_overwrite_logger(): void {
        $first = new NullLogger;
        $second = new ConsoleLogger;

        LoggerRegistry::setLogger($first);
        $this->assertSame($first, LoggerRegistry::getLogger());

        LoggerRegistry::setLogger($second);
        $this->assertSame($second, LoggerRegistry::getLogger());
    }

    public function test_resolver_is_invoked_lazily_and_only_once(): void {
        $calls = 0;
        $logger = new NullLogger;
        LoggerRegistry::setLoggerResolver(function () use (&$calls, $logger) {
            $calls++;

            return $logger;
        });

        $this->assertSame(0, $calls, 'Resolver must not run before the first getLogger() call');
        $this->assertSame($logger, LoggerRegistry::getLogger());
        $this->assertSame($logger, LoggerRegistry::getLogger());
        $this->assertSame(1, $calls, 'Resolved logger must be cached');
    }

    public function test_explicit_logger_wins_over_resolver(): void {
        $resolved = new NullLogger;
        $explicit = new ConsoleLogger;
        LoggerRegistry::setLoggerResolver(fn () => $resolved);
        LoggerRegistry::setLogger($explicit);

        $this->assertSame($explicit, LoggerRegistry::getLogger());
    }

    public function test_resolver_returning_null_keeps_registry_empty(): void {
        LoggerRegistry::setLoggerResolver(fn () => null);

        $this->assertNull(LoggerRegistry::getLogger());
        $this->assertFalse(LoggerRegistry::hasLogger());
    }

    public function test_has_logger_triggers_resolution(): void {
        LoggerRegistry::setLoggerResolver(fn () => new NullLogger);

        $this->assertTrue(LoggerRegistry::hasLogger());
    }

    public function test_reset_clears_resolver(): void {
        LoggerRegistry::setLoggerResolver(fn () => new NullLogger);
        LoggerRegistry::resetLogger();

        $this->assertNull(LoggerRegistry::getLogger());
        $this->assertFalse(LoggerRegistry::hasLogger());
    }
}
