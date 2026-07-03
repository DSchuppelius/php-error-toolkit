<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoggerHardeningTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use ERRORToolkit\{ErrorHandler, LoggerRegistry};
use ERRORToolkit\Logger\{ConsoleLogger, FileLogger};
use ERRORToolkit\Traits\ErrorLog;
use PHPUnit\Framework\TestCase;
use Psr\Log\{InvalidArgumentException, LogLevel, LoggerInterface};

class LoggerHardeningTest extends TestCase {
    private string $tempDir;

    protected function setUp(): void {
        LoggerRegistry::resetLogger();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ertk_hardening_' . uniqid();
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void {
        LoggerRegistry::resetLogger();
        foreach (glob($this->tempDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);
    }

    /**
     * @return resource
     */
    private static function createStream() {
        $stream = fopen('php://memory', 'w+');
        assert(is_resource($stream));

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private static function readStream($stream): string {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    // --- A3: PSR-3-Level-Validierung -----------------------------------

    public function test_unknown_level_string_throws_psr_exception(): void {
        $logger = new ConsoleLogger(LogLevel::DEBUG, stream: self::createStream());

        $this->expectException(InvalidArgumentException::class);
        $logger->log('verbose', 'nope');
    }

    public function test_non_string_level_throws_psr_exception(): void {
        $logger = new ConsoleLogger(LogLevel::DEBUG, stream: self::createStream());

        $this->expectException(InvalidArgumentException::class);
        $logger->log(3, 'nope');
    }

    // --- B5: PSR-3-Interpolation ----------------------------------------

    public function test_placeholders_are_interpolated_in_logger(): void {
        $stream = self::createStream();
        $logger = new ConsoleLogger(LogLevel::DEBUG, enableDeduplication: false, stream: $stream);

        $logger->info('User {user} performed {action}', ['user' => 'alice', 'action' => 'login']);

        $output = self::readStream($stream);
        $this->assertStringContainsString('User alice performed login', $output);
        $this->assertStringNotContainsString('{user}', $output);
    }

    public function test_internal_context_keys_are_not_interpolated(): void {
        $stream = self::createStream();
        $logger = new ConsoleLogger(LogLevel::DEBUG, enableDeduplication: false, stream: $stream);

        $logger->info('flag: {_secret}', ['_secret' => 'hidden']);

        $output = self::readStream($stream);
        $this->assertStringContainsString('flag: {_secret}', $output);
    }

    public function test_trait_log_path_interpolates_placeholders(): void {
        $stream = self::createStream();
        $consumer = new class {
            use ErrorLog;
        };
        $consumer::setLogger(new ConsoleLogger(LogLevel::DEBUG, enableDeduplication: false, stream: $stream));

        $consumer->logWarning('Retrying after {delay}s (attempt {attempt})', ['delay' => 5, 'attempt' => 2]);

        $output = self::readStream($stream);
        $this->assertStringContainsString('Retrying after 5s (attempt 2)', $output);
    }

    // --- B7: Caller-Detection-Schalter ------------------------------------

    public function test_caller_detection_can_be_disabled(): void {
        $stream = self::createStream();
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: false, stream: $stream);
        $this->assertTrue($logger->isCallerDetectionEnabled());

        $logger->setCallerDetection(false);
        $logger->info('fast path');

        $output = self::readStream($stream);
        $this->assertStringContainsString('[-]:', $output);
        $this->assertStringNotContainsString(self::class, $output);
    }

    // --- B6: FileLogger-Archiv-Purge --------------------------------------

    public function test_rotation_purges_oldest_archives(): void {
        $logFile = $this->tempDir . DIRECTORY_SEPARATOR . 'app.log';
        $logger = new FileLogger($logFile, LogLevel::DEBUG, failSafe: false, maxFileSize: 10, rotateLogs: true, enableDeduplication: false, maxArchiveFiles: 2);

        // Alt-Archive simulieren (chronologisch aufsteigende Zeitstempel)
        foreach (['20250101_000001', '20250101_000002', '20250101_000003'] as $suffix) {
            file_put_contents($logFile . '.' . $suffix, 'old');
        }
        // .lock-Datei darf vom Purge nie angefasst werden
        file_put_contents($logFile . '.lock', '');

        // Erster Eintrag füllt die Datei über maxFileSize, zweiter rotiert
        $logger->debug('first entry that exceeds the tiny max file size');
        $logger->debug('second entry triggers rotation and purge');

        $archives = array_filter(
            glob($logFile . '.*') ?: [],
            fn (string $file): bool => preg_match('/\.\d{8}_\d{6}$/', $file) === 1
        );

        $this->assertCount(2, $archives, 'Purge muss die ältesten Archive über dem Limit entfernen');
        $this->assertFileDoesNotExist($logFile . '.20250101_000001');
        $this->assertFileExists($logFile . '.lock');
    }

    public function test_max_archive_files_validation(): void {
        $logFile = $this->tempDir . DIRECTORY_SEPARATOR . 'val.log';

        $this->expectException(\InvalidArgumentException::class);
        new FileLogger($logFile, LogLevel::DEBUG, failSafe: false, maxArchiveFiles: 0);
    }

    // --- A2: Shutdown-Guard ------------------------------------------------

    public function test_unregistered_handler_ignores_shutdown(): void {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('log');

        $handler = ErrorHandler::createUnregistered($logger);

        $this->assertFalse($handler->isRegistered());
        $handler->handleShutdown();
    }

    // --- A1: ConsoleLogger-Stream-Handling ---------------------------------

    public function test_console_logger_default_stream_works_in_cli(): void {
        // Ohne Stream-Argument: STDERR (CLI) bzw. php://stderr (Web-SAPI).
        // Level-Filter EMERGENCY verhindert echte Ausgabe im Testlauf.
        $logger = new ConsoleLogger(LogLevel::EMERGENCY);

        $logger->debug('wird gefiltert, darf nicht crashen');
        $this->assertTrue($logger->isCallerDetectionEnabled());
    }
}
