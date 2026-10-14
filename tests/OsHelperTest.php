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

use ERRORToolkit\Helper\{OsHelper, ProcessHelper};
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class OsHelperTest extends TestCase {
    public function test_is_windows_matches_php_os(): void {
        $this->assertSame(stripos(PHP_OS, 'WIN') === 0, OsHelper::isWindows());
    }

    public function test_is_linux_matches_php_os(): void {
        $this->assertSame(stripos(PHP_OS, 'LINUX') === 0, OsHelper::isLinux());
    }

    public function test_is_mac_os_matches_php_os(): void {
        $this->assertSame(stripos(PHP_OS, 'DAR') === 0, OsHelper::isMacOS());
    }

    public function test_is_unix_matches_linux_or_mac(): void {
        $this->assertSame(OsHelper::isLinux() || OsHelper::isMacOS(), OsHelper::isUnix());
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

    public function test_get_home_directory_matches_environment(): void {
        if (OsHelper::isWindows()) {
            $expected = $_SERVER['USERPROFILE'] ?? (($_SERVER['HOMEDRIVE'] ?? '') . ($_SERVER['HOMEPATH'] ?? '')) ?: '';
        } else {
            $expected = $_SERVER['HOME'] ?? '';
        }
        $this->assertSame($expected, OsHelper::getHomeDirectory());
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

    public function test_get_cpu_core_count_matches_nproc_on_linux(): void {
        $nproc = OsHelper::isLinux() ? ProcessHelper::output(['nproc']) : null;
        if ($nproc === null) {
            $this->markTestSkipped('nproc nur unter Linux mit coreutils.');
        }

        $this->assertSame((int) trim($nproc), OsHelper::getCpuCoreCount());
    }

    public function test_count_cpu_list_reads_linux_ranges(): void {
        $count = new ReflectionMethod(OsHelper::class, 'countCpuList');

        $this->assertSame(7, $count->invoke(null, '0-3,6,8-9'));
        $this->assertSame(1, $count->invoke(null, '0'));
        $this->assertSame(64, $count->invoke(null, '0-63'));
        $this->assertNull($count->invoke(null, ''));
        $this->assertNull($count->invoke(null, '0-3,x'));
    }

    public function test_open_mp_variables_limit_the_core_count_like_nproc(): void {
        $limits = new ReflectionMethod(OsHelper::class, 'applyOpenMpLimits');
        $saved = [getenv('OMP_NUM_THREADS'), getenv('OMP_THREAD_LIMIT')];
        unset($_SERVER['OMP_NUM_THREADS'], $_SERVER['OMP_THREAD_LIMIT']);

        try {
            putenv('OMP_NUM_THREADS');
            putenv('OMP_THREAD_LIMIT');
            $this->assertSame(8, $limits->invoke(null, 8));

            putenv('OMP_NUM_THREADS=2,1');
            $this->assertSame(2, $limits->invoke(null, 8));

            putenv('OMP_THREAD_LIMIT=1');
            $this->assertSame(1, $limits->invoke(null, 8));

            putenv('OMP_NUM_THREADS=kaputt');
            putenv('OMP_THREAD_LIMIT');
            $this->assertSame(8, $limits->invoke(null, 8));
        } finally {
            putenv($saved[0] === false ? 'OMP_NUM_THREADS' : 'OMP_NUM_THREADS=' . $saved[0]);
            putenv($saved[1] === false ? 'OMP_THREAD_LIMIT' : 'OMP_THREAD_LIMIT=' . $saved[1]);
        }
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

    public function test_get_current_username_is_deterministic(): void {
        $this->assertSame(OsHelper::getCurrentUsername(), OsHelper::getCurrentUsername());
    }

    public function test_is_privileged_user_matches_posix_uid(): void {
        if (!OsHelper::isWindows() && function_exists('posix_getuid')) {
            $this->assertSame(posix_getuid() === 0, OsHelper::isPrivilegedUser());
        } else {
            // Auf Systemen ohne posix-uid-Prüfung nur Determinismus sicherstellen.
            $this->assertSame(OsHelper::isPrivilegedUser(), OsHelper::isPrivilegedUser());
        }
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
