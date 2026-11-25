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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class InlineFormattingTest extends TestCase
{
    public function testAnonymousFlexItemTrimsItsOwnCollapsibleBoundarySpaces()
    {
        $text = new Box(null, 'block', [], [new Box(null, 'inline', [], [], 'Fruit ')]);
        $control = new Box(null, 'block', ['width' => '80px']);
        $box = new Box(null, 'flex', ['gap' => '8px'], [$text, $control]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 100);
        self::assertSame(40.0, $fragment->children[0]->bounds->width);
        self::assertSame(48.0, $fragment->children[1]->bounds->x);
        self::assertSame('Fruit ', $text->children[0]->text);
    }

    public function testCollapsedLineEndSpacesDoNotAffectAlignmentOrSemanticText()
    {
        $first = new Box(null, 'inline', [], [], 'A ');
        $last = new Box(null, 'inline', [], [], '  ');
        $box = new Box(null, 'block', ['text-align' => 'right'], [$first, $last]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 96, 100);
        $operations = (new Painter())->paint($fragment)->getOperations();
        self::assertSame(['A'], array_column($operations, 'text'));
        self::assertSame([88.0], array_column($operations, 'x'));
        self::assertSame([8.0], array_column($operations, 'width'));
        self::assertSame('A ', $first->text);
        self::assertSame('  ', $last->text);
    }

    public function testPreservedLineEndSpacesRemainVisible()
    {
        foreach (['pre', 'pre-wrap', 'break-spaces'] as $whiteSpace) {
            $box = new Box(null, 'block', ['white-space' => $whiteSpace], [new Box(null, 'inline', [], [], 'A  ')]);
            $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 96, 100);
            $operations = (new Painter())->paint($fragment)->getOperations();
            self::assertSame(['A  '], array_column($operations, 'text'));
            self::assertSame([24.0], array_column($operations, 'width'));
        }
    }

    public function testRunBoundarySpacesKeepTheirAdvance()
    {
        $box = new Box(null, 'block', [], [new Box(null, 'inline', [], [], 'Hello '), new Box(null, 'inline', [], [], 'World')]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 100);
        self::assertSame(48.0, $fragment->children[1]->bounds->x);
        self::assertSame(['Hello ', 'World'], array_column((new Painter())->paint($fragment)->getOperations(), 'text'));
    }

    public function testWhitespaceRunBetweenElementsOccupiesOneAdvance()
    {
        $box = new Box(null, 'block', [], [new Box(null, 'inline', [], [], 'A'), new Box(null, 'inline', [], [], " \n "), new Box(null, 'inline', [], [], 'B')]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 100);
        self::assertSame(16.0, $fragment->children[2]->bounds->x);
        self::assertSame(' ', $fragment->children[1]->children[0]->text);
    }

    public function testForcedBreaksFlushLinesAndPreserveBlankLines()
    {
        $box = new Box(null, 'block', [], [new Box(null, 'inline', [], [], 'A'), new Box(null, 'line-break'), new Box(null, 'line-break'), new Box(null, 'inline', [], [], 'B')]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 100);
        self::assertSame(38.4, $fragment->children[1]->bounds->y);
        self::assertEqualsWithDelta(57.6, $fragment->bounds->height, 0.000001);
        self::assertCount(2, $fragment->children);
    }

    public function testLongInlineUsesRemainingFirstLineAndFullSubsequentLine()
    {
        $html = '<style>main{width:100px;font-size:10px;line-height:10px}.atomic{display:inline-block;width:60px;height:10px}</style><main><i class="atomic"></i><span>one two three four</span><b>!</b></main>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(100, 100));
        $span = $result->getDocument()->querySelector('span');
        $bounds = $result->getBounds($span);
        self::assertGreaterThan(1, \count($bounds));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        self::assertSame(60.0, $text[0]['x']);
        self::assertSame(80.0, $text[1]['x']);
        self::assertSame(0.0, $text[2]['x']);
        self::assertSame(12.0, $text[2]['y']);
        self::assertSame('one two three four!', $result->getDocument()->querySelector('span')->textContent.$result->getDocument()->querySelector('b')->textContent);
        $lines = [];
        foreach ($text as $operation) {
            $lines[(string) $operation['y']] = ($lines[(string) $operation['y']] ?? '').$operation['text'];
        }
        self::assertSame(['one two', 'three four!'], array_values($lines));
    }

    public function testNestedInlineStylesAndCjkKeepIdentityAcrossFragments()
    {
        $html = '<style>main{width:50px;font-size:10px;line-height:10px}.atomic{display:inline-block;width:30px;height:10px}em{color:red}</style><main><i class="atomic"></i><span>日<em>本語中文</em>文</span></main>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(50, 100));
        self::assertGreaterThan(1, \count($result->getBounds($result->getDocument()->querySelector('span'))));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        self::assertSame(30.0, $text[0]['x']);
        self::assertSame('日本語中文文', implode('', array_column($text, 'text')));
        self::assertContains('red', array_column($text, 'color'));
    }

    public function testTrailingCollapsibleSpaceDoesNotMoveFittingWordToNextLine()
    {
        $html = '<style>main{width:80px;font-size:10px;line-height:10px}i{display:inline-block;width:65px;height:10px}</style><main><i></i><span>one two</span></main>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(80, 100));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        self::assertSame('one', $text[0]['text']);
        self::assertSame(65.0, $text[0]['x']);
        self::assertSame('two', $text[1]['text']);
        self::assertSame(0.0, $text[1]['x']);
        self::assertSame(12.0, $text[1]['y']);
    }

    public function testPreLinePreservesSegmentBreaksWhileCollapsingHorizontalWhitespace()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px', 'white-space' => 'pre-line'], [], "A   B\n   C\tD E");
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        $text = array_values(array_filter((new Painter())->paint($fragment)->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        self::assertSame(['A B', 'C D E'], array_column($text, 'text'));
        self::assertSame([0.0, 10.0], array_column($text, 'y'));
        self::assertSame(20.0, $fragment->bounds->height);
    }

    public function testStyleBoundariesDoNotCreateWordBreakOpportunities()
    {
        $result = (new HtmlRenderer())->render('<style>p{width:32px;font-size:16px;line-height:16px}</style><p><span style="color:red">A</span>lpha</p>', new RenderingContext(100, 100));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        self::assertSame($text[0]['y'], $text[1]['y']);
        self::assertSame(8.0, $text[1]['x']);
        $result = (new HtmlRenderer())->render('<style>p{width:48px;font-size:16px;line-height:16px;margin:0}</style><p>Hi <span>A</span>lpha</p>', new RenderingContext(100, 100));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        self::assertSame(0.0, $text[1]['x']);
        self::assertSame(16.0, $text[1]['y']);
        self::assertSame($text[1]['y'], $text[2]['y']);
    }

    public function testAnywhereCanBreakAcrossTheSameStyledWord()
    {
        $result = (new HtmlRenderer())->render('<style>p{width:16px;font-size:16px;line-height:16px;margin:0;overflow-wrap:anywhere}</style><p><span>A</span>lpha</p>', new RenderingContext(100, 100));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        self::assertSame('Alpha', implode('', array_column($text, 'text')));
        self::assertGreaterThan(0.0, max(array_column($text, 'y')));
    }
}
