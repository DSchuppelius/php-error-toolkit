<?php
/*
 * Created on   : Sun Jan 26 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileNotFoundException.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Exceptions\FileSystem;

use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

class FileNotFoundException extends RuntimeException {

    public function __construct($message = '', int $code = 0, $response = null, ?Exception $previous = null, ?LoggerInterface $logger = null) {
        parent::__construct($message, $code, $response, $previous, $logger);
    }
}
