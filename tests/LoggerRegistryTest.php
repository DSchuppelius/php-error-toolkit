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

    public function test_resolver_is_invoked_lazily_and_on_every_call(): void {
        $calls = 0;
        $logger = new NullLogger;
        LoggerRegistry::setLoggerResolver(function () use (&$calls, $logger) {
            $calls++;

            return $logger;
        });

        $this->assertSame(0, $calls, 'Resolver must not run before the first getLogger() call');
        $this->assertSame($logger, LoggerRegistry::getLogger());
        $this->assertSame($logger, LoggerRegistry::getLogger());
        // Resolver results are deliberately NOT cached: re-resolving on every
        // call keeps the logger bound to the CURRENT container, so a flushed
        // application (a plain PHPUnit test after a feature test in the same
        // process, an Octane worker, a queue restart) never yields a stale,
        // crashing logger. An explicitly set logger (setLogger) is still cached.
        $this->assertSame(2, $calls, 'Resolver result must NOT be cached (re-resolved each call)');
    }

    public function test_throwing_resolver_fails_soft_instead_of_crashing_logging(): void {
        LoggerRegistry::setLoggerResolver(function (): never {
            // Simulates a resolver whose captured/current container was flushed
            // ("Class 'log' does not exist" / "Target class [config]").
            throw new \RuntimeException('container flushed');
        });

        // Logging must never take the process down: getLogger() swallows the
        // resolver failure and returns null (ErrorLog then falls back).
        $this->assertNull(LoggerRegistry::getLogger());
        $this->assertFalse(LoggerRegistry::hasLogger());
    }

    public function test_resolver_recovers_after_transient_failure(): void {
        $alive = false;
        $logger = new NullLogger;
        LoggerRegistry::setLoggerResolver(function () use (&$alive, $logger): ?\Psr\Log\LoggerInterface {
            if (!$alive) {
                throw new \RuntimeException('container not ready');
            }

            return $logger;
        });

        // While "dead": fail soft.
        $this->assertNull(LoggerRegistry::getLogger());
        // Once the container is back (e.g. a fresh app booted): re-resolves to
        // the live logger — a cached failure would have frozen it at null.
        $alive = true;
        $this->assertSame($logger, LoggerRegistry::getLogger());
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
