<?php
/*
 * Created on   : Mon Dec 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhpUnitHelperTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ERRORToolkit\Helper\PhpUnitHelper;

class PhpUnitHelperTest extends TestCase {
    public function testIsRunningInPhpunit() {
        // Während wir PHPUnit Tests ausführen, sollte dies true zurückgeben
        $result = PhpUnitHelper::isRunningInPhpunit();
        $this->assertTrue($result, 'Should detect we are running in PHPUnit');
    }

    public function testSupportsColors() {
        $result = PhpUnitHelper::supportsColors();
        $this->assertIsBool($result, 'supportsColors should return a boolean');
    }

    public function testSupportsColorsWithNeverArgument() {
        // Simuliere --colors=never Argument
        $originalArgv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['phpunit', '--colors=never'];

        $result = PhpUnitHelper::supportsColors();
        $this->assertFalse($result, 'Should return false for --colors=never');

        // Wiederherstellen
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testSupportsColorsWithAlwaysArgument() {
        // Simuliere --colors=always Argument
        $originalArgv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['phpunit', '--colors=always'];

        $result = PhpUnitHelper::supportsColors();
        $this->assertTrue($result, 'Should return true for --colors=always');

        // Wiederherstellen
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }
}
