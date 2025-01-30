<?php
/*
 * Created on   : Fri Oct 18 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LogType.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Enums;

use InvalidArgumentException;

enum LogType: string {
    case CONSOLE = 'console';
    case FILE = 'file';
    case NULL = 'null';

    public static function fromString(string $value): LogType {
        return match (strtolower($value)) {
            'console' => self::CONSOLE,
            'file' => self::FILE,
            'null' => self::NULL,
            default => throw new InvalidArgumentException("Invalid log type: $value"),
        };
    }
}
