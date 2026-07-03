<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ErrorToolkitServiceProvider.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Laravel;

use ERRORToolkit\LoggerRegistry;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Optional Laravel bridge (auto-discovered via composer extra).
 *
 * Binds the toolkit-wide LoggerRegistry lazily to the application's log
 * manager, so every library logging through ERRORToolkit\Traits\ErrorLog
 * (php-common-toolkit, php-api-toolkit, the SDKs) writes into the regular
 * Laravel log instead of the error_log/syslog fallback.
 *
 * The target channel is configurable via config/error-toolkit.php
 * ('channel', ENV ERROR_TOOLKIT_LOG_CHANNEL); null uses the default
 * channel. An explicitly set LoggerRegistry::setLogger() always wins over
 * the lazy resolver.
 */
class ErrorToolkitServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../../config/error-toolkit.php', 'error-toolkit');
    }

    public function boot(): void {
        $this->publishes([
            __DIR__ . '/../../config/error-toolkit.php' => $this->app->basePath('config/error-toolkit.php'),
        ], 'error-toolkit-config');

        LoggerRegistry::setLoggerResolver(function (): ?LoggerInterface {
            $log = $this->app->make('log');

            $channel = null;
            $config = $this->app->make('config');
            if (is_object($config) && method_exists($config, 'get')) {
                $channel = $config->get('error-toolkit.channel');
            }

            if (is_string($channel) && $channel !== '' && is_object($log) && method_exists($log, 'channel')) {
                $log = $log->channel($channel);
            }

            return $log instanceof LoggerInterface ? $log : null;
        });
    }
}
