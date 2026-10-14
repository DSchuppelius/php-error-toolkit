<?php
/*
 * Created on   : Wed Oct 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessHelper.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Helper;

use InvalidArgumentException;

/**
 * Startet kurze Hilfsprogramme (stty, tput, sysctl, nproc ...) ohne Shell und
 * mit Zeitgrenze.
 *
 * Das error-toolkit liegt unter dem php-common-toolkit und kann dessen
 * Shell-Klasse nicht benutzen; dieser Helper deckt nur den kleinen Bedarf hier:
 * - Programm und Argumente als Liste (proc_open ohne Shell-Interpreter): kein
 *   Quoting, keine Injektion ueber Argumente.
 * - stdout wird mit Frist gelesen; nach Ablauf geht SIGTERM an das Programm,
 *   nach einer kurzen Nachfrist SIGKILL. Gedacht fuer Programme ohne eigene
 *   Kindprozesse - Enkel werden nicht verfolgt.
 * - stderr geht ins Leere (wie `2>/dev/null`), die Standardeingabe ist leer
 *   oder auf Wunsch geerbt (stty und tput lesen das Terminal daraus).
 */
final class ProcessHelper {
    /** Zeitgrenze in Sekunden, wenn der Aufrufer keine angibt. */
    public const DEFAULT_TIMEOUT = 5.0;

    /** Exit-Code des Kindprozesses, wenn exec vor PHP 8.3 scheitert (Programm fehlt). */
    private const EXIT_EXEC_FAILED = 127;

    /** Nachfrist zwischen SIGTERM und SIGKILL. */
    private const KILL_GRACE = 1.0;

    /** Wartezeit je Runde beim Lesen und Warten. */
    private const POLL_MICROSECONDS = 10000;

    /** Hoechstens so viel stdout wird gesammelt; der Rest wird gelesen und verworfen. */
    private const MAX_OUTPUT_BYTES = 1048576;

    /**
     * Ob proc_open() verfuegbar ist (auf gehaerteten Hosts per disable_functions gesperrt).
     */
    public static function canStartProcesses(): bool {
        if (!function_exists('proc_open')) {
            return false;
        }

        $disabled = strtolower((string) ini_get('disable_functions'));
        if ($disabled === '') {
            return true;
        }

        return !in_array('proc_open', array_map('trim', explode(',', $disabled)), true);
    }

    /**
     * Startet ein Programm mit Argumentliste und wartet hoechstens $timeout Sekunden.
     *
     * @param list<string> $command Programm und Argumente, z. B. ['stty', '-g'].
     * @param float $timeout Sekunden bis zum Abbruch (positiv, endlich).
     * @param bool $inheritStdin true: das Programm erbt die Standardeingabe des PHP-Prozesses.
     * @return array{exitCode: int|null, output: string, timedOut: bool}|null null, wenn das
     *         Programm nicht startet (nicht vorhanden, proc_open gesperrt); exitCode null nach
     *         Abbruch oder Signal.
     * @throws InvalidArgumentException Bei nicht positiver oder nicht endlicher Zeitgrenze.
     */
    public static function run(array $command, float $timeout = self::DEFAULT_TIMEOUT, bool $inheritStdin = false): ?array {
        if (!is_finite($timeout) || $timeout <= 0.0) {
            throw new InvalidArgumentException('Die Zeitgrenze muss eine positive, endliche Zahl sein.');
        }
        if ($command === [] || trim($command[0]) === '' || !self::canStartProcesses()) {
            return null;
        }

        $windows = OsHelper::isWindows();
        // Nur Pipes, keine Dateien: /dev/null laege unter open_basedir ausserhalb
        // der erlaubten Pfade.
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        if (!$inheritStdin) {
            $descriptors[0] = ['pipe', 'r'];
        }

        $pipes = [];
        // Mit Argumentliste startet PHP das Programm direkt, ohne /bin/sh bzw. cmd.exe
        $process = @proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            return null;
        }

        if (isset($pipes[0])) {
            // Leere Standardeingabe
            fclose($pipes[0]);
        }
        $stdout = $pipes[1];
        $stderr = $pipes[2];
        // Unter Windows blockieren Pipes trotzdem und stream_select() kennt sie nicht:
        // dort wird erst nach dem Ende des Programms gelesen.
        $canPoll = !$windows && stream_set_blocking($stdout, false) && stream_set_blocking($stderr, false);
        $deadline = microtime(true) + $timeout;
        $output = '';
        $exitCode = null;
        $timedOut = false;

