<?php
/*
 * Created on   : Tue Mar 11 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileSystemException.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Exceptions;

use RuntimeException;
use Throwable;

class FileSystemException extends RuntimeException {
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
