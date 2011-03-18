<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;

class FileTest extends \PHPUnit_Framework_TestCase
{
    protected $file;

    protected function setUp()
    {
        $this->file = new File(__DIR__.'/Fixtures/test.gif');
    }

    public function testGetPathReturnsAbsolutePath()
    {
        $this->assertEquals(__DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'test.gif', $this->file->getPath());
    }

    public function test__toString()
    {
        $this->assertEquals(__DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'test.gif', (string) $this->file);
    }

    public function testGetWebPathReturnsPathRelativeToDocumentRoot()
    {
        File::setDocumentRoot(__DIR__);

        $this->assertEquals(__DIR__, File::getDocumentRoot());
        $this->assertEquals('/Fixtures/test.gif', $this->file->getWebPath());
    }

    public function testGetWebPathReturnsEmptyPathIfOutsideDocumentRoot()
    {
        File::setDocumentRoot(__DIR__.'/Fixtures/directory');

        $this->assertEquals('', $this->file->getWebPath());
    }

    public function testSetDocumentRootThrowsLogicExceptionWhenNotExists()
    {
        $this->setExpectedException('LogicException');

        File::setDocumentRoot(__DIR__.'/Fixtures/not_here');
    }

    public function testGetNameReturnsNameWithExtension()
    {
        $this->assertEquals('test.gif', $this->file->getName());
    }

    public function testGetExtensionReturnsEmptyString()
    {
        $file = new File(__DIR__.'/Fixtures/test');
        $this->assertEquals('', $file->getExtension());
    }

    public function testGetExtensionReturnsExtensionWithDot()
    {
        $this->assertEquals('.gif', $this->file->getExtension());
    }

    public function testGetDirectoryReturnsDirectoryName()
    {
        $this->assertEquals(__DIR__.DIRECTORY_SEPARATOR.'Fixtures', $this->file->getDirectory());
    }

    public function testGetMimeTypeUsesMimeTypeGuessers()
    {
        $guesser = $this->createMockGuesser($this->file->getPath(), 'image/gif');

        MimeTypeGuesser::getInstance()->register($guesser);

        $this->assertEquals('image/gif', $this->file->getMimeType());
    }

    public function testGetDefaultExtensionWithoutGuesser()
    {
        $file = new File(__DIR__.'/Fixtures/directory/.empty');

        $this->assertEquals('.empty', $file->getDefaultExtension());
    }

    public function testGetDefaultExtensionIsBasedOnMimeType()
    {
        $file = new File(__DIR__.'/Fixtures/test');
        $guesser = $this->createMockGuesser($file->getPath(), 'image/gif');

        MimeTypeGuesser::getInstance()->register($guesser);

        $this->assertEquals('.gif', $file->getDefaultExtension());
    }

    public function testConstructWhenFileNotExists()
    {
        $this->setExpectedException('Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException');

        new File(__DIR__.'/Fixtures/not_here');
    }

    public function testSizeReturnsFileSize()
    {
        $this->assertEquals(filesize($this->file->getPath()), $this->file->getSize());
    }

    public function testSizeFailing()
    {
        $this->setExpectedException('Symfony\Component\HttpFoundation\File\Exception\FileException');

        $dir = __DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'directory';
        $path = $dir.DIRECTORY_SEPARATOR.'test.copy.gif';
        @unlink($path);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new File($path);
        @unlink($path);
        $file->getSize();
    }

    public function testMove()
    {
        $path = __DIR__.'/Fixtures/test.copy.gif';
        $targetDir = __DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'directory';
        $targetPath = $targetDir.DIRECTORY_SEPARATOR.'test.copy.gif';
        @unlink($path);
        @unlink($targetPath);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new File($path);
        $file->move($targetDir);

        $this->assertTrue(file_exists($targetPath));
        $this->assertFalse(file_exists($path));
        $this->assertEquals($targetPath, $file->getPath());

        @unlink($path);
        @unlink($targetPath);
    }

    public function testMoveWithNewName()
    {
        $path = __DIR__.'/Fixtures/test.copy.gif';
        $targetDir = __DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'directory';
        $targetPath = $targetDir.DIRECTORY_SEPARATOR.'test.newname.gif';
        @unlink($path);
        @unlink($targetPath);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new File($path);
        $file->move($targetDir, 'test.newname.gif');

        $this->assertTrue(file_exists($targetPath));
        $this->assertFalse(file_exists($path));
        $this->assertEquals($targetPath, $file->getPath());

        @unlink($path);
        @unlink($targetPath);
    }

    public function testMoveFailing()
    {
        $path = __DIR__.'/Fixtures/test.copy.gif';
        $targetPath = '/thisfolderwontexist';
        @unlink($path);
        @unlink($targetPath);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new File($path);

        $this->setExpectedException('Symfony\Component\HttpFoundation\File\Exception\FileException');
        $file->move($targetPath);

        $this->assertFileExists($path);
        $this->assertFileNotExists($path.$targetPath.'test.gif');
        $this->assertEquals($path, $file->getPath());

        @unlink($path);
        @unlink($targetPath);
    }

    public function testRename()
    {
        $path = __DIR__.'/Fixtures/test.copy.gif';
        $targetPath = realpath(__DIR__.'/Fixtures').DIRECTORY_SEPARATOR.'test.target.gif';
        @unlink($path);
        @unlink($targetPath);
        copy(realpath(__DIR__.'/Fixtures/test.gif'), $path);

        $file = new File($path);
        $file->rename('test.target.gif');

        $this->assertTrue(file_exists($targetPath));
        $this->assertFalse(file_exists($path));
        $this->assertEquals($targetPath, $file->getPath());

        @unlink($path);
        @unlink($targetPath);
    }

    protected function createMockGuesser($path, $mimeType)
    {
        $guesser = $this->getMock('Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface');
        $guesser->expects($this->once())
                        ->method('guess')
                        ->with($this->equalTo($path))
                        ->will($this->returnValue($mimeType));

        return $guesser;
    }
}