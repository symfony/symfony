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

use Symfony\Components\Finder\Iterator\IgnoreVcsFilterIterator;

require_once __DIR__.'/IteratorTestCase.php';

class IgnoreVcsFilterIteratorTest extends IteratorTestCase
{
    public function testAccept()
    {
        $inner = new Iterator(array('/.git/test.php', '/foo/test.py', '/bar/foo.php'));

        $iterator = new IgnoreVcsFilterIterator($inner);

        $this->assertIterator(array('/foo/test.py', '/bar/foo.php'), $iterator);
    }
}
