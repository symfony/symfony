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

use Symfony\Components\Finder\Iterator\ExcludeDirectoryFilterIterator;

require_once __DIR__.'/IteratorTestCase.php';

class ExcludeDirectoryFilterIteratorTest extends IteratorTestCase
{
    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($directories, $expected)
    {
        $inner = new Iterator(array('/foo/test.php', '/foo/test.py', '/bar/foo.php'));

        $iterator = new ExcludeDirectoryFilterIterator($inner, $directories);

        $this->assertIterator($expected, $iterator);
    }

    public function getAcceptData()
    {
        return array(
            array(array('foo'), array('/bar/foo.php')),
            array(array('fo'), array('/foo/test.php', '/foo/test.py', '/bar/foo.php')),
        );
    }
}
