<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Input\File;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidFileException;
use Symfony\Component\Console\Input\File\InputFile;
use Symfony\Component\Filesystem\Filesystem;

class InputFileTest extends TestCase
{
    private Filesystem $filesystem;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.microtime(true).'.'.mt_rand();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
        InputFile::cleanupAll();
    }

    public function testFromDataCreatesTemporaryFile()
    {
        $data = 'test content';
        $file = InputFile::fromData($data);

        $this->assertTrue($file->isValid());
        $this->assertTrue($file->isTempFile());
        $this->assertSame($data, $file->getContents());
        $this->assertStringStartsWith(sys_get_temp_dir().'/symfony_input_', $file->getPathname());
    }

    public function testFromDataWithFormat()
    {
        $data = 'test content';
        $file = InputFile::fromData($data, 'txt');

        $this->assertStringEndsWith('.txt', $file->getPathname());
    }

    public function testFromPathWithExistingFile()
    {
        $path = $this->tempDir.'/test.txt';
        file_put_contents($path, 'test content');

        $file = InputFile::fromPath($path);

        $this->assertTrue($file->isValid());
        $this->assertFalse($file->isTempFile());
        $this->assertSame('test content', $file->getContents());
    }

    public function testFromPathWithNonExistentFile()
    {
        $this->expectException(InvalidFileException::class);
        $this->expectExceptionMessage('does not exist');

        InputFile::fromPath('/non/existent/file.txt');
    }

    public function testFromPathWithSpacesInName()
    {
        $path = $this->tempDir.'/my file with spaces.txt';
        file_put_contents($path, 'test content');

        $file = InputFile::fromPath($path);

        $this->assertTrue($file->isValid());
        $this->assertSame('test content', $file->getContents());
    }

    public function testFromPathWithDoubleQuotes()
    {
        $path = $this->tempDir.'/quoted file.txt';
        file_put_contents($path, 'test content');

        // Path wrapped in double quotes (common when dragging files)
        $file = InputFile::fromPath('"'.$path.'"');

        $this->assertTrue($file->isValid());
        $this->assertSame(realpath($path), $file->getRealPath());
    }

    public function testFromPathWithSingleQuotes()
    {
        $path = $this->tempDir.'/quoted file.txt';
        file_put_contents($path, 'test content');

        // Path wrapped in single quotes
        $file = InputFile::fromPath("'".$path."'");

        $this->assertTrue($file->isValid());
        $this->assertSame(realpath($path), $file->getRealPath());
    }

    public function testFromPathWithEscapedSpaces()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Backslash-escaped spaces are not applicable on Windows.');
        }

        $path = $this->tempDir.'/escaped file.txt';
        file_put_contents($path, 'test content');

        // Path with backslash-escaped spaces (common in shell)
        $escapedPath = str_replace(' ', '\\ ', $path);
        $file = InputFile::fromPath($escapedPath);

        $this->assertTrue($file->isValid());
        $this->assertSame(realpath($path), $file->getRealPath());
    }

    public function testFromPathWithFileUri()
    {
        $path = $this->tempDir.'/uri file.txt';
        file_put_contents($path, 'test content');

        // file:// URI (common when dragging from some applications)
        $file = InputFile::fromPath('file://'.$path);

        $this->assertTrue($file->isValid());
        $this->assertSame(realpath($path), $file->getRealPath());
    }

    public function testFromPathWithFileUriAndEncodedSpaces()
    {
        $path = $this->tempDir.'/uri encoded file.txt';
        file_put_contents($path, 'test content');

        // file:// URI with URL-encoded spaces
        $encodedPath = 'file://'.str_replace(' ', '%20', $path);
        $file = InputFile::fromPath($encodedPath);

        $this->assertTrue($file->isValid());
        $this->assertSame(realpath($path), $file->getRealPath());
    }

    #[RequiresPhpExtension('fileinfo')]
    public function testGetMimeType()
    {
        // Create a minimal PNG file (1x1 transparent pixel)
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $path = $this->tempDir.'/test.png';
        file_put_contents($path, $pngData);

        $file = InputFile::fromPath($path);
        $mimeType = $file->getMimeType();

        $this->assertSame('image/png', $mimeType);
    }

    #[RequiresPhpExtension('fileinfo')]
    public function testGuessExtension()
    {
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $path = $this->tempDir.'/test.png';
        file_put_contents($path, $pngData);

        $file = InputFile::fromPath($path);

        $this->assertSame('png', $file->guessExtension());
    }

    public function testMoveTemporaryFile()
    {
        $data = 'test content';
        $file = InputFile::fromData($data);
        $originalPath = $file->getPathname();

        $destination = $this->tempDir.'/destination';
        mkdir($destination, 0o777, true);

        $movedFile = $file->move($destination, 'moved.txt');

        $this->assertFileDoesNotExist($originalPath);
        $this->assertFileExists($destination.'/moved.txt');
        $this->assertSame('test content', $movedFile->getContents());
        $this->assertFalse($movedFile->isTempFile());
    }

    public function testMoveNonTemporaryFileCopies()
    {
        $path = $this->tempDir.'/original.txt';
        file_put_contents($path, 'test content');

        $file = InputFile::fromPath($path);

        $destination = $this->tempDir.'/destination';
        mkdir($destination, 0o777, true);

        $movedFile = $file->move($destination, 'copied.txt');

        // Original file should still exist (copy, not move)
        $this->assertFileExists($path);
        $this->assertFileExists($destination.'/copied.txt');
        $this->assertSame('test content', $movedFile->getContents());
    }

    public function testMoveCreatesDirectory()
    {
        $data = 'test content';
        $file = InputFile::fromData($data);

        $destination = $this->tempDir.'/new/nested/directory';
        $movedFile = $file->move($destination, 'file.txt');

        $this->assertDirectoryExists($destination);
        $this->assertFileExists($destination.'/file.txt');
    }

    public function testMoveToNonWritableDirectory()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Test not applicable on Windows.');
        }

        $data = 'test content';
        $file = InputFile::fromData($data);

        $destination = $this->tempDir.'/readonly';
        mkdir($destination, 0o555, true);

        $this->expectException(InvalidFileException::class);

        try {
            $file->move($destination, 'file.txt');
        } finally {
            chmod($destination, 0o777);
        }
    }

    public function testCleanupRemovesTempFile()
    {
        $file = InputFile::fromData('test content');
        $path = $file->getPathname();

        $this->assertFileExists($path);

        $file->cleanup();

        $this->assertFileDoesNotExist($path);
    }

    public function testCleanupDoesNothingForNonTempFile()
    {
        $path = $this->tempDir.'/permanent.txt';
        file_put_contents($path, 'test content');

        $file = InputFile::fromPath($path);
        $file->cleanup();

        $this->assertFileExists($path);
    }

    public function testIsValid()
    {
        $path = $this->tempDir.'/valid.txt';
        file_put_contents($path, 'test');

        $file = InputFile::fromPath($path);
        $this->assertTrue($file->isValid());

        unlink($path);
        $this->assertFalse($file->isValid());
    }

    public function testGetHumanReadableSize()
    {
        $file = InputFile::fromData(str_repeat('x', 1024));
        $this->assertSame('1.0 KB', $file->getHumanReadableSize());

        $file = InputFile::fromData(str_repeat('x', 100));
        $this->assertSame('100.0 B', $file->getHumanReadableSize());
    }

    public function testGetContentsThrowsForInvalidFile()
    {
        $path = $this->tempDir.'/to_delete.txt';
        file_put_contents($path, 'test');

        $file = InputFile::fromPath($path);
        unlink($path);

        $this->expectException(InvalidFileException::class);
        $this->expectExceptionMessage('Cannot read an invalid file');

        $file->getContents();
    }
}
