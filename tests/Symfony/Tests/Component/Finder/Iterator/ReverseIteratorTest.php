<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Finder\Iterator;

use Symfony\Component\Finder\Iterator\ReverseIterator;

class ReverseIteratorTest
{
    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($preserve_keys, $expected)
    {
        $inner = new \ArrayIterator(array('test' => 1, 2, 3));

        $iterator = new ReverseIterator($inner, $preserve_keys);

        $this->assertIterator($expected, $iterator);
    }

    public function getAcceptData()
    {
        return array(
            array(array(true), array(3, 2, 'test' => 1)),
            array(array(false), array(3, 2, 1)),
        );
    }
}
