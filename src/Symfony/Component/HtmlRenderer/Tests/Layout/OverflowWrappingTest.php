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

final class OverflowWrappingTest extends TestCase
{
    public function testAnywherePrefersOrdinaryWordBoundaries()
    {
        $box = new Box(null, 'block', ['overflow-wrap' => 'anywhere'], [], 'A dashed underline text');
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 160, 100);
        self::assertSame(['A dashed underline', 'text'], array_column((new Painter())->paint($fragment)->getOperations(), 'text'));
    }

    public function testAnywhereMovesAnUnbreakableWordBeforeEmergencyWrapping()
    {
        $box = new Box(null, 'block', ['overflow-wrap' => 'anywhere'], [], 'AAAA abcdefghijklmnop');
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 64, 100);
        self::assertSame(['AAAA', 'abcdefgh', 'ijklmnop'], array_column((new Painter())->paint($fragment)->getOperations(), 'text'));
    }

    public function testEmergencyWrappingDoesNotOverrideWhiteSpaceOrExplicitBreakAll()
    {
        foreach (['nowrap', 'pre'] as $whiteSpace) {
            $box = new Box(null, 'block', ['overflow-wrap' => 'anywhere', 'white-space' => $whiteSpace], [], 'AAAA abcdefghijklmnop');
            $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 64, 100);
            self::assertSame(['AAAA abcdefghijklmnop'], array_column((new Painter())->paint($fragment)->getOperations(), 'text'));
        }
        $box = new Box(null, 'block', ['word-break' => 'break-all'], [], 'AAAA abcdefghijklmnop');
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 64, 100);
        self::assertSame(['AAAA abc', 'defghijk', 'lmnop'], array_column((new Painter())->paint($fragment)->getOperations(), 'text'));
    }

    public function testAnywhereMinimumContentWidthDoesNotReserveAHyphen()
    {
        $box = new Box(null, 'block', ['overflow-wrap' => 'anywhere', 'width' => 'min-content', 'hyphens' => 'manual'], [], "ab\u{00AD}cd");
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(8.0, $fragment->bounds->width);
    }

    public function testAnywherePreservesGraphemeClusters()
    {
        $box = new Box(null, 'block', ['overflow-wrap' => 'anywhere'], [], "a\u{0301}b\u{0301}c\u{0301}");
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 8, 100);
        self::assertSame(["a\u{0301}", "b\u{0301}", "c\u{0301}"], array_column((new Painter())->paint($fragment)->getOperations(), 'text'));
    }

    public function testEmergencyBreaksPreserveTheLineWidthAcrossStyledRuns()
    {
        $result = (new HtmlRenderer())->render('<style>p{width:16px;font-size:16px;line-height:16px;margin:0;overflow-wrap:anywhere}</style><p><span>Alph</span>aBeta</p>', new RenderingContext(100, 100));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        $lines = [];
        foreach ($text as $operation) {
            $lines[(string) $operation['y']] = ($lines[(string) $operation['y']] ?? '').$operation['text'];
        }
        self::assertSame(['Al', 'ph', 'aB', 'et', 'a'], array_values($lines));
    }

    public function testWrappedDecorationDoesNotPaintCollapsedLineEndSpaces()
    {
        $result = (new HtmlRenderer())->render('<style>p{margin:0;width:160px;font-size:16px;line-height:16px;overflow-wrap:anywhere;text-decoration-line:underline;text-decoration-style:dashed}</style><p>A dashed underline that wraps onto the next lines</p>', new RenderingContext(200, 200));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        $lines = [];
        foreach ($text as $operation) {
            $lines[(string) $operation['y']] = ($lines[(string) $operation['y']] ?? '').$operation['text'];
        }
        foreach ($lines as $line) {
            self::assertStringEndsNotWith(' ', $line, 'Collapsed line-end space must not extend the text decoration.');
        }
        self::assertSame(['A dashed underline', 'that wraps onto the', 'next lines'], array_values($lines));
        self::assertSame('A dashed underline that wraps onto the next lines', $result->getDocument()->querySelector('p')->textContent);
    }

    public function testOrdinaryWordBoundariesAcrossStyledRunsTakePrecedence()
    {
        $result = (new HtmlRenderer())->render('<style>p{width:160px;font-size:16px;line-height:16px;margin:0;overflow-wrap:anywhere}</style><p>A dashed <span>underline</span> text</p>', new RenderingContext(200, 100));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        $lines = [];
        foreach ($text as $operation) {
            $lines[(string) $operation['y']] = ($lines[(string) $operation['y']] ?? '').$operation['text'];
        }
        self::assertSame(['A dashed underline', 'text'], array_values($lines));
    }
}
