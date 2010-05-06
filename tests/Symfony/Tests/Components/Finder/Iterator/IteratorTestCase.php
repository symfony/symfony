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

require_once __DIR__.'/Iterator.php';

class IteratorTestCase extends \PHPUnit_Framework_TestCase
{
    protected function assertIterator($expected, \Iterator $iterator)
    {
        $values = array_map(function (\SplFileInfo $fileinfo) { return $fileinfo->getPathname(); }, iterator_to_array($iterator));

        $this->assertEquals($expected, array_values($values));
    }
}
