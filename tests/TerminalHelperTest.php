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

use ERRORToolkit\Helper\TerminalHelper;
use PHPUnit\Framework\TestCase;

class TerminalHelperTest extends TestCase {
    public function test_is_debug_console() {
        $this->expectNotToPerformAssertions();
        TerminalHelper::isDebugConsole();
    }

    public function test_is_terminal() {
        $this->expectNotToPerformAssertions();
        TerminalHelper::isTerminal();
    }

    public function test_supports_colors() {
        $this->expectNotToPerformAssertions();
        TerminalHelper::supportsColors();
    }

    public function test_get_terminal_width() {
        $result = TerminalHelper::getTerminalWidth();
        $this->assertGreaterThan(0, $result);
    }

    public function test_get_terminal_height() {
        $result = TerminalHelper::getTerminalHeight();
        $this->assertGreaterThan(0, $result);
    }
}
