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
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;

final class ScrollbarRenderingTest extends TestCase
{
    public function testDeviceThicknessAndThumbOffsetHaveDifferentMinimumRules()
    {
        foreach ([[8, 16], [16, 32]] as [$width, $height]) {
            $metrics = new TerminalLayoutMetrics(new TerminalTextMeasurer($width, $height));
            self::assertSame((float) $width, $metrics->scrollbarThickness(true));
            self::assertSame((float) $height, $metrics->scrollbarThickness(false));
            self::assertSame((float) $width, $metrics->scrollbarThickness(true, true));
            self::assertSame(0.0, $metrics->constrainLength($height / 4, 'scrollbar-thumb-offset', true));
            self::assertSame((float) $height, $metrics->constrainLength($height / 4, 'scrollbar-thumb-length', true));
        }
    }

    public function testVerticalAndHorizontalGlyphsReuseTheTerminalPresentation()
    {
        $vertical = $this->operation();
        $horizontal = ['type' => 'scrollbar', 'axis' => 'x', 'track' => ['x' => 0.0, 'y' => 64.0, 'width' => 64.0, 'height' => 16.0], 'thumb' => ['x' => 16.0, 'y' => 64.0, 'width' => 32.0, 'height' => 16.0], 'thin' => false, 'thumb-color' => null, 'track-color' => null];
        $lines = new DisplayListRenderer()->render(new DisplayList([$vertical, $horizontal]), 8, 5);
        $plain = array_map(AnsiUtils::stripAnsiCodes(...), $lines);
        self::assertSame([' │', ' ┃', ' ┃', ' │', '──━━━━──'], array_map(rtrim(...), $plain));
        $cells = TerminalCellSnapshot::normalize(implode("\r\n", $lines), 8, 5, [229, 229, 229], [0, 0, 0]);
        self::assertSame('2', $cells[0][1][3]);
        self::assertSame('', $cells[1][1][3]);
        $vertical['thin'] = true;
        self::assertSame([' ┊', ' │', ' │', ' ┊'], array_map(static fn ($line) => rtrim(AnsiUtils::stripAnsiCodes($line)), new DisplayListRenderer()->render(new DisplayList([$vertical]), 2, 4)));
    }

    public function testNumericColorsAndForcedColorsAreDeviceAdapted()
    {
        $operation = $this->operation();
        $operation['thumb-color'] = [[1.0, 0.0, 0.0], 1.0];
        $operation['track-color'] = [[0.0, 1.0, 0.0], 1.0];
        $lines = new DisplayListRenderer()->render(new DisplayList([$operation]), 2, 4);
        $cells = TerminalCellSnapshot::normalize(implode("\r\n", $lines), 2, 4, [229, 229, 229], [0, 0, 0]);
        self::assertSame([0, 255, 0], $cells[0][1][1]);
        self::assertSame([255, 0, 0], $cells[1][1][1]);
        self::assertSame('', $cells[0][1][3]);
        $lines = new DisplayListRenderer(new TerminalTextMeasurer(), new MediaContext()->withForcedColors())->render(new DisplayList([$operation]), 2, 4);
        self::assertStringNotContainsString('38;2;', implode('', $lines));
        self::assertStringContainsString('┃', implode('', $lines));
    }

    public function testTranslationClippingAndOpacityApplyToBothRectangles()
    {
        $operation = $this->operation();
        $operation['track']['x'] = $operation['thumb']['x'] = 0.0;
        $list = new DisplayList([
            ['type' => 'push-transform', 'matrix' => [1.0, 0.0, 0.0, 1.0, 8.0, 16.0]],
            ['type' => 'push-clip', 'x' => 0.0, 'y' => 16.0, 'width' => 8.0, 'height' => 32.0],
            $operation,
            ['type' => 'pop-clip'],
            ['type' => 'pop-transform'],
        ]);
        $lines = array_map(static fn ($line) => rtrim(AnsiUtils::stripAnsiCodes($line)), new DisplayListRenderer()->render($list, 3, 6));
        self::assertSame(['', '', ' ┃', ' ┃', '', ''], $lines);
        $list = new DisplayList([['type' => 'push-opacity', 'opacity' => 0.0], $operation, ['type' => 'pop-opacity']]);
        self::assertStringNotContainsString('┃', implode('', new DisplayListRenderer()->render($list, 3, 6)));
    }

    public function testActualTerminalLayoutReservesAndPaintsBothAxesWithoutPostLayoutRewriting()
    {
        $session = new HtmlRenderer()->createSession('<div id="box" style="width:10;height:2;overflow:auto">one<br>two<br>three<br>four</div>', new RenderingContext(160, 128));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(20, 8);
        $plain = array_map(static fn ($line) => rtrim(AnsiUtils::stripAnsiCodes($line)), $widget->render($context));
        self::assertSame(['one      ┃', 'two      │'], \array_slice($plain, 0, 2));
        $box = $session->getDocument()->getElementById('box');
        self::assertSame(72.0, $session->render()->getScrollGeometry($box)->port->width);
        $session->scroll($box, 0, 32);
        $plain = array_map(static fn ($line) => rtrim(AnsiUtils::stripAnsiCodes($line)), $widget->render($context));
        self::assertSame(['three    │', 'four     ┃'], \array_slice($plain, 0, 2));

        $session = new HtmlRenderer()->createSession('<div style="width:5;height:2;overflow:auto;white-space:nowrap">abcdefghij</div>', new RenderingContext(160, 128));
        $plain = array_map(static fn ($line) => rtrim(AnsiUtils::stripAnsiCodes($line)), new DocumentWidget($session)->render($context));
        self::assertSame(['abcde', '━━━──'], \array_slice($plain, 0, 2));
        $session = new HtmlRenderer()->createSession('<div style="width:5;height:2;overflow:scroll;white-space:pre">abcdefghij'."\n".'klmnopqrst'."\n".'uvwxyz</div>', new RenderingContext(160, 128));
        $plain = array_map(static fn ($line) => rtrim(AnsiUtils::stripAnsiCodes($line)), new DocumentWidget($session)->render($context));
        self::assertSame(['abcd┃', '━━──'], \array_slice($plain, 0, 2));
    }

    public function testActualThinAndHiddenBarsKeepTheirDeviceMeasurementPolicy()
    {
        $html = '<div id="box" style="width:10;height:2;overflow:auto;scrollbar-width:thin">one<br>two<br>three<br>four</div>';
        $session = new HtmlRenderer()->createSession($html, new RenderingContext(160, 128));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(20, 8);
        $plain = array_map(static fn ($line) => rtrim(AnsiUtils::stripAnsiCodes($line)), $widget->render($context));
        self::assertSame(['one      │', 'two      ┊'], \array_slice($plain, 0, 2));
        $session->getDocument()->getElementById('box')->setAttribute('style', 'width:10;height:2;overflow:auto;scrollbar-width:none');
        $plain = array_map(static fn ($line) => rtrim(AnsiUtils::stripAnsiCodes($line)), $widget->render($context));
        self::assertSame(['one', 'two'], \array_slice($plain, 0, 2));
        self::assertNotContains('scrollbar', array_column($session->render()->getDisplayList()->getOperations(), 'type'));
    }

    private function operation(): array
    {
        return ['type' => 'scrollbar', 'axis' => 'y', 'track' => ['x' => 8.0, 'y' => 0.0, 'width' => 8.0, 'height' => 64.0], 'thumb' => ['x' => 8.0, 'y' => 16.0, 'width' => 8.0, 'height' => 32.0], 'thin' => false, 'thumb-color' => null, 'track-color' => null];
    }
}
