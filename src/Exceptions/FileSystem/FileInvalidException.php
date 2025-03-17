<?php
/*
 * Created on   : Tue Mar 11 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileInvalidException.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Exceptions\FileSystem;

use ERRORToolkit\Exceptions\FileSystemException;
use Exception;

final class FileInvalidException extends FileSystemException {

    public function __construct($message = '', int $code = 0, ?Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}