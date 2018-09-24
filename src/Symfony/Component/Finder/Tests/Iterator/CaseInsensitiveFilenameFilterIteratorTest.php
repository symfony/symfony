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

use Symfony\Component\Finder\Iterator\FilenameFilterIterator;

class CaseInsensitiveFilenameFilterIteratorTest extends IteratorTestCase
{
    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($matchPatterns, $noMatchPatterns, $expected)
    {
        $inner = new InnerCaseInsensitiveFilenameFilterIterator(array('test.php', 'test.PY', 'foo.php', 'baz.PHP'));

        $iterator = new FilenameFilterIterator($inner, $matchPatterns, $noMatchPatterns, false);

        $this->assertIterator($expected, $iterator);
    }

    public function getAcceptData()
    {
        return array(
            array(array('test.*'), array(), array('test.php', 'test.PY')),
            array(array(), array('test.*'), array('foo.php', 'baz.PHP')),
            array(array('*.php'), array('test.*'), array('foo.php', 'baz.PHP')),
            array(array('*.php', '*.py'), array('foo.*'), array('test.php', 'test.PY', 'baz.PHP')),
            array(array('/\.php$/'), array(), array('test.php', 'foo.php', 'baz.PHP')),
            array(array('/\.php$/i'), array(), array('test.php', 'foo.php', 'baz.PHP')),
            array(array(), array('/\.php$/'), array('test.PY')),
        );
    }
}

class InnerCaseInsensitiveFilenameFilterIterator extends \ArrayIterator
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
