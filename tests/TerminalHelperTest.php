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
use ReflectionMethod;

class TerminalHelperTest extends TestCase {
    public function test_is_debug_console(): void {
        $this->expectNotToPerformAssertions();
        TerminalHelper::isDebugConsole();
    }

    public function test_is_terminal(): void {
        $this->expectNotToPerformAssertions();
        TerminalHelper::isTerminal();
    }

    public function test_supports_colors(): void {
        $this->expectNotToPerformAssertions();
        TerminalHelper::supportsColors();
    }

    public function test_get_terminal_width(): void {
        $result = TerminalHelper::getTerminalWidth();
        $this->assertGreaterThan(0, $result);
    }

    public function test_get_terminal_height(): void {
        $result = TerminalHelper::getTerminalHeight();
        $this->assertGreaterThan(0, $result);
    }

    public function test_size_query_reads_the_number_from_the_program_output(): void {
        $query = new ReflectionMethod(TerminalHelper::class, 'querySize');

        // Wie `mode con` unter Windows: die Zahl steht hinter dem Schluesselwort
        $this->assertSame(132, $query->invoke(null, [PHP_BINARY, '-r', 'echo "  Lines: 50\n  Columns: 132\n";'], '/Columns:\s*(\d+)/'));
        // Wie `tput cols`
        $this->assertSame(120, $query->invoke(null, [PHP_BINARY, '-r', 'echo "120\n";'], '/^(\d+)$/'));
        // 0, kein Treffer oder Fehlschlag zaehlen nicht
        $this->assertNull($query->invoke(null, [PHP_BINARY, '-r', 'echo "0\n";'], '/^(\d+)$/'));
        $this->assertNull($query->invoke(null, [PHP_BINARY, '-r', 'echo "keine Zahl";'], '/^(\d+)$/'));
        $this->assertNull($query->invoke(null, [PHP_BINARY, '-r', 'echo "80"; exit(1);'], '/^(\d+)$/'));
    }
}
