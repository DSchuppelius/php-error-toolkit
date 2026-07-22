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
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Throwable;

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

        // The registry caches the resolved logger in a static property that
        // outlives the application instance (same PHP process, new app: every
        // PHPUnit feature test, Octane worker, queue restart). A logger
        // resolved for a previous, already-flushed app would keep resolving
        // config/channels against that dead container ("Target class [config]
        // does not exist"). Booting on a fresh app therefore discards any
        // previously cached logger/resolver before registering its own.
        LoggerRegistry::resetLogger();

        // The resolver must not capture $this->app: the closure outlives the
        // booting application (static registry), and the NEXT log call may
        // happen when that app is already flushed — e.g. a plain PHPUnit test
        // (no Laravel app) running after a feature test in the same process.
        // Resolving against a dead container throws ("Class 'log' does not
        // exist"). Therefore: always resolve against the CURRENT container,
        // and fail soft (null → ErrorLog fallback) instead of throwing.
        LoggerRegistry::setLoggerResolver(function (): ?LoggerInterface {
            $app = Container::getInstance();
            if (!$app instanceof Container || !$app->bound('log')) {
                return null; // container gone or flushed → fallback logging
            }

            try {
                $log = $app->make('log');

                $channel = null;
                if ($app->bound('config')) {
                    $config = $app->make('config');
                    if (is_object($config) && method_exists($config, 'get')) {
                        $channel = $config->get('error-toolkit.channel');
                    }
                }

                if (is_string($channel) && $channel !== '' && is_object($log) && method_exists($log, 'channel')) {
                    $log = $log->channel($channel);
                }

                return $log instanceof LoggerInterface ? $log : null;
            } catch (Throwable) {
                return null; // never let logging take the process down
            }
        });
    }
}
