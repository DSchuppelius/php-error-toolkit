<?php
/*
 * Created on   : Thu Apr 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExceptionsTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ERRORToolkit\Exceptions\FileSystemException;
use ERRORToolkit\Exceptions\InvalidPasswordException;
use ERRORToolkit\Exceptions\FileSystem\FileNotFoundException;
use ERRORToolkit\Exceptions\FileSystem\FileNotWrittenException;
use ERRORToolkit\Exceptions\FileSystem\FileExistsException;
use ERRORToolkit\Exceptions\FileSystem\FileInvalidException;
use ERRORToolkit\Exceptions\FileSystem\FolderNotFoundException;
use RuntimeException;
use Throwable;

class ExceptionsTest extends TestCase {
    /**
     * @return array<string, array{class-string<Throwable>}>
     */
    public static function exceptionClassProvider(): array {
        return [
            'FileSystemException' => [FileSystemException::class],
            'InvalidPasswordException' => [InvalidPasswordException::class],
            'FileNotFoundException' => [FileNotFoundException::class],
            'FileNotWrittenException' => [FileNotWrittenException::class],
            'FileExistsException' => [FileExistsException::class],
            'FileInvalidException' => [FileInvalidException::class],
            'FolderNotFoundException' => [FolderNotFoundException::class],
        ];
    }

    #[DataProvider('exceptionClassProvider')]
    public function testExceptionCanBeCreatedWithDefaults(string $exceptionClass): void {
        $exception = new $exceptionClass();
        $this->assertInstanceOf(Throwable::class, $exception);
        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    #[DataProvider('exceptionClassProvider')]
    public function testExceptionWithMessage(string $exceptionClass): void {
        $exception = new $exceptionClass('Testfehler');
        $this->assertSame('Testfehler', $exception->getMessage());
    }

    #[DataProvider('exceptionClassProvider')]
    public function testExceptionWithCode(string $exceptionClass): void {
        $exception = new $exceptionClass('Fehler', 42);
        $this->assertSame(42, $exception->getCode());
    }

    #[DataProvider('exceptionClassProvider')]
    public function testExceptionWithPrevious(string $exceptionClass): void {
        $previous = new RuntimeException('Ursache');
        $exception = new $exceptionClass('Fehler', 0, $previous);
        $this->assertSame($previous, $exception->getPrevious());
    }

    #[DataProvider('exceptionClassProvider')]
    public function testExceptionAcceptsThrowableAsPrevious(string $exceptionClass): void {
        // Throwable statt nur Exception - das ist der Fix den wir gemacht haben
        $previous = new \Error('Type Error');
        $exception = new $exceptionClass('Fehler', 0, $previous);
        $this->assertSame($previous, $exception->getPrevious());
    }

    #[DataProvider('exceptionClassProvider')]
    public function testExceptionIsThrowable(string $exceptionClass): void {
        try {
            throw new $exceptionClass('Test');
        } catch (Throwable $e) {
            $this->assertSame('Test', $e->getMessage());
            return;
        }
        $this->fail('Exception was not thrown');
    }

    public function testExceptionHierarchy(): void {
        // FileSystemException extends RuntimeException
        $this->assertInstanceOf(RuntimeException::class, new FileSystemException());

        // File*Exception extends FileSystemException
        $this->assertInstanceOf(FileSystemException::class, new FileNotFoundException());
        $this->assertInstanceOf(FileSystemException::class, new FileNotWrittenException());
        $this->assertInstanceOf(FileSystemException::class, new FileExistsException());
        $this->assertInstanceOf(FileSystemException::class, new FileInvalidException());

        // FolderNotFoundException extends FileNotFoundException
        $this->assertInstanceOf(FileNotFoundException::class, new FolderNotFoundException());

        // InvalidPasswordException extends RuntimeException (nicht FileSystemException)
        $this->assertInstanceOf(RuntimeException::class, new InvalidPasswordException());
        $this->assertNotInstanceOf(FileSystemException::class, new InvalidPasswordException());
    }
}