        while (true) {
            if ($canPoll) {
                self::append($output, self::drain($stdout));
                // stderr wird gelesen und verworfen (wie 2>/dev/null), damit das Programm nicht haengt
                self::drain($stderr);
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                // exitcode ist nur beim ersten Abruf nach dem Ende gueltig, -1 nach einem Signal
                $exitCode = $status['exitcode'] >= 0 ? $status['exitcode'] : null;
                break;
            }
            if (microtime(true) >= $deadline) {
                $timedOut = true;
                break;
            }

            self::wait($canPoll ? [$stdout, $stderr] : []);
        }

        $finished = true;
        if ($timedOut) {
            $finished = self::terminate($process);
        } else {
            self::append($output, $canPoll ? self::drain($stdout) : (string) stream_get_contents($stdout));
        }

        fclose($stdout);
        fclose($stderr);
        if ($finished) {
            proc_close($process);
        }
        // Sonst raeumt PHP die Ressource ohne blockierendes Warten ab.

        // Vor PHP 8.3 startet proc_open per fork/exec: Fehlt das Programm,
        // "gelingt" der Start, und der Kindprozess endet still mit 127 (exec
        // gescheitert). Ab 8.3 (posix_spawn) scheitert schon proc_open. Beide
        // Faelle heissen hier gleich: das Programm startet nicht.
        if (PHP_VERSION_ID < 80300 && !$windows && $exitCode === self::EXIT_EXEC_FAILED && $output === '') {
            return null;
        }

        return ['exitCode' => $exitCode, 'output' => $output, 'timedOut' => $timedOut];
    }

    /**
     * Die Standardausgabe eines Programms, wenn es mit Exit-Code 0 innerhalb der
     * Zeitgrenze endet, sonst null.
     *
     * @param list<string> $command Programm und Argumente.
     * @throws InvalidArgumentException Bei nicht positiver oder nicht endlicher Zeitgrenze.
     */
    public static function output(array $command, float $timeout = self::DEFAULT_TIMEOUT, bool $inheritStdin = false): ?string {
        $result = self::run($command, $timeout, $inheritStdin);

        return $result !== null && $result['exitCode'] === 0 ? $result['output'] : null;
    }

    /**
     * Liest, was gerade in der (nicht blockierenden) Pipe steht.
     *
     * @param resource $stream
     */
    private static function drain($stream): string {
        $data = '';
        while (($chunk = fread($stream, 8192)) !== false && $chunk !== '') {
            $data .= $chunk;
        }

        return $data;
    }

    /**
     * Haengt Ausgabe an, hoechstens MAX_OUTPUT_BYTES insgesamt.
     */
    private static function append(string &$output, string $chunk): void {
        $room = self::MAX_OUTPUT_BYTES - strlen($output);
        if ($room > 0 && $chunk !== '') {
            $output .= substr($chunk, 0, $room);
        }
    }

    /**
     * Wartet bis Daten anliegen oder eine Runde vorbei ist.
     *
     * @param list<resource> $streams
     */
    private static function wait(array $streams): void {
        $read = array_values(array_filter($streams, static fn ($stream): bool => !feof($stream)));
        if ($read !== []) {
            $write = null;
            $except = null;
            if (@stream_select($read, $write, $except, 0, self::POLL_MICROSECONDS) !== false) {
                return;
            }
        }

        usleep(self::POLL_MICROSECONDS);
    }

    /**
     * Beendet das Programm: SIGTERM, nach der Nachfrist SIGKILL.
     *
     * @param resource $process
     * @return bool Ob das Programm beendet ist.
     */
    private static function terminate($process): bool {
        foreach ([15, 9] as $signal) {
            @proc_terminate($process, $signal);
            $graceEnd = microtime(true) + self::KILL_GRACE;
            do {
                if (!proc_get_status($process)['running']) {
                    return true;
                }
                usleep(self::POLL_MICROSECONDS);
            } while (microtime(true) < $graceEnd);
        }

        return false;
    }
}
