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

use Symfony\Component\Finder\Iterator\ReverseSortingIterator;

class ReverseSortingIteratorTest extends IteratorTestCase
{
    public function test()
    {
        $iterator = new ReverseSortingIterator(new MockFileListIterator(array(
            'a.txt',
            'b.yaml',
            'c.php',
        )));

        $result = iterator_to_array($iterator);
        $this->assertCount(3, $iterator);
        $this->assertSame('c.php', $result[0]->getFilename());
        $this->assertSame('b.yaml', $result[1]->getFilename());
        $this->assertSame('a.txt', $result[2]->getFilename());
    }
}
