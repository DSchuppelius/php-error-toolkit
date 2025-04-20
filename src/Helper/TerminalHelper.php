<?php
/*
 * Created on   : Fri Apr 04 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TerminalHelper.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Helper;

class TerminalHelper {
    /**
     * Prüft, ob das aktuelle Skript in einem interaktiven Terminal läuft.
     */
    public static function isTerminal(): bool {
        if (stripos(PHP_OS, 'WIN') === 0) {
            return function_exists('sapi_windows_vt100_support') && sapi_windows_vt100_support(STDOUT);
        }

        return function_exists('posix_isatty') && posix_isatty(STDOUT);
    }

    /**
     * Gibt die aktuelle Cursor-Spalte im Terminal zurück (1-basiert).
     * Gibt 0 zurück, wenn keine gültige Position ermittelt werden kann.
     */
    public static function getCursorColumn(): int {
        if (!self::isTerminal()) {
            return 0;
        }

        // Cursorposition anfordern: ESC [6n
        echo "\033[6n";

        // Auf nicht blockierend setzen
        stream_set_blocking(STDIN, false);

        $response = '';
        $start = microtime(true);
        while ((microtime(true) - $start) < 0.1) {
            $char = fgetc(STDIN);
            if ($char === false) {
                usleep(500);
                continue;
            }

            $response .= $char;
            if (str_ends_with($response, 'R')) {
                break;
            }
        }

        // Antwort auswerten, z. B. ESC[12;42R
        if (preg_match('/\[(\d+);(\d+)R/', $response, $matches)) {
            return (int)$matches[2];
        }

        return 0;
    }
}
