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

    public function testDisplayFileStripsTerminalControlCharactersFromName()
    {
        $marker = bin2hex(random_bytes(4));
        // ESC + OSC "set window title" + BEL smuggled into the file name
        $name = "sf_console_{$marker}_\x1b]0;HIJACK\x07.txt";
        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.$name;

        if (false === @file_put_contents($path, 'x')) {
            $this->markTestSkipped('Filesystem does not allow control characters in filenames.');
        }

        try {
            $file = new InputFile($path);
            $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);

            (new FileInputHelper())->displayFile($output, $file);

            $display = $output->fetch();

            // The injected escape sequence must not survive into terminal output...
            $this->assertStringNotContainsString("\x1b]0;", $display);
            $this->assertStringNotContainsString("\x07", $display);
            // ...while the printable remainder of the name is still shown.
            $this->assertStringContainsString('HIJACK', $display);
            $this->assertStringContainsString($marker, $display);
        } finally {
            @unlink($path);
        }
    }

    public function testDisplayFileStripsControlCharactersReassembledAfterAFirstPass()
    {
        $marker = bin2hex(random_bytes(4));
        // Removing the ESC splices "\xc2" and "\x9b" into "\xc2\x9b", the UTF-8
        // encoding of U+009B (CSI), which a single stripping pass would leave behind.
        $name = "sf_console_{$marker}_\xc2\x1b\x9b.txt";
        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.$name;

        if (false === @file_put_contents($path, 'x')) {
            $this->markTestSkipped('Filesystem does not allow control characters in filenames.');
        }

        try {
            $file = new InputFile($path);
            $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);

            (new FileInputHelper())->displayFile($output, $file);

            $display = $output->fetch();

            // A single stripping pass would leave the spliced "\xc2\x9b" (U+009B) behind;
            // the loop must remove it. (The legitimate OSC 8 link the formatter emits does
            // contain ESC, so a bare "\x1b" assertion would be wrong here.)
            $this->assertStringNotContainsString("\xc2\x9b", $display);
            $this->assertStringContainsString($marker, $display);
        } finally {
            @unlink($path);
        }
    }

    public function testInvalidFileExceptionStripsControlCharactersFromThePath()
    {
        // fromPath() embeds the missing path verbatim into the exception message,
        // which the console error output renders to the terminal.
        $path = "/does/not/exist_\x1b]0;HIJACK\x07_\xc2\x1b\x9b";

        try {
            InputFile::fromPath($path);
            $this->fail('Expected an InvalidFileException.');
        } catch (InvalidFileException $e) {
            $this->assertStringNotContainsString("\x1b", $e->getMessage());
            $this->assertStringNotContainsString("\x07", $e->getMessage());
            $this->assertStringNotContainsString("\xc2\x9b", $e->getMessage());
            $this->assertStringContainsString('HIJACK', $e->getMessage());
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
}
