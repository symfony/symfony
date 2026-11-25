<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Vector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Vector\Geometry;

final class GeometryTest extends TestCase
{
    public function testExtractedPathMathPreservesFractionalPoints()
    {
        $paths = Geometry::path('M.5 .25L2.5 3.75z');
        $this->assertSame([['points' => [[0.5, 0.25], [2.5, 3.75]], 'closed' => true]], $paths);
        $this->assertSame([2.5, 3.75], Geometry::path('M.5 .25C1 1 2 2 2.5 3.75')[0]['points'][array_key_last(Geometry::path('M.5 .25C1 1 2 2 2.5 3.75')[0]['points'])]);
    }

    public function testExtractedTransformComposition()
    {
        $this->assertSame([2.0, 0.0, 0.0, 3.0, 0.5, 1.25], Geometry::transform('translate(.5 1.25) scale(2 3)'));
    }
}
