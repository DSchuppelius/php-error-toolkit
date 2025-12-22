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
            'TERM_PROGRAM'
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
            'PHP_IDE_CONFIG'
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
     */
    public static function getCursorColumn(): int {
        if (!self::isTerminal()) {
            return 0;
        }

        // Nur auf Unix-artigen Systemen verfügbar
        if (!OsHelper::isUnix()) {
            return 0;
        }

        // Terminal-Einstellungen speichern
        $ttyProps = trim(`stty -g 2>/dev/null`);
        if (empty($ttyProps)) {
            return 0;
        }

        try {
            system('stty -icanon -echo 2>/dev/null');

            $term = fopen('/dev/tty', 'w');
            if (!$term) {
                return 0;
            }
            fwrite($term, "\033[6n");
            fclose($term);

            $buf = fread(STDIN, 16);

            system("stty '$ttyProps' 2>/dev/null");

            if (preg_match('/^\033\[(\d+);(\d+)R$/', $buf, $matches)) {
                return (int)$matches[2];
            }

            return 0;
        } catch (Exception $e) {
            if (!empty($ttyProps)) {
                system("stty '$ttyProps' 2>/dev/null");
            }
            return 0;
        }
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
            return (int)$columns;
        }

        // System-spezifische Befehle
        if (OsHelper::isWindows()) {
            $output = shell_exec('mode con | findstr "Columns"');
            if ($output && preg_match('/Columns:\s*(\d+)/', $output, $matches)) {
                return (int)$matches[1];
            }
        } else {
            // Unix: tput verwenden
            if (OsHelper::findExecutable('tput')) {
                $width = shell_exec('tput cols 2>/dev/null');
                if ($width && is_numeric(trim($width))) {
                    return (int)trim($width);
                }
            }

            // Fallback: stty verwenden
            if (OsHelper::findExecutable('stty')) {
                $output = shell_exec('stty size 2>/dev/null');
                if ($output && preg_match('/\d+\s+(\d+)/', trim($output), $matches)) {
                    return (int)$matches[1];
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
            return (int)$lines;
        }

        // System-spezifische Befehle
        if (OsHelper::isWindows()) {
            $output = shell_exec('mode con | findstr "Lines"');
            if ($output && preg_match('/Lines:\s*(\d+)/', $output, $matches)) {
                return (int)$matches[1];
            }
        } else {
            // Unix: tput verwenden
            if (OsHelper::findExecutable('tput')) {
                $height = shell_exec('tput lines 2>/dev/null');
                if ($height && is_numeric(trim($height))) {
                    return (int)trim($height);
                }
            }

            // Fallback: stty verwenden
            if (OsHelper::findExecutable('stty')) {
                $output = shell_exec('stty size 2>/dev/null');
                if ($output && preg_match('/(\d+)\s+\d+/', trim($output), $matches)) {
                    return (int)$matches[1];
                }
            }
        }

        return 24; // Standard-Fallback
    }

    /**
     * Prüft, ob der Cursor am Anfang einer neuen Zeile steht.
     * Gibt true zurück wenn der Cursor in Spalte 1 steht oder die Position nicht ermittelt werden kann.
     */
    public static function isNewline(): bool {
        $column = self::getCursorColumn();
        // Wenn wir die Position nicht ermitteln können (0), nehmen wir an dass wir am Zeilenbeginn sind
        return $column <= 1;
    }
}