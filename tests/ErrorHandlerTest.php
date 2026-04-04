<?php
/*
 * Created on   : Fri Apr 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ErrorHandlerTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use ErrorException;
use ERRORToolkit\ErrorHandler;
use ERRORToolkit\LoggerRegistry;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

class ErrorHandlerTest extends TestCase {
    private int $originalErrorReporting;

    protected function setUp(): void {
        // PHPUnit 11.5 setzt error_reporting auf 245 (nur Core/Compile),
        // was Warnings/Notices/Deprecations ausschließt. Für direkte
        // handleError()-Aufrufe brauchen wir die reale E_ALL-Konfiguration.
        $this->originalErrorReporting = error_reporting(E_ALL);
    }

    protected function tearDown(): void {
        error_reporting($this->originalErrorReporting);
        LoggerRegistry::resetLogger();
    }

    // ─── createUnregistered ───────────────────────────────────────

    public function testCreateUnregisteredReturnsInstance(): void {
        $handler = ErrorHandler::createUnregistered(new NullLogger());
        $this->assertInstanceOf(ErrorHandler::class, $handler);
        $this->assertFalse($handler->isRegistered());
    }

    // ─── handleError: Logging ─────────────────────────────────────

    public function testWarningIsLoggedAsWarning(): void {
        $logger = $this->createMockLogger(LogLevel::WARNING, '[E_WARNING]');
        $handler = ErrorHandler::createUnregistered($logger);

        $handler->handleError(E_WARNING, 'Test warning', __FILE__, __LINE__);
    }

    public function testNoticeIsLoggedAsNotice(): void {
        $logger = $this->createMockLogger(LogLevel::NOTICE, '[E_NOTICE]');
        $handler = ErrorHandler::createUnregistered($logger);

        $handler->handleError(E_NOTICE, 'Test notice', __FILE__, __LINE__);
    }

    public function testDeprecatedIsLoggedAsNotice(): void {
        $logger = $this->createMockLogger(LogLevel::NOTICE, '[E_DEPRECATED]');
        $handler = ErrorHandler::createUnregistered($logger);

        $handler->handleError(E_DEPRECATED, 'Deprecated feature', __FILE__, __LINE__);
    }

    public function testUserWarningIsLoggedAsWarning(): void {
        $logger = $this->createMockLogger(LogLevel::WARNING, '[E_USER_WARNING]');
        $handler = ErrorHandler::createUnregistered($logger);

        $handler->handleError(E_USER_WARNING, 'User warning', __FILE__, __LINE__);
    }

    public function testUserNoticeIsLoggedAsNotice(): void {
        $logger = $this->createMockLogger(LogLevel::NOTICE, '[E_USER_NOTICE]');
        $handler = ErrorHandler::createUnregistered($logger);

        $handler->handleError(E_USER_NOTICE, 'User notice', __FILE__, __LINE__);
    }

    public function testUserDeprecatedIsLoggedAsNotice(): void {
        $logger = $this->createMockLogger(LogLevel::NOTICE, '[E_USER_DEPRECATED]');
        $handler = ErrorHandler::createUnregistered($logger);

        $handler->handleError(E_USER_DEPRECATED, 'User deprecated', __FILE__, __LINE__);
    }

    // ─── handleError: error_reporting() Respekt ───────────────────

    public function testSuppressedErrorIsNotLogged(): void {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('log');

        $handler = ErrorHandler::createUnregistered($logger);

        // Simuliere @ Operator: error_reporting(0)
        error_reporting(0);
        try {
            $result = $handler->handleError(E_WARNING, 'Suppressed', __FILE__, __LINE__);
            $this->assertFalse($result);
        } finally {
            error_reporting(E_ALL);
        }
    }

    public function testHandleErrorReturnsTrueWhenHandled(): void {
        $handler = ErrorHandler::createUnregistered(new NullLogger());
        $result = $handler->handleError(E_WARNING, 'Handled', __FILE__, __LINE__);
        $this->assertTrue($result);
    }

    // ─── handleError: throwOnWarning ──────────────────────────────

    public function testThrowOnWarningThrowsErrorException(): void {
        $handler = ErrorHandler::createUnregistered(new NullLogger(), throwOnWarning: true);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Severe warning');

        $handler->handleError(E_WARNING, 'Severe warning', __FILE__, __LINE__);
    }

    public function testThrowOnWarningDoesNotThrowForNotice(): void {
        $handler = ErrorHandler::createUnregistered(new NullLogger(), throwOnWarning: true);

        $result = $handler->handleError(E_NOTICE, 'Just a notice', __FILE__, __LINE__);
        $this->assertTrue($result);
    }

    public function testThrowOnWarningDoesNotThrowForDeprecated(): void {
        $handler = ErrorHandler::createUnregistered(new NullLogger(), throwOnWarning: true);

        $result = $handler->handleError(E_DEPRECATED, 'Deprecated', __FILE__, __LINE__);
        $this->assertTrue($result);
    }

    // ─── handleError: Immer-werfen bei E_USER_ERROR / E_RECOVERABLE_ERROR ─

    public function testUserErrorAlwaysThrows(): void {
        $handler = ErrorHandler::createUnregistered(new NullLogger());

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Fatal user error');

        $handler->handleError(E_USER_ERROR, 'Fatal user error', __FILE__, __LINE__);
    }

    public function testRecoverableErrorAlwaysThrows(): void {
        $handler = ErrorHandler::createUnregistered(new NullLogger());

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Recoverable error');

        $handler->handleError(E_RECOVERABLE_ERROR, 'Recoverable error', __FILE__, __LINE__);
    }

    // ─── handleError: Context enthält Details ─────────────────────

    public function testErrorContextContainsFileAndLine(): void {
        $capturedContext = null;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $context) use (&$capturedContext) {
                    $capturedContext = $context;
                    return true;
                })
            );

        $handler = ErrorHandler::createUnregistered($logger);
        $handler->handleError(E_WARNING, 'test', '/some/file.php', 42);

        $this->assertSame('/some/file.php', $capturedContext['file']);
        $this->assertSame(42, $capturedContext['line']);
        $this->assertSame(E_WARNING, $capturedContext['severity']);
        $this->assertSame('E_WARNING', $capturedContext['severity_name']);
    }

    // ─── handleException ──────────────────────────────────────────

    public function testHandleExceptionLogsCritical(): void {
        $logger = $this->createMockLogger(LogLevel::CRITICAL, 'Nicht-gefangene Exception');
        $handler = ErrorHandler::createUnregistered($logger);

        $exception = new \RuntimeException('Something broke', 42);
        $handler->handleException($exception);
    }

    public function testHandleExceptionContextContainsDetails(): void {
        $capturedContext = null;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::CRITICAL,
                $this->anything(),
                $this->callback(function (array $context) use (&$capturedContext) {
                    $capturedContext = $context;
                    return true;
                })
            );

        $handler = ErrorHandler::createUnregistered($logger);
        $exception = new \InvalidArgumentException('Bad arg', 99);
        $handler->handleException($exception);

        $this->assertNotNull($capturedContext);
        assert(is_array($capturedContext));
        $this->assertSame('InvalidArgumentException', $capturedContext['exception']);
        $this->assertSame('Bad arg', $capturedContext['message']);
        $this->assertSame(99, $capturedContext['code']);
        $this->assertArrayHasKey('trace', $capturedContext);
    }

    // ─── handleShutdown ───────────────────────────────────────────

    public function testHandleShutdownWithNoErrorDoesNothing(): void {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('log');

        $handler = ErrorHandler::createUnregistered($logger);

        if (error_get_last() === null) {
            $handler->handleShutdown();
        } else {
            $this->markTestSkipped('error_get_last() ist nicht null');
        }
    }

    // ─── Logger Fallback ──────────────────────────────────────────

    public function testFallsBackToLoggerRegistry(): void {
        $logger = $this->createMockLogger(LogLevel::WARNING, '[E_WARNING]');
        LoggerRegistry::setLogger($logger);

        // Kein Logger übergeben → nutzt LoggerRegistry
        $handler = ErrorHandler::createUnregistered();

        $handler->handleError(E_WARNING, 'Registry fallback', __FILE__, __LINE__);
    }

    public function testFallsBackToErrorLogWhenNoLogger(): void {
        LoggerRegistry::resetLogger();
        $handler = ErrorHandler::createUnregistered();

        // error_log() Fallback → kein Crash erwartet
        $handler->handleError(E_NOTICE, 'No logger available', __FILE__, __LINE__);
        $this->assertTrue(true);
    }

    // ─── Integration: register/unregister (separater Prozess) ─────

    #[RunInSeparateProcess]
    public function testRegisterAndUnregisterLifecycle(): void {
        $handler = ErrorHandler::register(new NullLogger());
        $this->assertTrue($handler->isRegistered());

        $handler->unregister();
        $this->assertFalse($handler->isRegistered());
    }

    #[RunInSeparateProcess]
    public function testUnregisterTwiceIsHarmless(): void {
        $handler = ErrorHandler::register(new NullLogger());
        $handler->unregister();
        $handler->unregister();
        $this->assertFalse($handler->isRegistered());
    }

    #[RunInSeparateProcess]
    public function testTriggerUserWarningIsCaptured(): void {
        $captured = false;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('log')
            ->willReturnCallback(function () use (&$captured) {
                $captured = true;
            });

        $handler = ErrorHandler::register($logger);

        trigger_error('Integration test warning', E_USER_WARNING);

        $this->assertTrue($captured, 'Logger wurde nicht aufgerufen');
        $handler->unregister();
    }

    #[RunInSeparateProcess]
    public function testTriggerUserNoticeIsCaptured(): void {
        $captured = false;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('log')
            ->willReturnCallback(function () use (&$captured) {
                $captured = true;
            });

        $handler = ErrorHandler::register($logger);

        trigger_error('Integration test notice', E_USER_NOTICE);

        $this->assertTrue($captured, 'Logger wurde nicht aufgerufen');
        $handler->unregister();
    }

    // ─── Mehrfach-Registrierung ─────────────────────────────────

    #[RunInSeparateProcess]
    public function testDoubleRegisterThrowsLogicException(): void {
        $handler = ErrorHandler::register(new NullLogger());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('bereits registriert');

        ErrorHandler::register(new NullLogger());
    }

    #[RunInSeparateProcess]
    public function testCanReRegisterAfterUnregister(): void {
        $handler = ErrorHandler::register(new NullLogger());
        $handler->unregister();

        $handler2 = ErrorHandler::register(new NullLogger());
        $this->assertTrue($handler2->isRegistered());
        $handler2->unregister();
    }

    #[RunInSeparateProcess]
    public function testGetActiveInstanceReturnsRegisteredHandler(): void {
        $this->assertNull(ErrorHandler::getActiveInstance());

        $handler = ErrorHandler::register(new NullLogger());
        $this->assertSame($handler, ErrorHandler::getActiveInstance());

        $handler->unregister();
        $this->assertNull(ErrorHandler::getActiveInstance());
    }

    // ─── Listener/Callback-System ─────────────────────────────────

    public function testListenerIsCalledOnMatchingLevel(): void {
        $called = false;
        $capturedMessage = '';
        $capturedContext = [];

        $handler = ErrorHandler::createUnregistered(new NullLogger());
        $handler->addListener(LogLevel::WARNING, function (string $message, array $context) use (&$called, &$capturedMessage, &$capturedContext) {
            $called = true;
            $capturedMessage = $message;
            $capturedContext = $context;
        });

        $handler->handleError(E_WARNING, 'Listener test', __FILE__, __LINE__);

        $this->assertTrue($called, 'Listener wurde nicht aufgerufen');
        $this->assertStringContainsString('Listener test', $capturedMessage);
        $this->assertSame('E_WARNING', $capturedContext['severity_name']);
    }

    public function testListenerIsNotCalledOnDifferentLevel(): void {
        $called = false;

        $handler = ErrorHandler::createUnregistered(new NullLogger());
        $handler->addListener(LogLevel::CRITICAL, function () use (&$called) {
            $called = true;
        });

        // E_WARNING → LogLevel::WARNING, nicht CRITICAL
        $handler->handleError(E_WARNING, 'No listener expected', __FILE__, __LINE__);

        $this->assertFalse($called, 'Listener sollte nicht aufgerufen werden');
    }

    public function testMultipleListenersOnSameLevel(): void {
        $count = 0;

        $handler = ErrorHandler::createUnregistered(new NullLogger());
        $handler->addListener(LogLevel::WARNING, function () use (&$count) {
            $count++;
        });
        $handler->addListener(LogLevel::WARNING, function () use (&$count) {
            $count++;
        });

        $handler->handleError(E_WARNING, 'Multi listener', __FILE__, __LINE__);

        $this->assertSame(2, $count);
    }

    public function testListenerExceptionDoesNotCrashHandler(): void {
        $secondCalled = false;

        $handler = ErrorHandler::createUnregistered(new NullLogger());
        $handler->addListener(LogLevel::WARNING, function () {
            throw new \RuntimeException('Listener kaputt');
        });
        $handler->addListener(LogLevel::WARNING, function () use (&$secondCalled) {
            $secondCalled = true;
        });

        // Handler darf nicht crashen trotz fehlerhaftem Listener
        $result = $handler->handleError(E_WARNING, 'Robust test', __FILE__, __LINE__);

        $this->assertTrue($result);
        $this->assertTrue($secondCalled, 'Zweiter Listener muss trotzdem aufgerufen werden');
    }

    public function testListenerOnExceptionLevel(): void {
        $called = false;

        $handler = ErrorHandler::createUnregistered(new NullLogger());
        $handler->addListener(LogLevel::CRITICAL, function () use (&$called) {
            $called = true;
        });

        $handler->handleException(new \RuntimeException('Test'));

        $this->assertTrue($called, 'CRITICAL-Listener sollte bei Exception aufgerufen werden');
    }

    public function testAddListenerReturnsSelfForChaining(): void {
        $handler = ErrorHandler::createUnregistered(new NullLogger());
        $result = $handler->addListener(LogLevel::WARNING, function () {
        });
        $this->assertSame($handler, $result);
    }

    // ─── exitOnException ──────────────────────────────────────────

    public function testExitOnExceptionDefaultFalseForUnregistered(): void {
        // createUnregistered hat exitOnException=false als Default
        $handler = ErrorHandler::createUnregistered(new NullLogger());
        $exception = new \RuntimeException('No exit');

        // Sollte NICHT exit() aufrufen → einfach zurückkehren
        $handler->handleException($exception);
        $this->assertTrue(true); // Kein Exit = Erfolg
    }

    // ─── Hilfsmethoden ────────────────────────────────────────────

    private function createMockLogger(string $expectedLevel, string $messageContains): LoggerInterface {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(
                $expectedLevel,
                $this->stringContains($messageContains),
                $this->isType('array')
            );
        return $logger;
    }
}
