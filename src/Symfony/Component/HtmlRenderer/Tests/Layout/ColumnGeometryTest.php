<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Layout\ColumnGeometry;

class ColumnGeometryTest extends TestCase
{
    public function testUnbreakableContentAndForcedCutsRespectColumnCapacity()
    {
        self::assertSame([[0.0, 20.0]], ColumnGeometry::partition(20, 1, [], [4.0, 8.0, 12.0], 0));
        self::assertSame([[0.0, 4.0], [4.0, 20.0]], ColumnGeometry::partition(20, 2, [], [4.0, 8.0, 12.0], 0));
        self::assertSame([[0.0, 4.0], [4.0, 8.0], [8.0, 12.0], [12.0, 20.0]], ColumnGeometry::partition(20, 2, [], [4.0, 8.0, 12.0], 0, true));
    }
}
