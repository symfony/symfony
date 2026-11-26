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
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\Text\TextSelection;

final class BreakWordTest extends TestCase
{
    public function testMovesWordsBeforeEmergencyWrapping()
    {
        $box = new Box(null, 'block', ['overflow-wrap' => 'break-word'], [], 'A abcdefgh Z');
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 40, 200);
        self::assertSame(['A', 'abcde', 'fgh Z'], array_column((new Painter())->paint($fragment)->getOperations(), 'text'));
    }

    public function testNestedWordsMoveBeforeEmergencyWrapping()
    {
        foreach (['break-word', 'anywhere'] as $mode) {
            $result = (new HtmlRenderer())->render('<style>p{margin:0;width:40px;line-height:16px;overflow-wrap:'.$mode.'}</style><p>A <span>abcdefgh</span> Z</p>', new RenderingContext(200, 200));
            $lines = [];
            foreach ($result->getDisplayList() as $operation) {
                if ('text' === $operation['type']) {
                    $lines[(string) $operation['y']] = ($lines[(string) $operation['y']] ?? '').$operation['text'];
                }
            }
            self::assertSame(['A', 'abcde', 'fgh Z'], array_values($lines));
            self::assertSame('A abcdefgh Z', $result->getDocument()->querySelector('p')->textContent);
        }
    }

    public function testKeepsNormalMinimumContentWidth()
    {
        foreach (['normal' => 64.0, 'break-word' => 64.0, 'anywhere' => 8.0] as $mode => $width) {
            $box = new Box(null, 'block', ['overflow-wrap' => $mode, 'width' => 'min-content'], [], 'abcdefgh');
            self::assertSame($width, (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 200)->bounds->width);
        }
    }

    public function testPreservesWhiteSpaceAndGraphemes()
    {
        foreach (['nowrap', 'pre'] as $whiteSpace) {
            $box = new Box(null, 'block', ['overflow-wrap' => 'break-word', 'white-space' => $whiteSpace], [], 'A abcdefgh');
            self::assertSame(['A abcdefgh'], array_column((new Painter())->paint((new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 40, 200))->getOperations(), 'text'));
        }
        $box = new Box(null, 'block', ['overflow-wrap' => 'break-word'], [], "a\u{0301}b\u{0301}c\u{0301}");
        self::assertSame(["a\u{0301}", "b\u{0301}", "c\u{0301}"], array_column((new Painter())->paint((new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 8, 200))->getOperations(), 'text'));
    }

    public function testUsesFractionalProportionalMeasurements()
    {
        $measurer = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                $width = 0.0;
                foreach (mb_str_split($text) as $character) {
                    $width += 'W' === $character ? 7.25 : 2.5;
                }

                return new TextMetrics($width, 8.5, 2.25);
            }
        };
        $box = new Box(null, 'block', ['overflow-wrap' => 'break-word'], [], 'ii WWWW ii');
        $fragment = (new LayoutEngine($measurer))->layout($box, 17.25, 100);
        self::assertSame(['ii', 'WW', 'WW', 'ii'], array_column((new Painter($measurer))->paint($fragment)->getOperations(), 'text'));
    }

    public function testPreservesNonBreakingSpacesAndSourceOffsets()
    {
        $box = new Box(null, 'block', ['overflow-wrap' => 'break-word'], [], "a\u{00A0}bcde", null, new TextSelection(6, 6));
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 24, 100);
        self::assertSame(["a\u{00A0}b", 'cde'], array_map(static fn ($child) => $child->text, $fragment->children));
        self::assertSame(3, $fragment->children[1]->run->start);
        self::assertSame(6, $fragment->children[1]->run->end);
        self::assertSame(6, $fragment->children[1]->run->toSource(3));
    }

    public function testWrapsWithinTheAvailableFloatInterval()
    {
        $result = (new HtmlRenderer())->render('<style>p{margin:0;width:64px;line-height:16px;overflow-wrap:break-word}i{float:left;width:24px;height:48px}</style><p><i></i>abcdefgh</p>', new RenderingContext(100, 100));
        $operations = array_values(array_filter($result->getDisplayList()->getOperations(), static fn ($operation) => 'text' === $operation['type']));
        $lines = [];
        foreach ($operations as $operation) {
            $lines[(string) $operation['y']] = ($lines[(string) $operation['y']] ?? '').$operation['text'];
            self::assertGreaterThanOrEqual(24.0, $operation['x']);
            self::assertLessThanOrEqual(64.0, $operation['x'] + $operation['width']);
        }
        self::assertSame(['abcde', 'fgh'], array_values($lines));
    }
}
