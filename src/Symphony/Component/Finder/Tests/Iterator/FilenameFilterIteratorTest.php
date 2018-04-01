<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Finder\Tests\Iterator;

use Symphony\Component\Finder\Iterator\FilenameFilterIterator;

class FilenameFilterIteratorTest extends IteratorTestCase
{
    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($matchPatterns, $noMatchPatterns, $expected)
    {
        $inner = new InnerNameIterator(array('test.php', 'test.py', 'foo.php'));

        $iterator = new FilenameFilterIterator($inner, $matchPatterns, $noMatchPatterns);

        $this->assertIterator($expected, $iterator);
    }

    public function getAcceptData()
    {
        return array(
            array(array('test.*'), array(), array('test.php', 'test.py')),
            array(array(), array('test.*'), array('foo.php')),
            array(array('*.php'), array('test.*'), array('foo.php')),
            array(array('*.php', '*.py'), array('foo.*'), array('test.php', 'test.py')),
            array(array('/\.php$/'), array(), array('test.php', 'foo.php')),
            array(array(), array('/\.php$/'), array('test.py')),
        );
    }
}

class InnerNameIterator extends \ArrayIterator
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
