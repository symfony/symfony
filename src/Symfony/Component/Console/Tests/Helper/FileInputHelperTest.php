<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidFileException;
use Symfony\Component\Console\Helper\FileInputHelper;
use Symfony\Component\Console\Helper\TerminalInputHelper;
use Symfony\Component\Console\Input\File\InputFile;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Question\FileQuestion;

class FileInputHelperTest extends TestCase
{
    public function testDisplayFileEscapesFormatterMetaCharactersInPath()
    {
        $name = 'sf_console_<error>bad<error>_'.bin2hex(random_bytes(4)).'.txt';
        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.$name;

        if (false === @file_put_contents($path, 'x')) {
            $this->markTestSkipped('Filesystem does not allow "<" / ">" in filenames.');
        }

        try {
            $file = new InputFile($path);
            $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);

            (new FileInputHelper())->displayFile($output, $file);

            $display = $output->fetch();
            $this->assertStringContainsString('<error>bad<error>', $display);
            $this->assertStringNotContainsString("\e[37;41m", $display);
            $this->assertStringNotContainsString("\e[39;49m", $display);
        } finally {
            @unlink($path);
        }
    }

    public function testReadWithPasteDetectionAbortsBeyondMaxBytes()
    {
        $cap = (new \ReflectionClassConstant(FileInputHelper::class, 'MAX_PASTE_BYTES'))->getValue();

        $stream = fopen('php://memory', 'r+');
        $chunk = str_repeat('A', 64 * 1024);
        $written = 0;
        while ($written <= $cap) {
            $written += fwrite($stream, $chunk);
        }
        rewind($stream);

        $helper = new FileInputHelper();
        $method = (new \ReflectionClass(FileInputHelper::class))->getMethod('readWithPasteDetection');

        $terminalReflection = new \ReflectionClass(TerminalInputHelper::class);
        $inputHelper = $terminalReflection->newInstanceWithoutConstructor();
        foreach (['isStdin' => false, 'withStty' => false] as $name => $value) {
            $terminalReflection->getProperty($name)->setValue($inputHelper, $value);
        }

        $this->expectException(InvalidFileException::class);
        $this->expectExceptionMessage('Pasted input exceeds the maximum allowed size');

        try {
            $method->invoke($helper, $stream, new BufferedOutput(), new FileQuestion('?'), $inputHelper);
        } finally {
            fclose($stream);
        }
    }

    public function testReadWithPasteDetectionReassemblesPasteSpanningMultipleReadChunks()
    {
        $pasteStart = (new \ReflectionClassConstant(FileInputHelper::class, 'PASTE_START'))->getValue();
        $pasteEnd = (new \ReflectionClassConstant(FileInputHelper::class, 'PASTE_END'))->getValue();

        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sf_console_'.bin2hex(random_bytes(4)).'.txt';
        file_put_contents($path, 'x');

        // Pad so that the path itself straddles the internal 8192-byte read buffer: part of
        // it lands in the first fread() call, the rest in the second. If the buffered read
        // dropped or corrupted bytes at the chunk boundary, the reassembled path would differ
        // from the original and the assertion below would fail.
        $padding = str_repeat(' ', 8192 - \strlen($pasteStart) - 5);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $pasteStart.$padding.$path.$pasteEnd);
        rewind($stream);

        $helper = new FileInputHelper();
        $method = (new \ReflectionClass(FileInputHelper::class))->getMethod('readWithPasteDetection');

        $terminalReflection = new \ReflectionClass(TerminalInputHelper::class);
        $inputHelper = $terminalReflection->newInstanceWithoutConstructor();
        foreach (['isStdin' => false, 'withStty' => false] as $name => $value) {
            $terminalReflection->getProperty($name)->setValue($inputHelper, $value);
        }

        try {
            $file = $method->invoke($helper, $stream, new BufferedOutput(), new FileQuestion('?'), $inputHelper);
            $this->assertSame($path, $file->getPathname());
        } finally {
            fclose($stream);
            @unlink($path);
        }
    }
}
