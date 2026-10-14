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

use Exception;

class TerminalHelper {
    /**
     * Prüft, ob das aktuelle Skript in einem interaktiven Terminal läuft.
     */
    public static function isTerminal(): bool {
        if (OsHelper::isWindows()) {
            return function_exists('sapi_windows_vt100_support') && sapi_windows_vt100_support(STDOUT);
        }

        return function_exists('posix_isatty') && posix_isatty(STDOUT);
    }

    /**
     * Prüft, ob das Skript in einer Debug-Console (z.B. VS Code) läuft.
     */
    public static function isDebugConsole(): bool {
        // XDEBUG_CONFIG kann VS Code Debug Console anzeigen
        $xdebugConfig = OsHelper::getEnv('XDEBUG_CONFIG');
        if ($xdebugConfig && stripos($xdebugConfig, 'vscode') !== false) {
            return true;
        }

        // VS Code Debug Console Indikatoren
        $vscodeIndicators = [
            'VSCODE_PID',
            'VSCODE_CWD',
            'VSCODE_IPC_HOOK',
            'VSCODE_IPC_HOOK_CLI',
            'TERM_PROGRAM',
        ];

        foreach ($vscodeIndicators as $indicator) {
            if (OsHelper::getEnv($indicator) !== null) {
                return true;
            }
        }

        // TERM_PROGRAM kann spezifische Werte haben
        $termProgram = OsHelper::getEnv('TERM_PROGRAM');
        if ($termProgram && in_array($termProgram, ['vscode', 'Visual Studio Code'], true)) {
            return true;
        }

        // Weitere Debug-Console-Indikatoren
        $debugIndicators = [
            'XDEBUG_SESSION',
            'XDEBUG_SESSION_START',
            'PHP_IDE_CONFIG',
        ];

        foreach ($debugIndicators as $indicator) {
            if (OsHelper::getEnv($indicator) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gibt die aktuelle Cursor-Spalte im Terminal zurück (1-basiert).
     * Gibt 0 zurück, wenn keine gültige Position ermittelt werden kann.
     *
     * ACHTUNG: Diese Methode ist teuer (drei stty-Aufrufe + Terminal-I/O).
     * Nicht für häufige Aufrufe (z.B. pro Log-Zeile) verwenden.
     *
     * stty läuft ohne Shell mit Zeitgrenze ({@see ProcessHelper}) und erbt die
     * Standardeingabe - es stellt das Terminal ein, an dem der Prozess hängt.
     */
    public static function getCursorColumn(): int {
        if (!self::isTerminal()) {
            return 0;
        }

        // Nur auf Unix-artigen Systemen verfügbar
        if (!OsHelper::isUnix()) {
            return 0;
        }

        // Benötigt proc_open(); auf gehärteten Hosts nicht verfügbar.
        if (!ProcessHelper::canStartProcesses()) {
            return 0;
        }

        // Terminal-Einstellungen speichern
        $ttyProps = trim(ProcessHelper::output(['stty', '-g'], inheritStdin: true) ?? '');
        if ($ttyProps === '') {
            return 0;
        }

        $tty = false;
        $buf = '';
        try {
            ProcessHelper::run(['stty', '-icanon', '-echo'], inheritStdin: true);

            // /dev/tty direkt für Lesen UND Schreiben öffnen – unabhängig von STDIN,
            // das von PHPUnit, CI-Systemen oder anderen Prozessen belegt sein kann.
            $tty = fopen('/dev/tty', 'r+');
            if (!$tty) {
                return 0;
            }

            fwrite($tty, "\033[6n");

            // Non-blocking Read mit Timeout (1 Sekunde) um Hänger zu vermeiden
            $read = [$tty];
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 1) > 0) {
                $result = @fread($tty, 16);

                if (is_string($result)) {
                    $buf = $result;
                }
            }
        } catch (Exception) {
            return 0;
        } finally {
            if (is_resource($tty)) {
                fclose($tty);
            }
            // Terminal-Einstellungen auf jedem Weg wiederherstellen
            self::restoreTty($ttyProps);
        }

        if ($buf !== '' && preg_match('/^\033\[(\d+);(\d+)R$/', $buf, $matches)) {
            return (int) $matches[2];
        }

        return 0;
    }

    /**
     * Stellt die mit `stty -g` gesicherten Terminal-Einstellungen wieder her
     * (der gesicherte Wert geht als ein Argument an stty, ohne Shell).
     */
    private static function restoreTty(string $ttyProps): void {
        ProcessHelper::run(['stty', $ttyProps], inheritStdin: true);
    }

    /**
     * Fragt das Terminal über ein Hilfsprogramm (ohne Shell, mit Zeitgrenze)
     * und liefert die erste Zahl, die $pattern trifft; null ohne Treffer oder bei 0.
     *
     * @param list<string> $command
     */
    private static function querySize(array $command, string $pattern): ?int {
        $output = ProcessHelper::output($command, inheritStdin: true);
        if ($output === null || preg_match($pattern, trim($output), $matches) !== 1) {
            return null;
        }

        $size = (int) $matches[1];

        return $size > 0 ? $size : null;
    }

    /**
     * Prüft, ob ANSI-Farbcodes unterstützt werden.
     */
    public static function supportsColors(): bool {
        if (PhpUnitHelper::isRunningInPhpunit()) {
            return PhpUnitHelper::supportsColors();
        }

        if (self::isDebugConsole()) {
            return true;
        }

        if (!self::isTerminal()) {
            return false;
        }

        if (OsHelper::isWindows()) {
            return function_exists('sapi_windows_vt100_support') && sapi_windows_vt100_support(STDOUT);
        }

        $term = OsHelper::getEnv('TERM');
        if ($term === null || $term === 'dumb') {
            return false;
        }

        return OsHelper::getEnv('COLORTERM') !== null ||
            strpos($term, 'color') !== false ||
            in_array($term, ['xterm', 'xterm-256color', 'screen', 'screen-256color'], true);
    }

    /**
     * Gibt die Terminal-Breite zurück.
     */
    public static function getTerminalWidth(): int {
        if (!self::isTerminal()) {
            return 80; // Standard-Fallback
        }

        // Umgebungsvariable prüfen
        $columns = OsHelper::getEnv('COLUMNS');
        if ($columns !== null && is_numeric($columns)) {
            return (int) $columns;
        }

        // System-spezifische Programme (ohne Shell, mit Zeitgrenze; benötigen proc_open)
        if (ProcessHelper::canStartProcesses()) {
            if (OsHelper::isWindows()) {
                $width = self::querySize(['mode.com', 'con'], '/Columns:\s*(\d+)/');
                if ($width !== null) {
                    return $width;
                }
            } else {
                // Unix: tput verwenden
                if (OsHelper::findExecutable('tput')) {
                    $width = self::querySize(['tput', 'cols'], '/^(\d+)$/');
                    if ($width !== null) {
                        return $width;
                    }
                }

                // Fallback: stty verwenden
                if (OsHelper::findExecutable('stty')) {
                    $width = self::querySize(['stty', 'size'], '/\d+\s+(\d+)/');
                    if ($width !== null) {
                        return $width;
                    }
                }
            }
        }

        return 80; // Standard-Fallback
    }

    /**
     * Gibt die Terminal-Höhe zurück.
     */
    public static function getTerminalHeight(): int {
        if (!self::isTerminal()) {
            return 24; // Standard-Fallback
        }

        // Umgebungsvariable prüfen
        $lines = OsHelper::getEnv('LINES');
        if ($lines !== null && is_numeric($lines)) {
            return (int) $lines;
        }

        // System-spezifische Programme (ohne Shell, mit Zeitgrenze; benötigen proc_open)
        if (ProcessHelper::canStartProcesses()) {
            if (OsHelper::isWindows()) {
                $height = self::querySize(['mode.com', 'con'], '/Lines:\s*(\d+)/');
                if ($height !== null) {
                    return $height;
                }
            } else {
                // Unix: tput verwenden
                if (OsHelper::findExecutable('tput')) {
                    $height = self::querySize(['tput', 'lines'], '/^(\d+)$/');
                    if ($height !== null) {
                        return $height;
                    }
                }

                // Fallback: stty verwenden
                if (OsHelper::findExecutable('stty')) {
                    $height = self::querySize(['stty', 'size'], '/(\d+)\s+\d+/');
                    if ($height !== null) {
                        return $height;
                    }
                }
            }
        }

        return 24; // Standard-Fallback
    }
}
