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
    public function testGetMimeTypeUsesMimeTypeGuessers()
    {
        $file = new File(__DIR__.'/Fixtures/test.gif');
        $guesser = $this->createMockGuesser($file->getPathname(), 'image/gif');

        MimeTypeGuesser::getInstance()->register($guesser);

        $this->assertEquals('image/gif', $file->getMimeType());
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
        $this->assertEquals(realpath($targetPath), $movedFile->getRealPath());

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
        $this->assertEquals(realpath($targetPath), $movedFile->getRealPath());

        @unlink($targetPath);
    }
    
    public function testMoveToAnUnexistentDirectory()
    {
        $path = __DIR__.'/Fixtures/test.copy.gif';
        $targetDir = __DIR__.'/Fixtures/directory/sub';
        $targetPath = $targetDir.'/test.copy.gif';
        @unlink($path);
        @unlink($targetPath);
        @rmdir($targetDir);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new File($path);
        $movedFile = $file->move($targetDir);

        $this->assertTrue(file_exists($targetPath));
        $this->assertFalse(file_exists($path));
        $this->assertEquals(realpath($targetPath), $movedFile->getRealPath());
       
        @unlink($targetPath);
        @rmdir($targetDir);
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