<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Content;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Content\CellDisplayList;
use Symfony\Bridge\TuiHtml\Internal\TextMarkers;
use Symfony\Bridge\TuiHtml\Render\CellBuffer;
use Symfony\Bridge\TuiHtml\Render\DisplayListRenderer;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;
use Symfony\Component\HtmlRenderer\Content\ContentLayout;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;

class CellDisplayListTest extends TestCase
{
    public function testCoverageWideGraphemesColorsAndHiddenCells()
    {
        $list = new CellDisplayList()->convert(["A\u{0301}界\x1b[7m \x1b[0;44;8msecret"], 20, 1, Color::hex('#eeeeee'), Color::hex('#111111'));
        $operations = $list->getOperations();
        $texts = array_values(array_filter($operations, static fn ($op) => 'text' === $op['type']));
        self::assertSame(["A\u{0301}", '界', ' '], array_column($texts, 'text'));
        self::assertSame(16.0, $texts[1]['width']);
        self::assertSame('#eeeeee', $texts[2]['color']);
        self::assertTrue($texts[2]['reverse']);
        $rectangles = array_values(array_filter($operations, static fn ($op) => 'rectangle' === $op['type']));
        self::assertCount(6, $rectangles);
        self::assertSame('#0000ee', $rectangles[0]['color']);
        self::assertStringNotContainsString("\x1b", json_encode($operations, \JSON_UNESCAPED_UNICODE));
    }

    public function testUnderlineHyperlinkBlinkAndCaretRemainSemantic()
    {
        $list = new CellDisplayList()->convert(["\x1b]8;;https://example.org\x07\x1b[4:3;58;2;255;0;0;5mX\x1b_pi:c1\x07"], 4, 1, Color::hex('#eeeeee'), Color::hex('#111111'));
        $text = $list->getOperations()[0];
        self::assertSame('https://example.org', $text['href']);
        self::assertSame('wavy', $text['text-decoration-style']);
        self::assertSame('#ff0000', $text['text-decoration-color']);
        self::assertStringContainsString('blink', $text['text-decoration-line']);
        self::assertSame('caret', $list->getOperations()[1]['type']);
        $lines = new DisplayListRenderer()->render($list, 4, 1);
        self::assertStringContainsString("\x1b]8;;https://example.org\x07", $lines[0]);
        self::assertStringContainsString(';5', $lines[0]);
    }

    public function testNativeLinkIdsAndFragmentTargetsRemainSemantic()
    {
        $first = RichTextWidget::linkOpening('https://example.org', 'native-first');
        $second = RichTextWidget::linkOpening('#target', 'native-second');
        $list = new CellDisplayList()->convert([$first.'A'.$second.'B'."\x1b]8;;\x07"], 2, 1, Color::hex('#eeeeee'), Color::named('default'));
        self::assertSame(['https://example.org', '#target'], array_column($list->getOperations(), 'href'));
        self::assertSame(['native-first', 'native-second'], array_column($list->getOperations(), 'link-id'));
        $lines = new DisplayListRenderer()->render($list, 2, 1);
        self::assertStringContainsString($first, $lines[0]);
        self::assertStringContainsString($second, $lines[0]);
        $operations = $list->getOperations();
        foreach (['bad:field', 'bad;field', "bad\x1b[31m", "bad\x07", "bad\r\n", "bad;\x1b]8;;https://invalid.example\x07"] as $id) {
            $operations[0]['link-id'] = $id;
            $lines = implode('', new DisplayListRenderer()->render(new DisplayList($operations), 2, 1));
            self::assertStringNotContainsString('id=bad', $lines);
            self::assertStringNotContainsString('https://invalid.example', $lines);
            self::assertStringContainsString("\x1b]8;;https://example.org\x07", $lines);
        }
    }

    public function testReverseKeepsTheActualParentBackdropAndDimIsOpacity()
    {
        $native = new CellDisplayList()->convert(["\x1b[7mX\x1b[0;2mY"], 4, 1, Color::hex('#eeeeee'), Color::hex('#000000'));
        self::assertSame('push-opacity', $native->getOperations()[1]['type']);
        self::assertSame(400, $native->getOperations()[2]['font-weight']);
        new ContentLayout(16, $native);
        $operations = [['type' => 'rectangle', 'x' => 0.0, 'y' => 0.0, 'width' => 32.0, 'height' => 16.0, 'color' => '#0000ee'], ...$native->getOperations()];
        $lines = new DisplayListRenderer()->render(new DisplayList($operations), 4, 1);
        $buffer = new CellBuffer(4, 1, false, true);
        $buffer->writeAnsiLines($lines);
        $cells = iterator_to_array($buffer->getCoveredCells());
        self::assertSame('#0000ee', $buffer->getBackgroundColor(0, 0)->toHex());
        self::assertSame(CellBuffer::ATTR_REVERSE, $cells[0]['attributes'] & CellBuffer::ATTR_REVERSE);
        self::assertSame(CellBuffer::ATTR_DIM, $cells[1]['attributes'] & CellBuffer::ATTR_DIM);
    }

    public function testKnownScaledMarkerBecomesPortableTextAndReservedGeometry()
    {
        $marker = TextMarkers::scaled('Hi', 2);
        $list = new CellDisplayList()->convert([$marker.'    ', '    '], 4, 2, Color::hex('#eeeeee'), Color::hex('#000000'));
        $texts = array_values(array_filter($list->getOperations(), static fn ($op) => 'text' === $op['type'] && ' ' !== $op['text']));
        self::assertSame(['H', 'i'], array_column($texts, 'text'));
        self::assertSame([32.0, 32.0], array_column($texts, 'font-size'));
        self::assertSame([0.0, 16.0], array_column($texts, 'x'));
        new ContentLayout(32, $list);
        $lines = new DisplayListRenderer(new TerminalTextMeasurer(8, 16, true), new MediaContext())->render($list, 4, 2);
        // The portable list is valid independently; transport opt-in remains a backend decision.
        self::assertCount(2, $lines);
    }

    public function testUnknownVisualProtocolFailsExplicitly()
    {
        $this->expectException(\InvalidArgumentException::class);
        new CellDisplayList()->convert(["\x1bPqopaque-sixel\x1b\\"], 4, 1, Color::hex('#eeeeee'), Color::hex('#000000'));
    }
}
