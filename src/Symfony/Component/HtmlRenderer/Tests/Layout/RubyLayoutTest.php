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
use Symfony\Component\HtmlRenderer\Layout\RubyGeometry;
use Symfony\Component\HtmlRenderer\Paint\Painter;

final class RubyLayoutTest extends TestCase
{
    public function testFractionalRubyGeometryCentersBothRunsAndPlacesUnderAnnotation()
    {
        self::assertSame(['width' => 10.5, 'height' => 15.5, 'base' => [0.0, 5.25], 'annotation' => [3.125, 0.0]], RubyGeometry::place(10.5, 4.25, 10.25, 5.25));
        self::assertSame(['width' => 10.5, 'height' => 15.5, 'base' => [0.0, 0.0], 'annotation' => [0.0, 10.25]], RubyGeometry::place(10.5, 4.25, 10.25, 5.25, true, true));
    }

    public function testPairIntrinsicWidthIsMaximumRatherThanSumOfRuns()
    {
        $pair = static fn () => new Box(null, 'ruby-pair', [], [new Box(null, 'ruby-base', [], [], 'AB'), new Box(null, 'ruby-text', ['font-size' => '5px', 'line-height' => '5px'], [], 'x')]);
        $root = new Box(null, 'block', ['width' => '22px', 'font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'ruby', [], [$pair(), $pair()])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 100, 100);
        self::assertSame(15.0, $layout->bounds->height);
    }

    public function testRubyPairsWrapBetweenBasesWithAnnotationsOnTheirOwnLine()
    {
        $pair = static fn () => new Box(null, 'ruby-pair', [], [new Box(null, 'ruby-base', [], [], 'AB'), new Box(null, 'ruby-text', ['font-size' => '5px', 'line-height' => '5px'], [], 'x')]);
        $root = new Box(null, 'block', ['width' => '15px', 'font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'ruby', [], [$pair(), $pair()])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 100, 100);
        $text = array_values(array_filter((new Painter())->paint($layout)->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertSame(['AB', 'x', 'AB', 'x'], array_column($text, 'text'));
        self::assertSame([5.0, 0.0, 20.0, 15.0], array_column($text, 'y'));
        self::assertSame([0.0, 3.75, 0.0, 3.75], array_column($text, 'x'));
        self::assertSame(30.0, $layout->bounds->height);
    }
}
