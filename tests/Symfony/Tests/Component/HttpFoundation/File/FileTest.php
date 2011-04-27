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
use Symfony\Component\HttpFoundation\File\Exception\DirectoryNotFoundException;

class FileTest extends \PHPUnit_Framework_TestCase
{
    protected $file;

    protected function setUp()
    {
        $this->file = new File(__DIR__.'/Fixtures/test.gif');
    }

    public function testGetMimeTypeUsesMimeTypeGuessers()
    {
        $guesser = $this->createMockGuesser($this->file->getPathname(), 'image/gif');

        MimeTypeGuesser::getInstance()->register($guesser);

        $this->assertEquals('image/gif', $this->file->getMimeType());
    }

    public function testGuessExtensionWithoutGuesser()
    {
        $file = new File(__DIR__.'/Fixtures/directory/.empty');

        $this->assertEquals(null, $file->guessExtension());
    }

    public function testGuessExtensionIsBasedOnMimeType()
    {
        $file = new File(__DIR__.'/Fixtures/test');
        $guesser = $this->createMockGuesser($file->getPathname(), 'image/gif');

        MimeTypeGuesser::getInstance()->register($guesser);

        $this->assertEquals('gif', $file->guessExtension());
    }

    /**
     * @expectedException Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException
     */
    public function testConstructWhenFileNotExists()
    {
        new File(__DIR__.'/Fixtures/not_here');
    }

    /**
     * @expectedException Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    public function testSizeFailing()
    {
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
        $targetDir = __DIR__.'/Fixtures/directory';
        $targetPath = $targetDir.'/test.copy.gif';
        @unlink($path);
        @unlink($targetPath);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new File($path);
        $movedFile = $file->move($targetDir);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\File\File', $movedFile);        
        
        $this->assertTrue(file_exists($targetPath));
        $this->assertFalse(file_exists($path));
        $this->assertEquals($targetPath, $movedFile->getPathname());

        @unlink($targetPath);
    }

    public function testMoveWithNewName()
    {
        $path = __DIR__.'/Fixtures/test.copy.gif';
        $targetDir = __DIR__.'/Fixtures/directory';
        $targetPath = $targetDir.'/test.newname.gif';
        @unlink($path);
        @unlink($targetPath);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new File($path);
        $movedFile = $file->move($targetDir, 'test.newname.gif');

        $this->assertTrue(file_exists($targetPath));
        $this->assertFalse(file_exists($path));
        $this->assertEquals($targetPath, $movedFile->getPathname());

        @unlink($targetPath);
    }
    
    public function testMoveFailingWhenTargetDirectoryDoesNotExist()
    {
        $path = __DIR__.'/Fixtures/test.copy.gif';
        $targetPath = '/thisfolderwontexist';
        @unlink($path);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new File($path);

        try {
            $file->move($targetPath);
        } catch (DirectoryNotFoundException $e) {
            @unlink($path);
            return;
        }
        
        $this->fail('The target directory does not exist');
    }

    protected function createMockGuesser($path, $mimeType)
    {
        $guesser = $this->getMock('Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface');
        $guesser
            ->expects($this->once())
            ->method('guess')
            ->with($this->equalTo($path))
            ->will($this->returnValue($mimeType))
        ;

        return $guesser;
    }
}