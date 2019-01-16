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
use Symfony\Component\HttpFoundation\File\Exception\FormSizeFileException;
use Symfony\Component\HttpFoundation\File\Exception\IniSizeFileException;
use Symfony\Component\HttpFoundation\File\Exception\NoFileException;
use Symfony\Component\HttpFoundation\File\Exception\NoTmpDirFileException;
use Symfony\Component\HttpFoundation\File\Exception\PartialFileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadedFileTest extends TestCase
{
    protected function setUp()
    {
        if (!ini_get('file_uploads')) {
            $this->markTestSkipped('file_uploads is disabled in php.ini');
        }
    }

    public function testConstructWhenFileNotExists()
    {
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException');

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
            UPLOAD_ERR_OK
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
            UPLOAD_ERR_OK
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
            'image/jpeg',
            null
        );

        $this->assertEquals('jpeg', $file->guessClientExtension());
    }

    public function testErrorIsOkByDefault()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            null
        );

        $this->assertEquals(UPLOAD_ERR_OK, $file->getError());
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

    /**
     * @expectedException \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    public function testMoveLocalFileIsNotAllowed()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            UPLOAD_ERR_OK
        );

        $movedFile = $file->move(__DIR__.'/Fixtures/directory');
    }

    public function failedUploadedFile()
    {
        foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_EXTENSION, -1] as $error) {
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
        switch ($file->getError()) {
            case UPLOAD_ERR_INI_SIZE:
                $exceptionClass = IniSizeFileException::class;
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $exceptionClass = FormSizeFileException::class;
                break;
            case UPLOAD_ERR_PARTIAL:
                $exceptionClass = PartialFileException::class;
                break;
            case UPLOAD_ERR_NO_FILE:
                $exceptionClass = NoFileException::class;
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $exceptionClass = CannotWriteFileException::class;
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $exceptionClass = NoTmpDirFileException::class;
                break;
            case UPLOAD_ERR_EXTENSION:
                $exceptionClass = ExtensionFileException::class;
                break;
            default:
                $exceptionClass = FileException::class;
        }

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
            UPLOAD_ERR_OK,
            true
        );

        $movedFile = $file->move(__DIR__.'/Fixtures/directory');

        $this->assertFileExists($targetPath);
        $this->assertFileNotExists($path);
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

    /**
     * @group legacy
     * @expectedDeprecation Passing a size as 4th argument to the constructor of "Symfony\Component\HttpFoundation\File\UploadedFile" is deprecated since Symfony 4.1.
     */
    public function testConstructDeprecatedSize()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            filesize(__DIR__.'/Fixtures/test.gif'),
            UPLOAD_ERR_OK,
            false
        );

        $this->assertEquals(filesize(__DIR__.'/Fixtures/test.gif'), $file->getSize());
    }

    /**
     * @group legacy
     * @expectedDeprecation Passing a size as 4th argument to the constructor of "Symfony\Component\HttpFoundation\File\UploadedFile" is deprecated since Symfony 4.1.
     */
    public function testConstructDeprecatedSizeWhenPassingOnlyThe4Needed()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            filesize(__DIR__.'/Fixtures/test.gif')
        );

        $this->assertEquals(filesize(__DIR__.'/Fixtures/test.gif'), $file->getSize());
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
            UPLOAD_ERR_OK,
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

    public function uploadedFileErrorProvider()
    {
        return [
            [UPLOAD_ERR_INI_SIZE],
            [UPLOAD_ERR_FORM_SIZE],
            [UPLOAD_ERR_PARTIAL],
            [UPLOAD_ERR_NO_TMP_DIR],
            [UPLOAD_ERR_EXTENSION],
        ];
    }

    public function testIsInvalidIfNotHttpUpload()
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            null,
            UPLOAD_ERR_OK
        );

        $this->assertFalse($file->isValid());
    }
}
