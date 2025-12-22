<?php
/*
 * Created on   : Sun Dec 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OsHelper.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Helper;

class OsHelper {
    /**
     * Prüft, ob das aktuelle System Windows ist.
     */
    public static function isWindows(): bool {
        return stripos(PHP_OS, 'WIN') === 0;
    }

    /**
     * Prüft, ob das aktuelle System Linux ist.
     */
    public static function isLinux(): bool {
        return stripos(PHP_OS, 'LINUX') === 0;
    }

    /**
     * Prüft, ob das aktuelle System macOS ist.
     */
    public static function isMacOS(): bool {
        return stripos(PHP_OS, 'DAR') === 0; // Darwin
    }

    /**
     * Prüft, ob das aktuelle System Unix-artig ist (Linux oder macOS).
     */
    public static function isUnix(): bool {
        return self::isLinux() || self::isMacOS();
    }

    /**
     * Gibt den Betriebssystem-Namen zurück.
     */
    public static function getOsName(): string {
        if (self::isWindows()) {
            return 'Windows';
        }
        if (self::isLinux()) {
            return 'Linux';
        }
        if (self::isMacOS()) {
            return 'macOS';
        }
        return PHP_OS;
    }

    /**
     * Gibt den korrekten Pfad-Separator für das aktuelle System zurück.
     */
    public static function getPathSeparator(): string {
        return DIRECTORY_SEPARATOR;
    }

    /**
     * Gibt den korrekten PATH-Separator für Umgebungsvariablen zurück.
     */
    public static function getEnvPathSeparator(): string {
        return self::isWindows() ? ';' : ':';
    }

    /**
     * Gibt das Home-Verzeichnis des aktuellen Benutzers zurück.
     */
    public static function getHomeDirectory(): string {
        if (self::isWindows()) {
            return $_SERVER['USERPROFILE'] ?? $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'] ?? '';
        }
        return $_SERVER['HOME'] ?? '';
    }

    /**
     * Gibt das Temp-Verzeichnis des Systems zurück.
     */
    public static function getTempDirectory(): string {
        return sys_get_temp_dir();
    }

    /**
     * Prüft, ob eine Datei ausführbar ist.
     */
    public static function isExecutable(string $path): bool {
        if (!file_exists($path)) {
            return false;
        }

        if (self::isWindows()) {
            // Auf Windows prüfen wir die Dateiendung
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            return in_array($ext, ['exe', 'bat', 'cmd', 'com'], true);
        }

        return is_executable($path);
    }

    /**
     * Sucht nach einem Executable in den PATH-Verzeichnissen.
     */
    public static function findExecutable(string $name): ?string {
        $pathEnv = $_SERVER['PATH'] ?? '';
        if (empty($pathEnv)) {
            return null;
        }

        $paths = explode(self::getEnvPathSeparator(), $pathEnv);
        $extensions = self::isWindows() ? ['', '.exe', '.bat', '.cmd', '.com'] : [''];

        foreach ($paths as $path) {
            $path = trim($path);
            if (empty($path)) {
                continue;
            }

            foreach ($extensions as $ext) {
                $fullPath = $path . self::getPathSeparator() . $name . $ext;
                if (self::isExecutable($fullPath)) {
                    return $fullPath;
                }
            }
        }

        return null;
    }

    /**
     * Gibt die aktuelle Benutzer-ID zurück (nur Unix).
     */
    public static function getCurrentUserId(): ?int {
        if (self::isWindows()) {
            return null;
        }

        return function_exists('posix_getuid') ? posix_getuid() : null;
    }

    /**
     * Gibt die aktuelle Gruppen-ID zurück (nur Unix).
     */
    public static function getCurrentGroupId(): ?int {
        if (self::isWindows()) {
            return null;
        }

        return function_exists('posix_getgid') ? posix_getgid() : null;
    }

    /**
     * Gibt den aktuellen Benutzernamen zurück.
     */
    public static function getCurrentUsername(): string {
        if (self::isWindows()) {
            return $_SERVER['USERNAME'] ?? '';
        }

        if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $userInfo = posix_getpwuid(posix_getuid());
            return $userInfo['name'] ?? '';
        }

        return $_SERVER['USER'] ?? $_SERVER['LOGNAME'] ?? '';
    }

    /**
     * Prüft, ob der aktuelle Benutzer Root/Administrator-Rechte hat.
     */
    public static function isPrivilegedUser(): bool {
        if (self::isWindows()) {
            // Auf Windows ist das schwieriger zu prüfen, aber wir können versuchen
            // in ein System-Verzeichnis zu schreiben
            return is_writable('C:\\Windows\\System32') || is_writable('C:\\Windows');
        }

        // Auf Unix: UID 0 = root
        return self::getCurrentUserId() === 0;
    }

    /**
     * Gibt Umgebungsvariable zurück mit optional Default-Wert.
     */
    public static function getEnv(string $name, ?string $default = null): ?string {
        $value = $_SERVER[$name] ?? getenv($name);
        return $value !== false ? $value : $default;
    }

    /**
     * Setzt eine Umgebungsvariable.
     */
    public static function setEnv(string $name, string $value): bool {
        $_SERVER[$name] = $value;
        return putenv("$name=$value");
    }

    /**
     * Gibt die verfügbaren CPU-Kerne zurück.
     */
    public static function getCpuCoreCount(): int {
        if (self::isWindows()) {
            $cores = shell_exec('echo %NUMBER_OF_PROCESSORS%');
            return (int)trim($cores ?: '1');
        }

        if (self::isLinux()) {
            $cores = shell_exec('nproc');
            return (int)trim($cores ?: '1');
        }

        if (self::isMacOS()) {
            $cores = shell_exec('sysctl -n hw.ncpu');
            return (int)trim($cores ?: '1');
        }

        return 1;
    }

    /**
     * Gibt die System-Architektur zurück.
     */
    public static function getArchitecture(): string {
        return php_uname('m');
    }

    /**
     * Gibt die Kernel-Version zurück.
     */
    public static function getKernelVersion(): string {
        return php_uname('r');
    }

    /**
     * Gibt detaillierte System-Informationen zurück.
     */
    public static function getSystemInfo(): array {
        return [
            'os' => self::getOsName(),
            'php_os' => PHP_OS,
            'architecture' => self::getArchitecture(),
            'kernel' => self::getKernelVersion(),
            'hostname' => php_uname('n'),
            'username' => self::getCurrentUsername(),
            'home_dir' => self::getHomeDirectory(),
            'temp_dir' => self::getTempDirectory(),
            'cpu_cores' => self::getCpuCoreCount(),
            'is_privileged' => self::isPrivilegedUser(),
            'path_separator' => self::getPathSeparator(),
            'env_path_separator' => self::getEnvPathSeparator(),
        ];
    }
}