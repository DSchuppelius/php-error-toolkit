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

use PHPUnit\Framework\TestCase;
use ERRORToolkit\Helper\OsHelper;

class OsHelperTest extends TestCase {
    public function testIsWindowsReturnsBool(): void {
        $this->assertIsBool(OsHelper::isWindows());
    }

    public function testIsLinuxReturnsBool(): void {
        $this->assertIsBool(OsHelper::isLinux());
    }

    public function testIsMacOSReturnsBool(): void {
        $this->assertIsBool(OsHelper::isMacOS());
    }

    public function testIsUnixReturnsBool(): void {
        $this->assertIsBool(OsHelper::isUnix());
    }

    public function testGetOsNameReturnsNonEmpty(): void {
        $name = OsHelper::getOsName();
        $this->assertNotEmpty($name);
        $this->assertContains($name, ['Windows', 'Linux', 'macOS', PHP_OS]);
    }

    public function testGetPathSeparator(): void {
        $sep = OsHelper::getPathSeparator();
        $this->assertContains($sep, ['/', '\\']);
    }

    public function testGetEnvPathSeparator(): void {
        $sep = OsHelper::getEnvPathSeparator();
        $this->assertContains($sep, [':', ';']);
    }

    public function testGetHomeDirectoryReturnsString(): void {
        $home = OsHelper::getHomeDirectory();
        $this->assertIsString($home);
    }

    public function testGetTempDirectory(): void {
        $tmp = OsHelper::getTempDirectory();
        $this->assertNotEmpty($tmp);
        $this->assertDirectoryExists($tmp);
    }

    public function testGetEnvWithExistingVariable(): void {
        $_SERVER['TEST_OSHELPER_VAR'] = 'test_value';
        $this->assertSame('test_value', OsHelper::getEnv('TEST_OSHELPER_VAR'));
        unset($_SERVER['TEST_OSHELPER_VAR']);
    }

    public function testGetEnvWithDefault(): void {
        $result = OsHelper::getEnv('NONEXISTENT_VAR_XYZABC', 'default');
        $this->assertSame('default', $result);
    }

    public function testGetEnvReturnsNullWithoutDefault(): void {
        $result = OsHelper::getEnv('NONEXISTENT_VAR_XYZABC');
        $this->assertNull($result);
    }

    public function testSetEnv(): void {
        $result = OsHelper::setEnv('TEST_OSHELPER_SET', 'hello');
        $this->assertTrue($result);
        $this->assertSame('hello', OsHelper::getEnv('TEST_OSHELPER_SET'));

        // Cleanup
        putenv('TEST_OSHELPER_SET');
        unset($_SERVER['TEST_OSHELPER_SET']);
    }

    public function testIsExecutableWithNonExistentFile(): void {
        $this->assertFalse(OsHelper::isExecutable('/nonexistent/path/to/file'));
    }

    public function testFindExecutableWithNonExistentCommand(): void {
        $this->assertNull(OsHelper::findExecutable('nonexistent_command_xyzabc'));
    }

    public function testGetCpuCoreCountReturnsPositive(): void {
        $cores = OsHelper::getCpuCoreCount();
        $this->assertGreaterThanOrEqual(1, $cores);
    }

    public function testGetArchitectureReturnsNonEmpty(): void {
        $arch = OsHelper::getArchitecture();
        $this->assertNotEmpty($arch);
    }

    public function testGetKernelVersionReturnsNonEmpty(): void {
        $version = OsHelper::getKernelVersion();
        $this->assertNotEmpty($version);
    }

    public function testGetSystemInfoReturnsExpectedKeys(): void {
        $info = OsHelper::getSystemInfo();
        $this->assertArrayHasKey('os', $info);
        $this->assertArrayHasKey('php_os', $info);
        $this->assertArrayHasKey('architecture', $info);
        $this->assertArrayHasKey('kernel', $info);
        $this->assertArrayHasKey('hostname', $info);
    }

    public function testGetCurrentUsernameReturnsString(): void {
        $username = OsHelper::getCurrentUsername();
        $this->assertIsString($username);
    }

    public function testIsPrivilegedUserReturnsBool(): void {
        $this->assertIsBool(OsHelper::isPrivilegedUser());
    }

    public function testOsDetectionConsistency(): void {
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
