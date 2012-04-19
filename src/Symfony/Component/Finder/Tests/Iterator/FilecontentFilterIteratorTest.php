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
}
