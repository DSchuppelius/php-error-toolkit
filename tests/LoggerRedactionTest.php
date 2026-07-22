<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoggerRedactionTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use ERRORToolkit\Logger\ConsoleLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LoggerRedactionTest extends TestCase {
    /** @return resource */
    private static function createStream() {
        return fopen('php://memory', 'r+');
    }

    private static function readStream($stream): string {
        rewind($stream);
        return stream_get_contents($stream) ?: '';
    }

    private function logger($stream): ConsoleLogger {
        return new ConsoleLogger(LogLevel::DEBUG, enableDeduplication: false, stream: $stream);
    }

    public function test_sensitive_context_keys_are_redacted(): void {
        $stream = self::createStream();
        $this->logger($stream)->log(LogLevel::ERROR, 'request failed', [
            'access_token' => 'eyJsecret',
            'client_secret' => 'topsecret',
            'password' => 'hunter2',
            'harmless' => 'keep-me',
        ]);
        $output = self::readStream($stream);

        $this->assertStringNotContainsString('eyJsecret', $output);
        $this->assertStringNotContainsString('topsecret', $output);
        $this->assertStringNotContainsString('hunter2', $output);
        $this->assertStringContainsString('[redacted]', $output);
        $this->assertStringContainsString('keep-me', $output);
    }

    public function test_nested_response_headers_are_redacted(): void {
        $stream = self::createStream();
        $this->logger($stream)->log(LogLevel::WARNING, 'api error', [
            'status_code' => 401,
            'response_headers' => [
                'Set-Cookie' => ['SESSIONID=abc123; HttpOnly'],
                'Authorization' => ['Bearer eyJleak'],
                'Content-Type' => ['application/json'],
            ],
        ]);
        $output = self::readStream($stream);

        $this->assertStringNotContainsString('abc123', $output);
        $this->assertStringNotContainsString('eyJleak', $output);
        $this->assertStringContainsString('application', $output); // non-sensitive header kept (slash is json-escaped)
    }

    public function test_redaction_is_case_insensitive(): void {
        $stream = self::createStream();
        $this->logger($stream)->log(LogLevel::INFO, 'x', ['Authorization' => 'Bearer nope', 'API_KEY' => 'k-123']);
        $output = self::readStream($stream);

        $this->assertStringNotContainsString('nope', $output);
        $this->assertStringNotContainsString('k-123', $output);
    }

    public function test_crlf_in_message_cannot_forge_a_second_log_line(): void {
        $stream = self::createStream();
        $forged = "admin\n[2026-01-01 00:00:00] emergency [x]: root shell opened";
        $this->logger($stream)->log(LogLevel::ERROR, 'Login failed for {user}', ['user' => $forged]);
        $output = self::readStream($stream);

        // The CR/LF is stripped, so no independent forged line survives.
        $this->assertStringNotContainsString("\n[2026-01-01 00:00:00] emergency", $output);
        $this->assertStringContainsString('root shell opened', $output); // text kept, newline removed
        // Exactly one log line (trailing newline from writeLog aside).
        $this->assertSame(1, substr_count(rtrim($output, "\n"), "\n") + 1);
    }

    public function test_ansi_escape_in_message_is_neutralized(): void {
        $stream = self::createStream();
        $this->logger($stream)->log(LogLevel::ERROR, "danger \033[31mRED\033[0m", []);
        $output = self::readStream($stream);

        // The ESC (0x1B) byte injected via the message must be gone.
        $this->assertStringNotContainsString("\x1b[31m", $output);
        $this->assertStringContainsString('RED', $output);
    }
}
