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
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;

final class CacheGeometryPrecisionTest extends TestCase
{
    public function testReusedBoxesKeepDistinctContainingBlockMarginMetricsAtLowPrecision()
    {
        $precision = \ini_get('serialize_precision');
        ini_set('serialize_precision', '3');
        try {
            $shared = new Box(null, 'block', ['margin-top' => '10%', 'height' => '1px', 'width' => '1px']);
            $root = new Box(null, 'block', ['width' => '200px'], [new Box(null, 'block', ['width' => '100.01px', 'overflow' => 'hidden'], [$shared]), new Box(null, 'block', ['width' => '100.02px', 'overflow' => 'hidden'], [$shared])]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 200, 200);
            $first = $layout->children[0];
            $second = $layout->children[1];
            self::assertEqualsWithDelta(10.001, $first->children[0]->bounds->y - $first->bounds->y, 1.0E-10);
            self::assertEqualsWithDelta(10.002, $second->children[0]->bounds->y - $second->bounds->y, 1.0E-10);
        } finally {
            ini_set('serialize_precision', $precision);
        }
    }
}
