<?php
/*
 * Created on   : Tue Mar 11 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileExistsException.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Exceptions\FileSystem;

use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class FileExistsException extends RuntimeException {

    public function __construct($message = '', int $code = 0, $response = null, ?Exception $previous = null, ?LoggerInterface $logger = null) {
        parent::__construct($message, $code, $response, $previous, $logger);
    }
}