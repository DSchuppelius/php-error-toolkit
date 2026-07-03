<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : error-toolkit.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Log-Channel für Toolkit-Logs
    |--------------------------------------------------------------------------
    |
    | Laravel-Log-Channel, in den alle über ERRORToolkit\LoggerRegistry
    | laufenden Toolkit-Logs (php-error-toolkit, php-common-toolkit,
    | php-api-toolkit, SDKs) geschrieben werden. null verwendet den
    | Default-Channel der Anwendung.
    |
    */
    'channel' => env('ERROR_TOOLKIT_LOG_CHANNEL'),
];
