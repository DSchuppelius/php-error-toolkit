<?php
/*
 * Created on   : Sun Oct 12 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvalidPasswordException.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Exceptions;

use Exception;
use RuntimeException;

class InvalidPasswordException extends RuntimeException {
    public function __construct($message = '', int $code = 0, ?Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
