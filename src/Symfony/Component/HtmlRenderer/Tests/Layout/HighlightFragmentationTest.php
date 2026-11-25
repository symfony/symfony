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
use Symfony\Component\HtmlRenderer\Paint\Painter;

final class HighlightFragmentationTest extends TestCase
{
    public function testHighlightedBackgroundFollowsActualWrappedTextFragments()
    {
        $box = new Box(null, 'block', ['width' => '35px', 'font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'inline', ['-renderer-highlight' => '1', 'background-color' => 'red'], [], 'one two three')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        $operations = (new Painter())->paint($layout)->getOperations();
        $backgrounds = array_values(array_filter($operations, static fn (array $op) => 'rectangle' === $op['type']));
        self::assertSame([20.0, 15.0, 25.0], array_column($backgrounds, 'width'));
        self::assertSame([0.0, 0.0, 10.0], array_column($backgrounds, 'y'));
        self::assertSame([10.0, 10.0, 10.0], array_column($backgrounds, 'height'));
        $text = array_values(array_filter($operations, static fn (array $op) => 'text' === $op['type']));
        self::assertSame(['one ', 'two', 'three'], array_column($text, 'text'));
    }
}
