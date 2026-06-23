<?php
/*
 * Created on   : Thu Apr 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LogTypeTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use ERRORToolkit\Enums\LogType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LogTypeTest extends TestCase {
    public function test_from_string_console(): void {
        $this->assertSame(LogType::CONSOLE, LogType::fromString('console'));
        $this->assertSame(LogType::CONSOLE, LogType::fromString('Console'));
        $this->assertSame(LogType::CONSOLE, LogType::fromString('CONSOLE'));
    }

    public function test_from_string_file(): void {
        $this->assertSame(LogType::FILE, LogType::fromString('file'));
        $this->assertSame(LogType::FILE, LogType::fromString('File'));
        $this->assertSame(LogType::FILE, LogType::fromString('FILE'));
    }

    public function test_from_string_null(): void {
        $this->assertSame(LogType::NULL, LogType::fromString('null'));
        $this->assertSame(LogType::NULL, LogType::fromString('Null'));
        $this->assertSame(LogType::NULL, LogType::fromString('NULL'));
    }

    public function test_from_string_invalid_throws(): void {
        $this->expectException(InvalidArgumentException::class);
        LogType::fromString('invalid');
    }

    public function test_enum_values(): void {
        $this->assertSame('console', LogType::CONSOLE->value);
        $this->assertSame('file', LogType::FILE->value);
        $this->assertSame('null', LogType::NULL->value);
    }

    public function test_all_cases_exist(): void {
        $cases = LogType::cases();
        $this->assertCount(3, $cases);
    }
}
