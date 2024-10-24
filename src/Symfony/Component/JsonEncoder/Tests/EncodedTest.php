<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Encoded;

class EncodedTest extends TestCase
{
    public function testEncodedAsTraversable()
    {
        $this->assertSame(['foo', 'bar', 'baz'], iterator_to_array(new Encoded(new \ArrayIterator(['foo', 'bar', 'baz']))));
    }

    public function testEncodedAsString()
    {
        $this->assertSame('foobarbaz', (string) new Encoded(new \ArrayIterator(['foo', 'bar', 'baz'])));
    }
}
