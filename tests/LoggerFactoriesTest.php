<?php
/*
 * Created on   : Thu Apr 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoggerFactoriesTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ERRORToolkit\Factories\ConsoleLoggerFactory;
use ERRORToolkit\Factories\FileLoggerFactory;
use ERRORToolkit\Factories\NullLoggerFactory;
use ERRORToolkit\Logger\ConsoleLogger;
use ERRORToolkit\Logger\FileLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class LoggerFactoriesTest extends TestCase {
    protected function tearDown(): void {
        ConsoleLoggerFactory::resetLogger();
        FileLoggerFactory::resetLogger();
        NullLoggerFactory::resetLogger();
    }

    // ========================================================================
    // NullLoggerFactory
    // ========================================================================

    public function testNullLoggerFactoryReturnsNullLogger(): void {
        $logger = NullLoggerFactory::getLogger();
        $this->assertInstanceOf(NullLogger::class, $logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testNullLoggerFactoryReturnsSameInstance(): void {
        $first = NullLoggerFactory::getLogger();
        $second = NullLoggerFactory::getLogger();
        $this->assertSame($first, $second);
    }

    public function testNullLoggerFactoryResetCreatesNewInstance(): void {
        $first = NullLoggerFactory::getLogger();
        NullLoggerFactory::resetLogger();
        $second = NullLoggerFactory::getLogger();
        $this->assertNotSame($first, $second);
    }

    // ========================================================================
    // ConsoleLoggerFactory
    // ========================================================================

    public function testConsoleLoggerFactoryReturnsConsoleLogger(): void {
        $logger = ConsoleLoggerFactory::getLogger();
        $this->assertInstanceOf(ConsoleLogger::class, $logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testConsoleLoggerFactoryReturnsSameInstance(): void {
        $first = ConsoleLoggerFactory::getLogger();
        $second = ConsoleLoggerFactory::getLogger();
        $this->assertSame($first, $second);
    }

    public function testConsoleLoggerFactoryResetCreatesNewInstance(): void {
        $first = ConsoleLoggerFactory::getLogger();
        ConsoleLoggerFactory::resetLogger();
        $second = ConsoleLoggerFactory::getLogger();
        $this->assertNotSame($first, $second);
    }

    // ========================================================================
    // FileLoggerFactory
    // ========================================================================

    public function testFileLoggerFactoryReturnsFileLogger(): void {
        $logFile = sys_get_temp_dir() . '/test_factory_' . uniqid() . '.log';
        $logger = FileLoggerFactory::getLogger($logFile);
        $this->assertInstanceOf(FileLogger::class, $logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);

        @unlink($logFile);
    }

    public function testFileLoggerFactoryReturnsSameInstance(): void {
        $logFile = sys_get_temp_dir() . '/test_factory_' . uniqid() . '.log';
        $first = FileLoggerFactory::getLogger($logFile);
        $second = FileLoggerFactory::getLogger();
        $this->assertSame($first, $second);

        @unlink($logFile);
    }

    public function testFileLoggerFactoryResetCreatesNewInstance(): void {
        $logFile1 = sys_get_temp_dir() . '/test_factory_' . uniqid() . '.log';
        $logFile2 = sys_get_temp_dir() . '/test_factory_' . uniqid() . '.log';

        $first = FileLoggerFactory::getLogger($logFile1);
        FileLoggerFactory::resetLogger();
        $second = FileLoggerFactory::getLogger($logFile2);
        $this->assertNotSame($first, $second);

        @unlink($logFile1);
        @unlink($logFile2);
    }
}
