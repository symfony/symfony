<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Iterator;

use Symfony\Component\Finder\Iterator\FilecontentFilterIterator;

class FilecontentFilterIteratorTest extends IteratorTestCase
{

    public function testAccept()
    {
        $inner = new ContentInnerNameIterator(array('test.txt'));
        $iterator = new FilecontentFilterIterator($inner, array(), array());
        $this->assertIterator(array('test.txt'), $iterator);
    }

    public function testDirectory()
    {
        $inner = new ContentInnerNameIterator(array('directory'));
        $iterator = new FilecontentFilterIterator($inner, array('directory'), array());
        $this->assertIterator(array(), $iterator);
    }

    public function testUnreadableFile()
    {
        $inner = new ContentInnerNameIterator(array('file r-'));
        $iterator = new FilecontentFilterIterator($inner, array('file r-'), array());
        $this->assertIterator(array(), $iterator);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testFileGetContents()
    {
        $inner = new ContentInnerNameIterator(array('file r+'));
        $iterator = new FilecontentFilterIterator($inner, array('file r+'), array());
        $array = iterator_to_array($iterator);
    }

}

class ContentInnerNameIterator extends \ArrayIterator
{
    public function current()
    {
        return new \SplFileInfo(parent::current());
    }

    public function getFilename()
    {
        return parent::current();
    }

    public function isFile()
    {
        $name = parent::current();

        return preg_match('/file/', $name);
    }

    public function isDir()
    {
        $name = parent::current();

        return preg_match('/directory/', $name);
    }

    public function getRealpath()
    {
        return parent::current();
    }

    public function isReadable()
    {
        $name = parent::current();

        return preg_match('/r\+/', $name);
    }

}
