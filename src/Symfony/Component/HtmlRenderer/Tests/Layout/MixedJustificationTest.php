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
use Symfony\Component\HtmlRenderer\RenderingResult;
use Symfony\Component\HtmlRenderer\Text\TextSelection;

final class MixedJustificationTest extends TestCase
{
    public function testWordsAcrossStyledRunsShareTheParagraphsJustifiedLine()
    {
        $result = (new HtmlRenderer())->render('<p style="margin:0;width:80px;font-size:16px;line-height:16px;text-align:justify">one <em>two</em> three end</p>', new RenderingContext(200, 100));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        $two = array_values(array_filter($text, static fn (array $operation): bool => 'two' === $operation['text']))[0];
        self::assertSame(56.0, $two['x']);
        self::assertSame(0.0, $two['y']);
        self::assertSame('one two three end', $result->getDocument()->querySelector('p')->textContent);
    }

    public function testForcedAndLastLinesHonorTheirOwnAlignment()
    {
        foreach (['' => 32.0, 'text-justify:none' => 32.0, 'text-align-last:justify' => 56.0] as $style => $x) {
            $result = (new HtmlRenderer())->render('<p style="margin:0;width:80px;font-size:16px;line-height:16px;text-align:justify;'.$style.'">one <em>two</em><br>three end</p>', new RenderingContext(200, 100));
            $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type'] && 'two' === $operation['text']));
            self::assertSame($x, $text[0]['x'], $style);
        }
    }

    public function testInterCharacterJustificationCrossesStyledRunsAndKeepsGraphemesTogether()
    {
        $result = (new HtmlRenderer())->render('<p style="margin:0;width:80.5px;font-size:16px;line-height:16px;text-align:justify;text-align-last:justify;text-justify:inter-character">á<em>b</em>c</p>', new RenderingContext(200, 100));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        self::assertSame(['á', 'b', 'c'], array_column($text, 'text'));
        self::assertSame([0.0, 36.25, 72.5], array_column($text, 'x'));
    }

    public function testAutomaticIdeographicJustificationCrossesStyledRuns()
    {
        $result = (new HtmlRenderer())->render('<p style="margin:0;width:80px;font-size:16px;line-height:16px;text-align:justify;text-align-last:justify">一<em>二</em>三</p>', new RenderingContext(200, 100));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        self::assertSame(['一', '二', '三'], array_column($text, 'text'));
        self::assertSame([0.0, 36.0, 72.0], array_column($text, 'x'));
    }

    public function testWhitespaceCollapsesAcrossEmptyInlineBoxesButNotAtomicBoxes()
    {
        foreach (['inline' => 'after.', 'inline-block' => ' after.'] as $display => $text) {
            $result = (new HtmlRenderer())->render('<p style="margin:0;font-size:16px;line-height:16px">Before <span style="display:'.$display.'"></span> after.</p>', new RenderingContext(200, 100));
            $operations = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
            self::assertSame($text, $operations[1]['text'], $display);
            self::assertSame(56.0, $operations[1]['x'], $display);
            self::assertSame('Before  after.', $result->getDocument()->querySelector('p')->textContent);
        }
    }

    public function testCollapsedLeadingSpaceRetainsTheOriginalSelectionOffsets()
    {
        $box = new Box(null, 'block', ['font-size' => '16px', 'line-height' => '16px'], [
            new Box(null, 'inline', [], [], 'one '),
            new Box(null, 'inline', [], [], ' two', null, new TextSelection(1, 2)),
        ]);
        $measurer = new MonospaceTextMeasurer();
        $fragment = (new LayoutEngine($measurer))->layout($box, 200, 100);
        $operations = (new Painter($measurer))->paint($fragment)->getOperations();
        $selection = array_values(array_filter($operations, static fn (array $operation): bool => 'rectangle' === $operation['type']));
        self::assertSame(32.0, $selection[0]['x']);
        self::assertSame(8.0, $selection[0]['width']);
        $caret = array_values(array_filter($operations, static fn (array $operation): bool => 'caret' === $operation['type']));
        self::assertSame(40.0, $caret[0]['x']);
    }

    public function testExpandedSpacesRetainSelectionAndProviderCaretMapping()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><p>one <em>two</em> three end</p>');
        $paragraph = $document->querySelector('p');
        $emphasis = $document->querySelector('em');
        $measurer = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                $count = preg_match_all('/\X/u', $text);

                return new TextMetrics($count * 8, 12, 4, array_map(static fn (int $offset): float => $offset * 8, range(0, $count)));
            }
        };
        $box = new Box($paragraph, 'block', ['width' => '80px', 'font-size' => '16px', 'line-height' => '16px', 'text-align' => 'justify'], [
            new Box($paragraph->firstChild, 'inline', [], [], 'one ', null, new TextSelection(3, 4)),
            new Box($emphasis, 'inline', [], [new Box($emphasis->firstChild, 'inline', [], [], 'two')]),
            new Box($paragraph->lastChild, 'inline', [], [], ' three end'),
        ]);
        $layout = (new LayoutEngine($measurer))->layout($box, 80, 100);
        $displayList = (new Painter($measurer))->paint($layout);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, $displayList, $measurer);
        self::assertSame(3, $result->getTextPosition($paragraph, 30, 8));
        self::assertSame(4, $result->getTextPosition($paragraph, 50, 8));
        $selection = array_values(array_filter($displayList->getOperations(), static fn (array $operation): bool => 'rectangle' === $operation['type']));
        self::assertSame(24.0, $selection[0]['x']);
        self::assertSame(32.0, $selection[0]['width']);
        $carets = array_values(array_filter($displayList->getOperations(), static fn (array $operation): bool => 'caret' === $operation['type']));
        self::assertCount(1, $carets);
        self::assertSame(56.0, $carets[0]['x']);
    }
}
