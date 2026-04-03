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

use PHPUnit\Framework\TestCase;
use ERRORToolkit\Enums\LogType;
use InvalidArgumentException;

class LogTypeTest extends TestCase {
    public function testFromStringConsole(): void {
        $this->assertSame(LogType::CONSOLE, LogType::fromString('console'));
        $this->assertSame(LogType::CONSOLE, LogType::fromString('Console'));
        $this->assertSame(LogType::CONSOLE, LogType::fromString('CONSOLE'));
    }

    public function testFromStringFile(): void {
        $this->assertSame(LogType::FILE, LogType::fromString('file'));
        $this->assertSame(LogType::FILE, LogType::fromString('File'));
        $this->assertSame(LogType::FILE, LogType::fromString('FILE'));
    }

    public function testFromStringNull(): void {
        $this->assertSame(LogType::NULL, LogType::fromString('null'));
        $this->assertSame(LogType::NULL, LogType::fromString('Null'));
        $this->assertSame(LogType::NULL, LogType::fromString('NULL'));
    }

    public function testFromStringInvalidThrows(): void {
        $this->expectException(InvalidArgumentException::class);
        LogType::fromString('invalid');
    }

    public function testEnumValues(): void {
        $this->assertSame('console', LogType::CONSOLE->value);
        $this->assertSame('file', LogType::FILE->value);
        $this->assertSame('null', LogType::NULL->value);
    }

    public function testAllCasesExist(): void {
        $cases = LogType::cases();
        $this->assertCount(3, $cases);
    }
}
