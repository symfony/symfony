<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Finder\Iterator;

use Symfony\Components\Finder\Iterator\FilenameFilterIterator;

require_once __DIR__.'/IteratorTestCase.php';

class FilenameFilterIteratorTest extends IteratorTestCase
{
    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($matchPatterns, $noMatchPatterns, $expected)
    {
        $inner = new Iterator(array('test.php', 'test.py', 'foo.php'));

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
