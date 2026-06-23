<?php
/*
 * Created on   : Thu Apr 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OsHelperTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use ERRORToolkit\Helper\OsHelper;
use PHPUnit\Framework\TestCase;

class OsHelperTest extends TestCase {
    public function test_is_windows_returns_bool(): void {
        $this->assertIsBool(OsHelper::isWindows());
    }

    public function test_is_linux_returns_bool(): void {
        $this->assertIsBool(OsHelper::isLinux());
    }

    public function test_is_mac_os_returns_bool(): void {
        $this->assertIsBool(OsHelper::isMacOS());
    }

    public function test_is_unix_returns_bool(): void {
        $this->assertIsBool(OsHelper::isUnix());
    }

    public function test_get_os_name_returns_non_empty(): void {
        $name = OsHelper::getOsName();
        $this->assertNotEmpty($name);
        $this->assertContains($name, ['Windows', 'Linux', 'macOS', PHP_OS]);
    }

    public function test_get_path_separator(): void {
        $sep = OsHelper::getPathSeparator();
        $this->assertContains($sep, ['/', '\\']);
    }

    public function test_get_env_path_separator(): void {
        $sep = OsHelper::getEnvPathSeparator();
        $this->assertContains($sep, [':', ';']);
    }

    public function test_get_home_directory_returns_string(): void {
        $home = OsHelper::getHomeDirectory();
        $this->assertIsString($home);
    }

    public function test_get_temp_directory(): void {
        $tmp = OsHelper::getTempDirectory();
        $this->assertNotEmpty($tmp);
        $this->assertDirectoryExists($tmp);
    }

    public function test_get_env_with_existing_variable(): void {
        $_SERVER['TEST_OSHELPER_VAR'] = 'test_value';
        $this->assertSame('test_value', OsHelper::getEnv('TEST_OSHELPER_VAR'));
        unset($_SERVER['TEST_OSHELPER_VAR']);
    }

    public function test_get_env_with_default(): void {
        $result = OsHelper::getEnv('NONEXISTENT_VAR_XYZABC', 'default');
        $this->assertSame('default', $result);
    }

    public function test_get_env_returns_null_without_default(): void {
        $result = OsHelper::getEnv('NONEXISTENT_VAR_XYZABC');
        $this->assertNull($result);
    }

    public function test_set_env(): void {
        $result = OsHelper::setEnv('TEST_OSHELPER_SET', 'hello');
        $this->assertTrue($result);
        $this->assertSame('hello', OsHelper::getEnv('TEST_OSHELPER_SET'));

        // Cleanup
        putenv('TEST_OSHELPER_SET');
        unset($_SERVER['TEST_OSHELPER_SET']);
    }

    public function test_is_executable_with_non_existent_file(): void {
        $this->assertFalse(OsHelper::isExecutable('/nonexistent/path/to/file'));
    }

    public function test_find_executable_with_non_existent_command(): void {
        $this->assertNull(OsHelper::findExecutable('nonexistent_command_xyzabc'));
    }

    public function test_get_cpu_core_count_returns_positive(): void {
        $cores = OsHelper::getCpuCoreCount();
        $this->assertGreaterThanOrEqual(1, $cores);
    }

    public function test_get_architecture_returns_non_empty(): void {
        $arch = OsHelper::getArchitecture();
        $this->assertNotEmpty($arch);
    }

    public function test_get_kernel_version_returns_non_empty(): void {
        $version = OsHelper::getKernelVersion();
        $this->assertNotEmpty($version);
    }

    public function test_get_system_info_returns_expected_keys(): void {
        $info = OsHelper::getSystemInfo();
        $this->assertArrayHasKey('os', $info);
        $this->assertArrayHasKey('php_os', $info);
        $this->assertArrayHasKey('architecture', $info);
        $this->assertArrayHasKey('kernel', $info);
        $this->assertArrayHasKey('hostname', $info);
    }

    public function test_get_current_username_returns_string(): void {
        $username = OsHelper::getCurrentUsername();
        $this->assertIsString($username);
    }

    public function test_is_privileged_user_returns_bool(): void {
        $this->assertIsBool(OsHelper::isPrivilegedUser());
    }

    public function test_os_detection_consistency(): void {
        // Genau ein System-Typ sollte true sein (oder keiner bei exotischen OS)
        $detected = array_filter([
            OsHelper::isWindows(),
            OsHelper::isLinux(),
            OsHelper::isMacOS(),
        ]);
        $this->assertLessThanOrEqual(1, count($detected));

        // isUnix sollte konsistent sein
        if (OsHelper::isLinux() || OsHelper::isMacOS()) {
            $this->assertTrue(OsHelper::isUnix());
        }
    }
}
