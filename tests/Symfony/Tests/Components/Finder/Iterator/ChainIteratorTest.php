<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Finder\Iterator;

use Symfony\Components\Finder\Iterator\ChainIterator;

require_once __DIR__.'/IteratorTestCase.php';

class ChainIteratorTest extends IteratorTestCase
{
  public function testAccept()
  {
    $inner1 = new Iterator(array('test.php', 'test.py'));
    $inner2 = new Iterator(array());
    $inner3 = new Iterator(array('foo.php'));

    $iterator = new ChainIterator(array($inner1, $inner2, $inner3));

    $this->assertIterator(array('test.php', 'test.py', 'foo.php'), $iterator);
  }
}
