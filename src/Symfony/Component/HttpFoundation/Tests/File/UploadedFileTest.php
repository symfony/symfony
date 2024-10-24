<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\File;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\Exception\CannotWriteFileException;
use Symfony\Component\HttpFoundation\File\Exception\ExtensionFileException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\Exception\FormSizeFileException;
use Symfony\Component\HttpFoundation\File\Exception\IniSizeFileException;
use Symfony\Component\HttpFoundation\File\Exception\NoFileException;
use Symfony\Component\HttpFoundation\File\Exception\NoTmpDirFileException;
use Symfony\Component\HttpFoundation\File\Exception\PartialFileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadedFileTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\ini_get('file_uploads')) {
            $this->markTestSkipped('file_uploads is disabled in php.ini');
        }
    }

    public function testConstructWhenFileNotExists()
    {
        $this->expectException(FileNotFoundException::class);

        new UploadedFile(
            __DIR__.'/Fixtures/not_here',
            'original.gif',
            null
        );
    }

    public function testFileUploadsWithNoMimeType()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            null,
            \UPLOAD_ERR_OK
        );

        $this->assertEquals('application/octet-stream', $file->getClientMimeType());

        if (\extension_loaded('fileinfo')) {
            $this->assertEquals('image/gif', $file->getMimeType());
        }
    }

    public function testFileUploadsWithUnknownMimeType()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/.unknownextension',
            'original.gif',
            null,
            \UPLOAD_ERR_OK
        );

        $this->assertEquals('application/octet-stream', $file->getClientMimeType());
    }

    public function testGuessClientExtension()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            null
        );

        $this->assertEquals('gif', $file->guessClientExtension());
    }

    public function testGuessClientExtensionWithIncorrectMimeType()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/png',
            null
        );

        $this->assertEquals('png', $file->guessClientExtension());
    }

    public function testCaseSensitiveMimeType()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/case-sensitive-mime-type.xlsm',
            'test.xlsm',
            'application/vnd.ms-excel.sheet.macroEnabled.12',
            null
        );

        $this->assertEquals('xlsm', $file->guessClientExtension());
    }

    public function testErrorIsOkByDefault()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            null
        );

        $this->assertEquals(\UPLOAD_ERR_OK, $file->getError());
    }

    public function testInvalidFile()
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('The file "original.gif" was not uploaded due to an unknown error.');

        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
        );

        $file->move(__DIR__.'/Fixtures/directory');
    }

    public function testNoErrorMessageIfErrorIsUploadErrOk()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            null
        );

        $this->assertEquals('', $file->getErrorMessage());
    }

    public function testGetClientOriginalName()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            null
        );

        $this->assertEquals('original.gif', $file->getClientOriginalName());
    }

    public function testGetClientOriginalExtension()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            null
        );

        $this->assertEquals('gif', $file->getClientOriginalExtension());
    }

    public function testMoveLocalFileIsNotAllowed()
    {
        $this->expectException(FileException::class);
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            \UPLOAD_ERR_OK
        );

        $file->move(__DIR__.'/Fixtures/directory');
    }

    public static function failedUploadedFile()
    {
        foreach ([\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE, \UPLOAD_ERR_PARTIAL, \UPLOAD_ERR_NO_FILE, \UPLOAD_ERR_CANT_WRITE, \UPLOAD_ERR_NO_TMP_DIR, \UPLOAD_ERR_EXTENSION, -1] as $error) {
            yield [new UploadedFile(
                __DIR__.'/Fixtures/test.gif',
                'original.gif',
                'image/gif',
                $error
            )];
        }
    }

    /**
     * @dataProvider failedUploadedFile
     */
    public function testMoveFailed(UploadedFile $file)
    {
        $exceptionClass = match ($file->getError()) {
            \UPLOAD_ERR_INI_SIZE => IniSizeFileException::class,
            \UPLOAD_ERR_FORM_SIZE => FormSizeFileException::class,
            \UPLOAD_ERR_PARTIAL => PartialFileException::class,
            \UPLOAD_ERR_NO_FILE => NoFileException::class,
            \UPLOAD_ERR_CANT_WRITE => CannotWriteFileException::class,
            \UPLOAD_ERR_NO_TMP_DIR => NoTmpDirFileException::class,
            \UPLOAD_ERR_EXTENSION => ExtensionFileException::class,
            default => FileException::class,
        };

        $this->expectException($exceptionClass);

        $file->move(__DIR__.'/Fixtures/directory');
    }

    public function testMoveLocalFileIsAllowedInTestMode()
    {
        $path = __DIR__.'/Fixtures/test.copy.gif';
        $targetDir = __DIR__.'/Fixtures/directory';
        $targetPath = $targetDir.'/test.copy.gif';
        @unlink($path);
        @unlink($targetPath);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new UploadedFile(
            $path,
            'original.gif',
            'image/gif',
            \UPLOAD_ERR_OK,
            true
        );

        $movedFile = $file->move(__DIR__.'/Fixtures/directory');

        $this->assertFileExists($targetPath);
        $this->assertFileDoesNotExist($path);
        $this->assertEquals(realpath($targetPath), $movedFile->getRealPath());

        @unlink($targetPath);
    }

    public function testGetClientOriginalNameSanitizeFilename()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            '../../original.gif',
            'image/gif'
        );

        $this->assertEquals('original.gif', $file->getClientOriginalName());
    }

    public function testGetSize()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif'
        );

        $this->assertEquals(filesize(__DIR__.'/Fixtures/test.gif'), $file->getSize());

        $file = new UploadedFile(
            __DIR__.'/Fixtures/test',
            'original.gif',
            'image/gif'
        );

        $this->assertEquals(filesize(__DIR__.'/Fixtures/test'), $file->getSize());
    }

    public function testGetExtension()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif'
        );

        $this->assertEquals('gif', $file->getExtension());
    }

    public function testIsValid()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            null,
            \UPLOAD_ERR_OK,
            true
        );

        $this->assertTrue($file->isValid());
    }

    /**
     * @dataProvider uploadedFileErrorProvider
     */
    public function testIsInvalidOnUploadError($error)
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            null,
            $error
        );

        $this->assertFalse($file->isValid());
    }

    public static function uploadedFileErrorProvider()
    {
        return [
            [\UPLOAD_ERR_INI_SIZE],
            [\UPLOAD_ERR_FORM_SIZE],
            [\UPLOAD_ERR_PARTIAL],
            [\UPLOAD_ERR_NO_TMP_DIR],
            [\UPLOAD_ERR_EXTENSION],
        ];
    }

    public function testIsInvalidIfNotHttpUpload()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            null,
            \UPLOAD_ERR_OK
        );

        $this->assertFalse($file->isValid());
    }

    public function testGetMaxFilesize()
    {
        $size = UploadedFile::getMaxFilesize();

        if ($size > \PHP_INT_MAX) {
            $this->assertIsFloat($size);
        } else {
            $this->assertIsInt($size);
        }

        $this->assertGreaterThan(0, $size);

        if (0 === (int) \ini_get('post_max_size') && 0 === (int) \ini_get('upload_max_filesize')) {
            $this->assertSame(\PHP_INT_MAX, $size);
        }
    }

    public function testgetClientOriginalPath()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'test.gif',
            'image/gif'
        );

        $this->assertEquals('test.gif', $file->getClientOriginalPath());
    }

    public function testgetClientOriginalPathWebkitDirectory()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/webkitdirectory/test.txt',
            'webkitdirectory/test.txt',
            'text/plain',
        );

        $this->assertEquals('webkitdirectory/test.txt', $file->getClientOriginalPath());
    }
}
