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

use PHPUnit\Framework\TestCase;
use ERRORToolkit\LoggerRegistry;
use ERRORToolkit\Logger\ConsoleLogger;
use Psr\Log\NullLogger;

class LoggerRegistryTest extends TestCase {
    protected function setUp(): void {
        LoggerRegistry::resetLogger();
    }

    protected function tearDown(): void {
        LoggerRegistry::resetLogger();
    }

    public function testInitialStateIsNull(): void {
        $this->assertNull(LoggerRegistry::getLogger());
        $this->assertFalse(LoggerRegistry::hasLogger());
    }

    public function testSetAndGetLogger(): void {
        $logger = new NullLogger();
        LoggerRegistry::setLogger($logger);

        $this->assertSame($logger, LoggerRegistry::getLogger());
        $this->assertTrue(LoggerRegistry::hasLogger());
    }

    public function testResetLogger(): void {
        LoggerRegistry::setLogger(new NullLogger());
        $this->assertTrue(LoggerRegistry::hasLogger());

        LoggerRegistry::resetLogger();
        $this->assertNull(LoggerRegistry::getLogger());
        $this->assertFalse(LoggerRegistry::hasLogger());
    }

    public function testOverwriteLogger(): void {
        $first = new NullLogger();
        $second = new ConsoleLogger();

        LoggerRegistry::setLogger($first);
        $this->assertSame($first, LoggerRegistry::getLogger());

        LoggerRegistry::setLogger($second);
        $this->assertSame($second, LoggerRegistry::getLogger());
    }
}
