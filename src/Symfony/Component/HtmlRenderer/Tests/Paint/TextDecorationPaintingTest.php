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
use Symfony\Component\HtmlRenderer\Text\TextSelection;

final class TextDecorationPaintingTest extends TestCase
{
    public function testDecorationsCarryStyleColorAndResolvedFractionalThickness()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'color' => 'red', 'text-decoration-line' => 'underline line-through', 'text-decoration-style' => 'wavy', 'text-decoration-color' => 'currentColor', 'text-decoration-thickness' => '12.5%'], [], 'Text');
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        $operation = (new Painter())->paint($fragment)->getOperations()[0];
        self::assertSame('underline line-through', $operation['text-decoration-line']);
        self::assertSame('wavy', $operation['text-decoration-style']);
        self::assertSame('red', $operation['text-decoration-color']);
        self::assertSame(1.25, $operation['text-decoration-thickness']);
    }

    public function testTextFillChangesGlyphsWithoutChangingBorderDecorationOrCaretColor()
    {
        $box = new Box(null, 'inline-block', ['color' => 'red', '-webkit-text-fill-color' => 'blue', 'border-width' => '1px', 'text-decoration-line' => 'underline', 'text-decoration-thickness' => 'from-font'], [], 'A', null, new TextSelection(0, 0));
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        $operations = (new Painter())->paint($fragment)->getOperations();
        self::assertSame('red', $operations[0]['color']);
        self::assertSame('blue', $operations[1]['color']);
        self::assertSame('red', $operations[1]['text-decoration-color']);
        self::assertSame('from-font', $operations[1]['text-decoration-thickness']);
        self::assertSame('#ff0000', $operations[2]['color']);
    }
}
