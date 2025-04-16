<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\Exception\FileException;
use AlexSkrypnyk\File\Exception\PatchException;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(PatchException::class)]
class PatchExceptionTest extends UnitTestCase {

  #[DataProvider('messageFormattingProvider')]
  public function testMessageFormatting(
    string $message,
    ?string $file_path,
    int|string|null $line_number,
    ?string $line_content,
    string $expected_message,
  ): void {
    $exception = new PatchException($message, $file_path, $line_number, $line_content);
    $this->assertEquals($expected_message, $exception->getMessage());
  }

  public static function messageFormattingProvider(): array {
    return [
      'message only' => [
        'Test message',
        NULL,
        NULL,
        NULL,
        'Test message.',
      ],
      'message with period' => [
        'Test message.',
        NULL,
        NULL,
        NULL,
        'Test message.',
      ],
      'message with file path' => [
        'Test message',
        '/path/to/file.txt',
        NULL,
        NULL,
        'Test message in file "/path/to/file.txt".',
      ],
      'message with line number' => [
        'Test message',
        NULL,
        42,
        NULL,
        'Test message on line 42.',
      ],
      'message with line content' => [
        'Test message',
        NULL,
        NULL,
        'Line content',
        'Test message: "Line content".',
      ],
      'message with file path and line number' => [
        'Test message',
        '/path/to/file.txt',
        42,
        NULL,
        'Test message in file "/path/to/file.txt" on line 42.',
      ],
      'message with file path and line content' => [
        'Test message',
        '/path/to/file.txt',
        NULL,
        'Line content',
        'Test message in file "/path/to/file.txt": "Line content".',
      ],
      'message with line number and line content' => [
        'Test message',
        NULL,
        42,
        'Line content',
        'Test message on line 42: "Line content".',
      ],
      'message with all details' => [
        'Test message',
        '/path/to/file.txt',
        42,
        'Line content',
        'Test message in file "/path/to/file.txt" on line 42: "Line content".',
      ],
      'empty message with details' => [
        '',
        '/path/to/file.txt',
        42,
        'Line content',
        'An error occurred in file "/path/to/file.txt" on line 42: "Line content".',
      ],
      'string line number' => [
        'Test message',
        '/path/to/file.txt',
        'ABC',
        'Line content',
        'Test message in file "/path/to/file.txt" on line ABC: "Line content".',
      ],
      'message with period and other details' => [
        'Test message.',
        '/path/to/file.txt',
        42,
        'Line content',
        'Test message in file "/path/to/file.txt" on line 42: "Line content".',
      ],
    ];
  }

  public function testGetters(): void {
    $file_path = '/path/to/file.txt';
    $line_number = 42;
    $line_content = 'Line content';

    $exception = new PatchException(
      'Test message',
      $file_path,
      $line_number,
      $line_content
    );

    $this->assertEquals($file_path, $exception->getFilePath());
    $this->assertEquals($line_number, $exception->getLineNumber());
    $this->assertEquals($line_content, $exception->getLineContent());
  }

  public function testExceptionInheritance(): void {
    $exception = new PatchException('Test message');
    $this->assertInstanceOf(\Exception::class, $exception);
    $this->assertInstanceOf(FileException::class, $exception);
  }

}
