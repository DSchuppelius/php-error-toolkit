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
     *
     * @return non-empty-string
     */
    public static function getEnvPathSeparator(): string {
        return self::isWindows() ? ';' : ':';
    }

    /**
     * Gibt das Home-Verzeichnis des aktuellen Benutzers zurück.
     */
    public static function getHomeDirectory(): string {
        if (self::isWindows()) {
            return $_SERVER['USERPROFILE'] ?? (($_SERVER['HOMEDRIVE'] ?? '') . ($_SERVER['HOMEPATH'] ?? '')) ?: '';
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
        if (isset($_SERVER[$name])) {
            return $_SERVER[$name];
        }

        $value = getenv($name, local_only: true);
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
     * Prüft, ob shell_exec (und damit auch die Backtick-Syntax) nutzbar ist.
     * Auf gehärteten Hosts kann die Funktion via disable_functions gesperrt sein.
     *
     * Das Toolkit selbst startet seit 2.8 keine Shell mehr; Hilfsprogramme laufen
     * über {@see ProcessHelper} (proc_open mit Argumentliste und Zeitgrenze).
     */
    public static function canExecuteShellCommands(): bool {
        if (!function_exists('shell_exec')) {
            return false;
        }

        $disabled = strtolower((string) ini_get('disable_functions'));
        if ($disabled === '') {
            return true;
        }

        $disabledList = array_map('trim', explode(',', $disabled));

        return !in_array('shell_exec', $disabledList, true);
    }

    /**
     * Gibt die verfügbaren CPU-Kerne zurück (mindestens 1).
     *
     * Linux: wie `nproc` die Kerne, auf denen der Prozess laufen darf
     * (Cpus_allowed_list aus /proc/self/status, sonst die Online-Kerne),
     * begrenzt durch OMP_NUM_THREADS/OMP_THREAD_LIMIT - gelesen, nicht über ein
     * Programm. Windows: NUMBER_OF_PROCESSORS. Sonst und als Rückfall (z. B. unter
     * open_basedir): posix_sysconf(), zuletzt nproc bzw. sysctl ohne Shell mit
     * Zeitgrenze ({@see ProcessHelper}).
     */
    public static function getCpuCoreCount(): int {
        if (self::isWindows()) {
            return self::positiveInt(self::getEnv('NUMBER_OF_PROCESSORS')) ?? 1;
        }

        if (self::isLinux()) {
            $cores = self::linuxCpuCount() ?? self::sysconfCpuCount() ?? self::positiveInt(ProcessHelper::output(['nproc']));
            return $cores !== null ? self::applyOpenMpLimits($cores) : 1;
        }

        $cores = self::sysconfCpuCount();
        if ($cores === null && self::isMacOS()) {
            $cores = self::positiveInt(ProcessHelper::output(['sysctl', '-n', 'hw.ncpu']));
        }

        return $cores ?? 1;
    }

    /**
     * Kerne, auf denen der Prozess laufen darf, aus /proc bzw. /sys; null, wenn
     * nichts lesbar ist (z. B. unter open_basedir).
     */
    private static function linuxCpuCount(): ?int {
        $status = @file_get_contents('/proc/self/status');
        if (is_string($status) && preg_match('/^Cpus_allowed_list:\s*(\S+)/m', $status, $matches) === 1) {
            $count = self::countCpuList($matches[1]);
            if ($count !== null) {
                return $count;
            }
        }

        $online = @file_get_contents('/sys/devices/system/cpu/online');
        if (is_string($online)) {
            $count = self::countCpuList(trim($online));
            if ($count !== null) {
                return $count;
            }
        }

        $cpuinfo = @file_get_contents('/proc/cpuinfo');
        if (is_string($cpuinfo)) {
            $count = (int) preg_match_all('/^processor\s*:/m', $cpuinfo);
            return $count > 0 ? $count : null;
        }

        return null;
    }

    /**
     * Zählt eine Linux-CPU-Liste wie "0-3,6,8-9" (= 7); null bei unlesbarem Format.
     */
    private static function countCpuList(string $list): ?int {
        if (preg_match('/^\d+(?:-\d+)?(?:,\d+(?:-\d+)?)*$/', $list) !== 1) {
            return null;
        }

        $count = 0;
        foreach (explode(',', $list) as $range) {
            $bounds = explode('-', $range);
            $count += count($bounds) === 2 ? max(0, (int) $bounds[1] - (int) $bounds[0] + 1) : 1;
        }

        return $count > 0 ? $count : null;
    }

    /**
     * Wie nproc: OMP_NUM_THREADS (erster Wert) ersetzt die Zahl, OMP_THREAD_LIMIT deckelt sie.
     */
    private static function applyOpenMpLimits(int $cores): int {
        $threads = self::getEnv('OMP_NUM_THREADS');
        if ($threads !== null) {
            $cores = self::positiveInt(explode(',', $threads)[0]) ?? $cores;
        }

        $limit = self::positiveInt(self::getEnv('OMP_THREAD_LIMIT'));

        return $limit !== null ? min($cores, $limit) : $cores;
    }

    /**
     * Online-Kerne über posix_sysconf() (PHP 8.3+ mit posix); null, wenn nicht verfügbar.
     */
    private static function sysconfCpuCount(): ?int {
        if (!function_exists('posix_sysconf') || !defined('POSIX_SC_NPROCESSORS_ONLN')) {
            return null;
        }

        $cores = posix_sysconf(POSIX_SC_NPROCESSORS_ONLN);

        return $cores > 0 ? $cores : null;
    }

    /**
     * Positive Ganzzahl aus einem Text ("8\n" = 8); null bei allem anderen.
     */
    private static function positiveInt(?string $value): ?int {
        if ($value === null || preg_match('/^\s*(\d+)\s*$/', $value, $matches) !== 1) {
            return null;
        }

        $number = (int) $matches[1];

        return $number > 0 ? $number : null;
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
     *
     * @return array<string, mixed>
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
