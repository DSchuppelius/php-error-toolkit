<?php
/*
 * Created on   : Mon Mar 10 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FolderNotFoundException.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Exceptions\FileSystem;

use Throwable;

final class FolderNotFoundException extends FileNotFoundException {

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
