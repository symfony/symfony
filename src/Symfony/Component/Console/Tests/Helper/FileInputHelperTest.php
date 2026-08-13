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
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Helper\FileInputHelper;
use Symfony\Component\Console\Helper\TerminalInputHelper;
use Symfony\Component\Console\Input\File\InputFile;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Question\FileQuestion;
use Symfony\Component\Console\Terminal\Image\ImageProtocolInterface;
use Symfony\Component\Console\Terminal\Image\KittyGraphicsProtocol;

class FileInputHelperTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    public function testDisplayFileEscapesFormatterMetaCharactersInPath()
    {
        $name = 'sf_console_<error>bad<error>_'.bin2hex(random_bytes(4)).'.txt';
        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.$name;

        if (false === @file_put_contents($path, 'x')) {
            $this->markTestSkipped('Filesystem does not allow "<" / ">" in filenames.');
        }
        $this->tempFiles[] = $path;

        $file = new InputFile($path);
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);

        (new FileInputHelper())->displayFile($output, $file);

        $display = $output->fetch();
        $this->assertStringContainsString('<error>bad<error>', $display);
        $this->assertStringNotContainsString("\e[37;41m", $display);
        $this->assertStringNotContainsString("\e[39;49m", $display);
    }

    public function testReadWithPasteDetectionReturnsPathFromLineInput()
    {
        $path = $this->createTempFile();

        $this->assertSame($path, $this->readWithPasteDetection($this->streamOf($path."\n"))->getPathname());
    }

    public function testReadWithPasteDetectionReturnsPathWhenInputEndsWithoutNewline()
    {
        $path = $this->createTempFile();

        $this->assertSame($path, $this->readWithPasteDetection($this->streamOf($path))->getPathname());
    }

    public function testReadWithPasteDetectionStopsAtNewlineBeforePasteStart()
    {
        $path = $this->createTempFile();

        // A newline ends the line even when a paste-start marker appears later in the stream.
        $input = $path."\n".$this->pasteMarker('PASTE_START').'ignored'.$this->pasteMarker('PASTE_END');

        $this->assertSame($path, $this->readWithPasteDetection($this->streamOf($input))->getPathname());
    }

    public function testReadWithPasteDetectionReassemblesPasteSpanningMultipleReadChunks()
    {
        $start = $this->pasteMarker('PASTE_START');
        $path = $this->createTempFile();

        // Pad so the path straddles the 8192-byte read boundary: part lands in the first
        // fread() call, the rest in the second.
        $input = $start.str_repeat(' ', 8192 - \strlen($start) - 5).$path.$this->pasteMarker('PASTE_END');

        $this->assertSame($path, $this->readWithPasteDetection($this->streamOf($input))->getPathname());
    }

    public function testReadWithPasteDetectionMatchesPasteStartSplitAcrossReadChunks()
    {
        $start = $this->pasteMarker('PASTE_START');
        $path = $this->createTempFile();

        // Worst case for the boundary look-back: only PASTE_START's last byte lands in the second read.
        $input = str_repeat(' ', 8192 - (\strlen($start) - 1)).$start.$path.$this->pasteMarker('PASTE_END');

        $this->assertSame($path, $this->readWithPasteDetection($this->streamOf($input))->getPathname());
    }

    public function testReadWithPasteDetectionMatchesPasteEndSplitAcrossReadChunks()
    {
        $start = $this->pasteMarker('PASTE_START');
        $end = $this->pasteMarker('PASTE_END');
        $path = $this->createTempFile();

        // Worst case for the boundary look-back: only PASTE_END's last byte lands in the second read.
        $input = $start.str_repeat(' ', 8192 - (\strlen($end) - 1) - \strlen($start) - \strlen($path)).$path.$end;

        $this->assertSame($path, $this->readWithPasteDetection($this->streamOf($input))->getPathname());
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

    public function testCollectFilesRedrawsTheFooterInPasteMode()
    {
        $a = $this->createTempFile();
        $b = $this->createTempFile();

        // One answer with two files, then an empty answer to finish.
        $batches = [[InputFile::fromPath($a), InputFile::fromPath($b)], []];
        $read = static function () use (&$batches): array { return array_shift($batches) ?? []; };

        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $files = $this->invokeCollectFiles($output, new FileQuestion('Provide files:', multiple: true), $read, true);

        $this->assertSame([$a, $b], array_map(static fn (InputFile $f): string => $f->getPathname(), $files));

        $display = $output->fetch();
        // The question is printed once, not repeated per answer.
        $this->assertSame(1, substr_count($display, 'Provide files:'));
        // The hint is pinned below and the cursor is moved up to redraw it (footer erase).
        $this->assertStringContainsString('// Hit Enter to finish', $display);
        $this->assertStringContainsString("\x1b[1A", $display);
        $this->assertStringContainsString(basename($a), $display);
        $this->assertStringContainsString(basename($b), $display);
    }

    public function testCollectFilesListsFilesWithoutCursorControlWhenNotDecorated()
    {
        $a = $this->createTempFile();

        $batches = [[InputFile::fromPath($a)], []];
        $read = static function () use (&$batches): array { return array_shift($batches) ?? []; };

        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, false);
        $files = $this->invokeCollectFiles($output, new FileQuestion('Provide files:', multiple: true), $read, false);

        $this->assertCount(1, $files);

        $display = $output->fetch();
        $this->assertSame(1, substr_count($display, 'Provide files:'));
        $this->assertStringContainsString('// Hit Enter to finish', $display);
        $this->assertStringNotContainsString("\x1b[", $display);
    }

    public function testReadWithPasteDetectionReturnsSeveralPathsFromASinglePaste()
    {
        $a = $this->createTempFile();
        $b = $this->createTempFile();

        // Dropping several files at once inserts them as space-separated, quoted paths.
        $input = $this->pasteMarker('PASTE_START').\sprintf("'%s' '%s'", $a, $b).$this->pasteMarker('PASTE_END');

        $files = $this->readAllWithPasteDetection($this->streamOf($input), null, true);

        $this->assertSame([$a, $b], array_map(static fn (InputFile $f): string => $f->getPathname(), $files));
    }

    public function testReadWithPasteDetectionReturnsSeveralPathsFromALine()
    {
        $a = $this->createTempFile();
        $b = $this->createTempFile();

        $files = $this->readAllWithPasteDetection($this->streamOf("$a $b\n"), null, true);

        $this->assertSame([$a, $b], array_map(static fn (InputFile $f): string => $f->getPathname(), $files));
    }

    public function testReadWithPasteDetectionKeepsQuotedAndEscapedSpacesWithinEachPath()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Backslash escaping of paths is a non-Windows convention.');
        }

        $a = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sf console '.bin2hex(random_bytes(4)).'.txt';
        file_put_contents($a, 'x');
        $this->tempFiles[] = $a;
        $b = $this->createTempFile();

        // First path backslash-escaped, second path quoted.
        $input = str_replace(' ', '\ ', $a)." '$b'\n";

        $files = $this->readAllWithPasteDetection($this->streamOf($input), null, true);

        $this->assertSame([$a, $b], array_map(static fn (InputFile $f): string => $f->getPathname(), $files));
    }

    public function testReadWithPasteDetectionReturnsNoFileForAnEmptyMultipleAnswer()
    {
        // An empty answer ends the collection of a multiple question instead of aborting.
        $this->assertSame([], $this->readAllWithPasteDetection($this->streamOf("\n"), null, true));
    }

    public function testReadWithPasteDetectionRejectsAnUndecodablePastedImage()
    {
        $truncated = "\x1b_Ga=T,f=100,m=1;".base64_encode('chunk one')."\x1b\\";
        $input = $this->pasteMarker('PASTE_START').$truncated.$this->pasteMarker('PASTE_END');

        $this->expectException(InvalidFileException::class);
        $this->expectExceptionMessage('The pasted image could not be decoded.');

        $this->readWithPasteDetection($this->streamOf($input), new KittyGraphicsProtocol());
    }

    public function testReadWithPasteDetectionAbortsBeyondMaxBytes()
    {
        $cap = (new \ReflectionClassConstant(FileInputHelper::class, 'MAX_PASTE_BYTES'))->getValue();

        $this->expectException(InvalidFileException::class);
        $this->expectExceptionMessage('Pasted input exceeds the maximum allowed size');

        $this->readWithPasteDetection($this->streamOf(str_repeat('A', $cap + 1)));
    }

    public function testReadWithPasteDetectionAbortsOnEmptyInput()
    {
        $this->expectException(MissingInputException::class);
        $this->expectExceptionMessage('Aborted.');

        $this->readWithPasteDetection($this->streamOf(''));
    }

    public function testReadWithPasteDetectionRejectsEmptyLine()
    {
        $this->expectException(MissingInputException::class);
        $this->expectExceptionMessage('No file input provided.');

        $this->readWithPasteDetection($this->streamOf("\n"));
    }

    public function testReadWithPasteDetectionIgnoresInputTypedAfterAnEmptyPaste()
    {
        $path = $this->createTempFile();
        $input = $this->pasteMarker('PASTE_START').$this->pasteMarker('PASTE_END').$path."\n";

        try {
            $this->readWithPasteDetection($this->streamOf($input));
            $this->fail('Expected an InvalidFileException.');
        } catch (InvalidFileException $e) {
            $this->assertStringNotContainsString($path, $e->getMessage());
        }
    }

    /**
     * @param callable():list<InputFile> $read
     *
     * @return list<InputFile>
     */
    private function invokeCollectFiles(BufferedOutput $output, FileQuestion $question, callable $read, bool $canPaste): array
    {
        $method = new \ReflectionMethod(FileInputHelper::class, 'collectFiles');

        return $method->invoke(new FileInputHelper(), $output, $question, $read, $canPaste);
    }

    private function createTempFile(): string
    {
        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sf_console_'.bin2hex(random_bytes(4)).'.txt';
        file_put_contents($path, 'x');

        return $this->tempFiles[] = $path;
    }

    private function pasteMarker(string $name): string
    {
        return (new \ReflectionClassConstant(FileInputHelper::class, $name))->getValue();
    }

    /**
     * @return resource
     */
    private function streamOf(string $contents)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function readWithPasteDetection($stream, ?ImageProtocolInterface $protocol = null): InputFile
    {
        return $this->readAllWithPasteDetection($stream, $protocol)[0];
    }

    /**
     * @param resource $stream
     *
     * @return list<InputFile>
     */
    private function readAllWithPasteDetection($stream, ?ImageProtocolInterface $protocol = null, bool $multiple = false): array
    {
        $reflection = new \ReflectionClass(FileInputHelper::class);
        $method = $reflection->getMethod('readWithPasteDetection');

        $terminalReflection = new \ReflectionClass(TerminalInputHelper::class);
        $inputHelper = $terminalReflection->newInstanceWithoutConstructor();
        foreach (['isStdin' => false, 'withStty' => false] as $name => $value) {
            $terminalReflection->getProperty($name)->setValue($inputHelper, $value);
        }

        $helper = new FileInputHelper();

        if (null !== $protocol) {
            $reflection->getProperty('protocol')->setValue($helper, $protocol);
        }

        return $method->invoke($helper, $stream, new BufferedOutput(), new FileQuestion('?', multiple: $multiple), $inputHelper);
    }
}
