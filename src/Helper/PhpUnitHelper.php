<?php
/*
 * Created on   : Mon Dec 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhpUnitHelper.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Helper;

class PhpUnitHelper {
    /**
     * Prüft, ob PHPUnit läuft.
     */
    public static function isRunningInPhpunit(): bool {
        return class_exists('PHPUnit\\Framework\\TestCase', false) ||
            (isset($_SERVER['argv']) && str_contains(implode(' ', $_SERVER['argv']), 'phpunit'));
    }

    /**
     * Prüft PHPUnit's Color-Einstellungen.
     */
    public static function supportsColors(): bool {
        // Kommandozeilen-Argumente haben Priorität über XML-Konfiguration
        if (isset($_SERVER['argv'])) {
            $args = implode(' ', $_SERVER['argv']);
            if (str_contains($args, '--colors=never') || str_contains($args, '--no-colors')) {
                return false;
            }

            if (str_contains($args, '--colors=always')) {
                return true;
            }

            if (str_contains($args, '--colors=auto') || str_contains($args, '--colors')) {
                // Debug Console sollte bei --colors=auto auch Farben unterstützen
                return TerminalHelper::isDebugConsole() || TerminalHelper::isTerminal();
            }
        }

        // PHPUnit XML-Konfiguration prüfen
        $xmlColors = self::getXmlColorSetting();
        if ($xmlColors !== null) {
            if ($xmlColors === 'never') {
                return false;
            }
            if ($xmlColors === 'always') {
                return true;
            }
            if ($xmlColors === 'auto') {
                // Debug Console sollte bei auto auch Farben unterstützen
                return TerminalHelper::isDebugConsole() || TerminalHelper::isTerminal();
            }
            // colors="true" in XML entspricht --colors=auto
            if ($xmlColors === 'true' || $xmlColors === true) {
                return TerminalHelper::isDebugConsole() || TerminalHelper::isTerminal();
            }
            // colors="false" in XML entspricht --colors=never
            if ($xmlColors === 'false' || $xmlColors === false) {
                return false;
            }
        }

        // Standard PHPUnit-Verhalten: auto (basiert auf Terminal- oder Debug Console-Unterstützung)
        return TerminalHelper::isDebugConsole() || TerminalHelper::isTerminal();
    }

    /**
     * Liest die colors-Einstellung aus der PHPUnit XML-Konfiguration.
     */
    private static function getXmlColorSetting(): ?string {
        // Mögliche PHPUnit-Konfigurationsdateien
        $configFiles = [
            'phpunit.xml',
            'phpunit.xml.dist',
            'phpunit.dist.xml'
        ];

        foreach ($configFiles as $configFile) {
            if (file_exists($configFile)) {
                $previousUseErrors = libxml_use_internal_errors(true);
                $xml = simplexml_load_file($configFile, options: LIBXML_NONET);
                libxml_use_internal_errors($previousUseErrors);
                if ($xml !== false && isset($xml['colors'])) {
                    return (string)$xml['colors'];
                }
            }
        }

        return null;
    }
}
