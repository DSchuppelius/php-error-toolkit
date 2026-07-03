<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ErrorToolkitServiceProviderTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\Laravel;

use ERRORToolkit\Laravel\ErrorToolkitServiceProvider;
use ERRORToolkit\LoggerRegistry;
use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\TestCase;
use Psr\Log\{LoggerInterface, NullLogger};

class ErrorToolkitServiceProviderTest extends TestCase {
    private NullLogger $defaultLogger;
    private NullLogger $channelLogger;

    protected function setUp(): void {
        LoggerRegistry::resetLogger();
        $this->defaultLogger = new NullLogger;
        $this->channelLogger = new NullLogger;
    }

    protected function tearDown(): void {
        LoggerRegistry::resetLogger();
    }

    private function makeApp(?string $channel): Application {
        $config = new class($channel) {
            /** @var array<string, mixed> */
            private array $values;

            public function __construct(?string $channel) {
                $this->values = ['error-toolkit.channel' => $channel];
            }

            public function get(string $key, mixed $default = null): mixed {
                return $this->values[$key] ?? $default;
            }

            public function set(string $key, mixed $value): void {
                $this->values[$key] = $value;
            }
        };

        $logManager = new class($this->defaultLogger, $this->channelLogger) implements LoggerInterface {
            use \Psr\Log\LoggerTrait;

            public function __construct(private LoggerInterface $default, private LoggerInterface $channelLogger) {}

            public function channel(string $name): LoggerInterface {
                return $this->channelLogger;
            }

            public function log($level, \Stringable|string $message, array $context = []): void {
                $this->default->log($level, $message, $context);
            }
        };

        $app = $this->createMock(Application::class);
        $app->method('make')->willReturnCallback(fn (string $abstract) => match ($abstract) {
            'config' => $config,
            'log' => $logManager,
            default => null,
        });
        $app->method('basePath')->willReturnCallback(fn ($path = '') => '/tmp/app/' . $path);

        return $app;
    }

    private function bootProvider(?string $channel): LoggerInterface {
        $provider = new ErrorToolkitServiceProvider($this->makeApp($channel));
        $provider->register();
        $provider->boot();

        $resolved = LoggerRegistry::getLogger();
        $this->assertNotNull($resolved, 'Provider must register a working lazy resolver');

        return $resolved;
    }

    public function test_boot_binds_default_log_manager_to_registry(): void {
        $resolved = $this->bootProvider(null);

        // Without a configured channel the log manager itself is used.
        $resolved->info('ping');
        $this->assertFalse(LoggerRegistry::getLogger() === $this->channelLogger);
    }

    public function test_boot_respects_configured_channel(): void {
        $resolved = $this->bootProvider('toolkit');

        $this->assertSame($this->channelLogger, $resolved);
    }

    public function test_explicitly_set_logger_wins_over_bridge(): void {
        $explicit = new NullLogger;

        $provider = new ErrorToolkitServiceProvider($this->makeApp(null));
        $provider->register();
        $provider->boot();

        LoggerRegistry::setLogger($explicit);

        $this->assertSame($explicit, LoggerRegistry::getLogger());
    }
}
