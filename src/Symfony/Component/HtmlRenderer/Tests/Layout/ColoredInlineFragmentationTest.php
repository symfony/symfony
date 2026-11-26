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
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\InlineFormatting;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class ColoredInlineFragmentationTest extends TestCase
{
    public function testSolidBackgroundDoesNotChangeWrappingGeometry()
    {
        foreach (['anywhere', 'break-word'] as $mode) {
            $plain = $this->render('transparent', 'abcdefgh', 'overflow-wrap:'.$mode);
            $colored = $this->render('red', 'abcdefgh', 'overflow-wrap:'.$mode);
            self::assertSame($this->textGeometry($plain), $this->textGeometry($colored));
            self::assertSame(['A', 'abcde', 'fgh Z'], $this->lines($colored));
        }
    }

    public function testUniformShorthandPaintsTheSameFragmentsAsItsColorLonghand()
    {
        $html = '<style>p{margin:0;width:40px;line-height:16px;overflow-wrap:break-word}span{background:red}</style><p>A <span>abcdefgh</span> Z</p>';
        $operations = (new HtmlRenderer())->render($html, new RenderingContext(200, 200))->getDisplayList()->getOperations();
        self::assertSame($this->render('red', 'abcdefgh'), $operations);
    }

    public function testAlphaBackgroundCoversEachPointOnlyOnce()
    {
        $operations = $this->render('rgba(255,0,0,.5)', 'abcdefgh');
        $rectangles = array_values(array_filter($operations, static fn ($op) => 'rectangle' === $op['type']));
        self::assertNotEmpty($rectangles);
        $area = 0.0;
        foreach ($rectangles as $index => $rectangle) {
            self::assertSame('rgba(255, 0, 0, 0.5)', $rectangle['color']);
            $area += $rectangle['width'] * $rectangle['height'];
            foreach (\array_slice($rectangles, $index + 1) as $other) {
                $width = max(0, min($rectangle['x'] + $rectangle['width'], $other['x'] + $other['width']) - max($rectangle['x'], $other['x']));
                $height = max(0, min($rectangle['y'] + $rectangle['height'], $other['y'] + $other['height']) - max($rectangle['y'], $other['y']));
                self::assertEqualsWithDelta(0.0, $width * $height, 0.00001);
            }
        }
        self::assertEqualsWithDelta(64.0 * 16, $area, 0.00001);
    }

    public function testNestedColorsKeepTheOriginalNodes()
    {
        $document = \Dom\HTMLDocument::createFromString('<style>p{margin:0;width:40px;line-height:16px;overflow-wrap:break-word}span{background:red}b{background:blue;font-weight:normal}</style><p>A <span>abcd<b>efgh</b></span> Z</p>', \LIBXML_NOERROR);
        $source = $document->saveHtml();
        $span = $document->querySelector('span');
        $bold = $document->querySelector('b');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(200, 200));
        $operations = $result->getDisplayList()->getOperations();
        self::assertSame(['A', 'abcde', 'fgh Z'], $this->lines($operations));
        self::assertSame($source, $document->saveHtml());
        self::assertSame($span, $document->querySelector('span'));
        self::assertSame($bold, $document->querySelector('b'));
        self::assertNotNull($result->getBounds($span));
        $colors = array_column(array_filter($operations, static fn ($op) => 'rectangle' === $op['type']), 'color');
        self::assertContains('red', $colors);
        self::assertContains('blue', $colors);
    }

    public function testCollapsedLineEndSpaceDoesNotExtendTheBackground()
    {
        $operations = $this->render('red', 'abcde fgh');
        self::assertSame(['A', 'abcde', 'fgh Z'], $this->lines($operations));
        $ends = [];
        foreach ($operations as $operation) {
            if ('rectangle' === $operation['type']) {
                $ends[(string) $operation['y']] = max($ends[(string) $operation['y']] ?? 0, $operation['x'] + $operation['width']);
            }
        }
        self::assertSame([40.0, 24.0], array_values($ends));
    }

    public function testProportionalAndRightToLeftTextKeepTheirGeometry()
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
        foreach (['ltr', 'rtl'] as $direction) {
            $plain = $this->render('transparent', 'WWWWWWWW', 'direction:'.$direction, $measurer);
            $colored = $this->render('red', 'WWWWWWWW', 'direction:'.$direction, $measurer);
            self::assertSame($this->textGeometry($plain), $this->textGeometry($colored));
            self::assertSame(['A', 'WWWWW', 'WWW Z'], $this->lines($colored));
        }
    }

    public function testClippedTransformedParentKeepsThePaintStack()
    {
        $style = 'height:32px;overflow:hidden;transform:translate(2.5px,3.25px);opacity:.5';
        $plain = $this->render('transparent', 'abcdefgh', $style);
        $colored = $this->render('red', 'abcdefgh', $style);
        self::assertSame($this->textGeometry($plain), $this->textGeometry($colored));
        $stack = [];
        foreach ($colored as $operation) {
            if (str_starts_with($operation['type'], 'push-') || str_starts_with($operation['type'], 'pop-')) {
                $stack[] = $operation['type'];
            }
        }
        self::assertContains('push-clip', $stack);
        self::assertContains('push-transform', $stack);
        self::assertContains('push-opacity', $stack);
        self::assertSame(\count(array_filter($stack, static fn ($op) => str_starts_with($op, 'push-'))), \count(array_filter($stack, static fn ($op) => str_starts_with($op, 'pop-'))));
    }

    public function testOtherDecorationsAndBlockSplittingRemainExcluded()
    {
        foreach ([['border-width' => '1px'], ['padding-left' => '1px'], ['background-image' => 'linear-gradient(red,blue)'], ['background-image' => 'url(example.png)'], ['background-clip' => 'text'], ['background-origin' => 'content-box'], ['opacity' => '.5']] as $decoration) {
            $box = new Box(null, 'inline', ['background-color' => 'red'] + $decoration, [], 'abcdefgh');
            self::assertNull(InlineFormatting::split($box, true));
        }
        $block = new Box(null, 'block', [], [], 'block');
        $remaining = 100;
        self::assertNull(InlineFormatting::splitBlocks(new Box(null, 'inline', ['background-color' => 'red'], [$block]), $remaining));
        self::assertFalse(InlineFormatting::collapsibleWhitespace(new Box(null, 'inline', ['background-color' => 'red'], [], ' ')));
    }

    private function render(string $background, string $text, string $extra = '', ?TextMeasurerInterface $measurer = null): array
    {
        $html = '<style>p{margin:0;width:40px;line-height:16px;overflow-wrap:break-word;'.$extra.'}span{background-color:'.$background.'}</style><p>A <span>'.$text.'</span> Z</p>';

        return (new HtmlRenderer())->render($html, new RenderingContext(200, 200, $measurer))->getDisplayList()->getOperations();
    }

    private function textGeometry(array $operations): array
    {
        return array_values(array_map(static fn ($op) => array_intersect_key($op, array_flip(['text', 'x', 'y', 'width', 'height'])), array_filter($operations, static fn ($op) => 'text' === $op['type'])));
    }

    private function lines(array $operations): array
    {
        $lines = [];
        foreach ($operations as $operation) {
            if ('text' === $operation['type']) {
                $lines[(string) $operation['y']] = ($lines[(string) $operation['y']] ?? '').$operation['text'];
            }
        }

        return array_values($lines);
    }
}
