<?php

namespace Symfony\Tests\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;


class FileTest extends \PHPUnit_Framework_TestCase
{
    protected $file;

    public function setUp()
    {
        $this->file = new File(__DIR__.'/Fixtures/test.gif');
    }

    public function testGetPathReturnsAbsolutePath()
    {
        $this->assertEquals(__DIR__.'/Fixtures/test.gif', $this->file->getPath());
    }

    public function testGetNameReturnsNameWithExtension()
    {
        $this->assertEquals('test.gif', $this->file->getName());
    }

    public function testGetExtensionReturnsExtensionWithDot()
    {
        $this->assertEquals('.gif', $this->file->getExtension());
    }

    public function testGetDirectoryReturnsDirectoryName()
    {
        $this->assertEquals(__DIR__.'/Fixtures', $this->file->getDirectory());
    }

    public function testGetMimeTypeUsesMimeTypeGuessers()
    {
        $guesser = $this->createMockGuesser($this->file->getPath(), 'image/gif');

        MimeTypeGuesser::getInstance()->register($guesser);

        $this->assertEquals('image/gif', $this->file->getMimeType());
    }

    public function testGetDefaultExtensionIsBasedOnMimeType()
    {
        $file = new File(__DIR__.'/Fixtures/test');
        $guesser = $this->createMockGuesser($file->getPath(), 'image/gif');

        MimeTypeGuesser::getInstance()->register($guesser);

        $this->assertEquals('.gif', $file->getDefaultExtension());
    }

    public function testSizeReturnsFileSize()
    {
        $this->assertEquals(filesize($this->file->getPath()), $this->file->size());
    }

    public function testMove()
    {
        $path = __DIR__.'/Fixtures/test.copy.gif';
        $targetPath = __DIR__.'/Fixtures/test.target.gif';
        @unlink($path);
        @unlink($targetPath);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new File($path);
        $file->move($targetPath);

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