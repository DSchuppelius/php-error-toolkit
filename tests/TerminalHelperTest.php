<?php
/*
 * Created on   : Mon Dec 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TerminalHelperTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ERRORToolkit\Helper\TerminalHelper;

class TerminalHelperTest extends TestCase {
    public function testIsDebugConsole() {
        $result = TerminalHelper::isDebugConsole();
        $this->assertIsBool($result);
    }

    public function testIsTerminal() {
        $result = TerminalHelper::isTerminal();
        $this->assertIsBool($result);
    }

    public function testSupportsColors() {
        $result = TerminalHelper::supportsColors();
        $this->assertIsBool($result);
    }

    public function testGetTerminalWidth() {
        $result = TerminalHelper::getTerminalWidth();
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testGetTerminalHeight() {
        $result = TerminalHelper::getTerminalHeight();
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testIsNewline() {
        $result = TerminalHelper::isNewline();
        $this->assertIsBool($result);
    }
}