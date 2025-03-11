<?php
/*
 * Created on   : Sun Jan 26 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileNotWrittenException.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Exceptions\FileSystem;

use ERRORToolkit\Exceptions\FileSystemException;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class FileNotWrittenException extends FileSystemException {

    public function __construct($message = '', int $code = 0, $response = null, ?Exception $previous = null, ?LoggerInterface $logger = null) {
        parent::__construct($message, $code, $response, $previous, $logger);
    }
}
