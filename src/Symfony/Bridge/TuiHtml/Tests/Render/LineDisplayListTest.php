<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Render\DisplayListRenderer;
use Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\CollapsedBorderGrid;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;

final class LineDisplayListTest extends TestCase
{
    public function testDeviceHalfPairsConserveOneCellInBothAxesAndDensities()
    {
        foreach ([[8.0, 16.0], [16.0, 32.0]] as [$width, $height]) {
            $metrics = new TerminalLayoutMetrics(new TerminalTextMeasurer($width, $height));
            self::assertSame([$width, 0.0], CollapsedBorderGrid::halves($width, false, $metrics));
            self::assertSame([$height, 0.0], CollapsedBorderGrid::halves($height, true, $metrics));
        }
    }

    public function testPortableLineEndpointsUseExistingBorderPatternsAndJunctions()
    {
        $line = ['type' => 'line', 'x1' => 0.0, 'y1' => 0.0, 'x2' => 24.0, 'y2' => 0.0, 'width' => 1.0, 'style' => 'double', 'color' => [[1.0, 0.0, 0.0], 1.0], 'start' => [false, true, true, false], 'end' => [false, false, true, true]];
        $output = (new DisplayListRenderer())->render(new DisplayList([$line]), 4, 1);
        self::assertSame('╔══╗', AnsiUtils::stripAnsiCodes($output[0]));
    }

    public function testLineClippingTranslationAndOpacityPreserveTheUsedGeometry()
    {
        $line = ['type' => 'line', 'x1' => 0.0, 'y1' => 0.0, 'x2' => 32.0, 'y2' => 0.0, 'width' => 1.0, 'style' => 'solid', 'color' => [[1.0, 0.0, 0.0], 1.0], 'start' => [false, true, true, false], 'end' => [false, false, true, true]];
        $output = (new DisplayListRenderer())->render(new DisplayList([['type' => 'push-clip', 'x' => 8.0, 'y' => 0.0, 'width' => 24.0, 'height' => 16.0], ['type' => 'push-transform', 'matrix' => [1.0, 0.0, 0.0, 1.0, 8.0, 0.0]], ['type' => 'push-opacity', 'opacity' => 0.5], $line, ['type' => 'pop-opacity'], ['type' => 'pop-transform'], ['type' => 'pop-clip']]), 6, 1);
        self::assertSame(' ┌──  ', AnsiUtils::stripAnsiCodes($output[0]));
        self::assertMatchesRegularExpression('/\x1b\[(?:0;)?2(?:;|m)/', $output[0]);
        $line['x2'] = 8.0;
        $line['y2'] = 16.0;
        $output = (new DisplayListRenderer())->render(new DisplayList([$line]), 4, 2);
        self::assertSame(['    ', '    '], array_map(AnsiUtils::stripAnsiCodes(...), $output));
    }

    public function testCollapsedTableHasOneCellFrameAndSharedInnerBorders()
    {
        $session = (new HtmlRenderer())->createSession('<style>table{border-collapse:collapse}td{padding:0;border:1px solid;width:1ch}</style><table><tr><td>A</td><td>B</td></tr></table>', new RenderingContext(160, 80));
        $output = (new DocumentWidget($session))->render(new RenderContext(20, 5));
        $plain = array_values(array_filter(array_map(static fn (string $line): string => rtrim(AnsiUtils::stripAnsiCodes($line)), $output), static fn (string $line): bool => '' !== $line));
        self::assertSame(['┌─┬─┐', '│A│B│', '└─┴─┘'], $plain);
        $document = $session->getDocument();
        $result = $session->render();
        self::assertSame(40.0, $result->getBounds($document->querySelector('table'))[0]->width);
        self::assertSame(48.0, $result->getBounds($document->querySelector('table'))[0]->height);
        self::assertSame(8.0, $result->getBounds($document->querySelector('td'))[0]->x);
    }
}
