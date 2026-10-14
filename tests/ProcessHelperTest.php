<?php
/*
 * Created on   : Wed Oct 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessHelperTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use ERRORToolkit\Helper\{OsHelper, ProcessHelper};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProcessHelperTest extends TestCase {
    public function test_run_returns_output_and_exit_code_without_a_shell(): void {
        $argument = 'a b;c $(x) `y` | z > /tmp/nie';
        $result = ProcessHelper::run([PHP_BINARY, '-r', 'echo $argv[1]; exit(3);', $argument]);

        $this->assertNotNull($result);
        $this->assertSame(3, $result['exitCode']);
        $this->assertSame($argument, $result['output'], 'Argumente gehen ohne Shell unveraendert durch');
        $this->assertFalse($result['timedOut']);
    }

    public function test_stderr_is_discarded_and_stdin_is_empty_by_default(): void {
        $result = ProcessHelper::run([PHP_BINARY, '-r', 'fwrite(STDERR, "fehler"); echo "[" . stream_get_contents(STDIN) . "]";']);

        $this->assertNotNull($result);
        $this->assertSame('[]', $result['output']);
        $this->assertSame(0, $result['exitCode']);
    }

    public function test_timeout_terminates_the_process(): void {
        $started = microtime(true);
        $result = ProcessHelper::run([PHP_BINARY, '-r', 'sleep(5);'], 0.5);

        $this->assertNotNull($result);
        $this->assertTrue($result['timedOut']);
        $this->assertNull($result['exitCode']);
        $this->assertLessThan(3.0, microtime(true) - $started);
    }

    public function test_process_ignoring_sigterm_is_killed_after_the_grace_period(): void {
        if (OsHelper::isWindows() || OsHelper::findExecutable('sh') === null) {
            $this->markTestSkipped('Signale nur unter Unix.');
        }
        $started = microtime(true);

        // Ignorierte Signale bleiben ueber exec hinweg ignoriert: sleep reagiert nicht auf SIGTERM
        $result = ProcessHelper::run(['sh', '-c', 'trap "" TERM; exec sleep 5'], 0.5);

        $this->assertNotNull($result);
        $this->assertTrue($result['timedOut']);
        $this->assertLessThan(4.0, microtime(true) - $started);
    }

    public function test_large_output_does_not_block_and_is_capped(): void {
        $result = ProcessHelper::run([PHP_BINARY, '-r', 'echo str_repeat("x", 3 * 1048576);'], 10.0);

        $this->assertNotNull($result);
        $this->assertSame(0, $result['exitCode']);
        $this->assertFalse($result['timedOut']);
        $this->assertSame(1048576, strlen($result['output']));
    }

    public function test_missing_program_returns_null(): void {
        $this->assertNull(ProcessHelper::run(['gibt-es-nicht-' . bin2hex(random_bytes(4))]));
        $this->assertNull(ProcessHelper::run([]));
    }

    public function test_output_only_on_success(): void {
        $this->assertSame('ok', ProcessHelper::output([PHP_BINARY, '-r', 'echo "ok";']));
        $this->assertNull(ProcessHelper::output([PHP_BINARY, '-r', 'echo "teilweise"; exit(1);']));
        $this->assertNull(ProcessHelper::output([PHP_BINARY, '-r', 'sleep(5);'], 0.3));
    }

    /**
     * @return array<string, array{0: float}>
     */
    public static function invalidTimeouts(): array {
        return ['null' => [0.0], 'negativ' => [-1.0], 'unendlich' => [INF], 'nan' => [NAN]];
    }

    #[DataProvider('invalidTimeouts')]
    public function test_invalid_timeout_is_rejected(float $timeout): void {
        $this->expectException(InvalidArgumentException::class);
        ProcessHelper::run([PHP_BINARY, '-r', 'echo 1;'], $timeout);
    }

    /**
     * Prozesse startet im Toolkit nur ProcessHelper - ohne Shell und mit Zeitgrenze.
     */
    public function test_no_other_class_starts_processes_or_shells(): void {
        $forbidden = ['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open', 'pcntl_exec'];
        $findings = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__) . '/src', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            $tokens = token_get_all($source);
            foreach ($tokens as $index => $token) {
                if ($token === '`') {
                    $findings[] = $file->getFilename() . ': Backtick';
                    continue;
                }
                if (!is_array($token) || !in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }
                $name = strtolower(ltrim($token[1], '\\'));
                if (!in_array($name, $forbidden, true) || !$this->isFunctionCall($tokens, $index)) {
                    continue;
                }
                if (!($name === 'proc_open' && $file->getFilename() === 'ProcessHelper.php')) {
                    $findings[] = $file->getFilename() . ':' . $token[2] . ' ' . $name . '()';
                }
            }
        }

        $this->assertSame([], $findings);
    }

    /**
     * Ein Name ist ein Funktionsaufruf, wenn "(" folgt und kein "->", "::" oder "function" davorsteht.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function isFunctionCall(array $tokens, int $index): bool {
        $next = $index + 1;
        while (isset($tokens[$next]) && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
            $next++;
        }
        if (($tokens[$next] ?? null) !== '(') {
            return false;
        }

        $previous = $index - 1;
        while (isset($tokens[$previous]) && is_array($tokens[$previous]) && $tokens[$previous][0] === T_WHITESPACE) {
            $previous--;
        }
        $before = $tokens[$previous] ?? null;

        return !(is_array($before) && in_array($before[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true));
    }
}
