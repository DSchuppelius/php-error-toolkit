<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoggerFactoryInterface.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Contracts\Interfaces;

use Psr\Log\LoggerInterface;

interface LoggerFactoryInterface {
    public static function getLogger(): LoggerInterface;
}
