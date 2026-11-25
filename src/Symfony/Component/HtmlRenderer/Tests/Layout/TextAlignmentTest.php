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

final class TextAlignmentTest extends TestCase
{
    public function testParagraphCentersItsInlineLineGroup()
    {
        $box = new Box(null, 'block', ['text-align' => 'center'], [new Box(null, 'inline', [], [], 'Hello')]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(30.0, $fragment->children[0]->children[0]->bounds->x);
    }

    public function testWrappedLinesAlignIndividually()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px', 'text-align' => 'right'], [new Box(null, 'inline', [], [], 'one two three')]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 40, 100);
        $lines = $fragment->children[0]->children;
        self::assertSame(5.0, $lines[0]->bounds->x);
        self::assertSame(15.0, $lines[1]->bounds->x);
    }

    public function testMixedRunsShareOneCenteredGroupAndRtlStartUsesRightEdge()
    {
        $box = new Box(null, 'block', ['text-align' => 'center'], [new Box(null, 'inline', [], [], 'A'), new Box(null, 'inline', [], [], 'B')]);
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        $fragment = $engine->layout($box, 100, 100);
        self::assertSame(42.0, $fragment->children[0]->bounds->x);
        self::assertSame(50.0, $fragment->children[1]->bounds->x);
        $fragment = $engine->layout(new Box(null, 'block', ['direction' => 'rtl', 'text-align' => 'start'], [new Box(null, 'inline', [], [], 'Hello')]), 100, 100);
        self::assertSame(60.0, $fragment->children[0]->bounds->x);
    }

    public function testJustificationDistributesGeometryBetweenWordsExceptLastLine()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px', 'text-align' => 'justify'], [new Box(null, 'inline', [], [], 'one two three')]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 40, 100);
        $words = $fragment->children[0]->children;
        self::assertSame(['one', 'two', 'three'], array_column($words, 'text'));
        self::assertSame(25.0, $words[1]->bounds->x);
        self::assertSame(0.0, $words[2]->bounds->x);
        self::assertSame(10.0, $words[2]->bounds->y);
    }
}
