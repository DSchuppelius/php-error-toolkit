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
        return defined('PHPUNIT_COMPOSER_INSTALL') ||
            class_exists('PHPUnit\\Framework\\TestCase', false) ||
            (isset($_SERVER['argv']) && strpos(implode(' ', $_SERVER['argv']), 'phpunit') !== false);
    }

    /**
     * Prüft PHPUnit's Color-Einstellungen.
     */
    public static function supportsColors(): bool {
        // Kommandozeilen-Argumente haben Priorität über XML-Konfiguration
        if (isset($_SERVER['argv'])) {
            $args = implode(' ', $_SERVER['argv']);
            if (strpos($args, '--colors=never') !== false || strpos($args, '--no-colors') !== false) {
                return false;
            }

            if (strpos($args, '--colors=always') !== false) {
                return true;
            }

            if (strpos($args, '--colors=auto') !== false || strpos($args, '--colors') !== false) {
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
                $xml = @simplexml_load_file($configFile);
                if ($xml !== false && isset($xml['colors'])) {
                    return (string)$xml['colors'];
                }
            }
        }

        return null;
    }
}
