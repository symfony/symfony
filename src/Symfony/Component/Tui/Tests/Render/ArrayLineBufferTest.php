<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\OutOfBoundsException;
use Symfony\Component\Tui\Render\ArrayLineBuffer;

class ArrayLineBufferTest extends TestCase
{
    public function testGetLineThrowsOnOutOfBoundsIndex()
    {
        $this->expectException(OutOfBoundsException::class);

        new ArrayLineBuffer(['A'])->getLine(1);
    }

    public function testSliceThrowsOnNegativeOffset()
    {
        $this->expectException(OutOfBoundsException::class);

        new ArrayLineBuffer(['A'])->slice(-1, 1);
    }
}
