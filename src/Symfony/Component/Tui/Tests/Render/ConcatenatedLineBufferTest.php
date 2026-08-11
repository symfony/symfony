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
use Symfony\Component\Tui\Render\ConcatenatedLineBuffer;

class ConcatenatedLineBufferTest extends TestCase
{
    public function testGetLineThrowsOnOutOfBoundsIndex()
    {
        $this->expectException(OutOfBoundsException::class);

        new ConcatenatedLineBuffer([new ArrayLineBuffer(['A'])])->getLine(1);
    }

    public function testSliceThrowsOnNegativeLength()
    {
        $this->expectException(OutOfBoundsException::class);

        new ConcatenatedLineBuffer([new ArrayLineBuffer(['A'])])->slice(0, -1);
    }
}
