<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Paint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;

final class BorderRadiusPaintingTest extends TestCase
{
    public function testEllipticalPercentageRadiiResolveAgainstBorderBoxAndScaleOverlaps()
    {
        $box = new Box(null, 'block', ['box-sizing' => 'border-box', 'width' => '100px', 'height' => '50px', 'border-width' => '1px', 'border-radius' => '60% / 80%', 'border-top-left-radius' => 'calc(50% + 10px) 40px']);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 100);
        $border = (new Painter())->paint($layout)->getOperations()[0];
        self::assertSame('border', $border['type']);
        self::assertSame([[37.5, 25.0], [37.5, 25.0], [37.5, 25.0], [37.5, 25.0]], $border['radii']);
    }

    public function testFractionalIndependentCornerRadiiRemainNumeric()
    {
        $box = new Box(null, 'block', ['width' => '20px', 'height' => '10px', 'border-width' => '1px', 'border-top-left-radius' => '2.5px 1.25px', 'border-bottom-right-radius' => '3px']);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        $border = (new Painter())->paint($layout)->getOperations()[0];
        self::assertSame([[2.5, 1.25], [0.0, 0.0], [3.0, 3.0], [0.0, 0.0]], $border['radii']);
    }
}
