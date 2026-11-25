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
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Internal\TextMarkers;
use Symfony\Bridge\TuiHtml\Render\Renderer;
use Symfony\Bridge\TuiHtml\Render\TerminalRenderer;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\CellSize;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Focus\FocusPolicyInterface;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\RendererInterface;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\InputWidget;

class TerminalRendererTest extends TestCase
{
    public function testLogicalFontSizesStayIndependentOfPhysicalCellDensity()
    {
        foreach ([1, 2] as $density) {
            $metrics = new TerminalTextMeasurer(8 * $density, 16 * $density);
            $session = (new HtmlRenderer())->createSession('<p style="font-size:16px;margin:0">BASE</p><p style="font-size:32px;margin:0">DOUBLE</p>', new RenderingContext(480 * $density, 640 * $density));
            $widget = (new DocumentWidget($session, $metrics))->setMediaContext(new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, false, false, new CellSize(8 * $density, 16 * $density), false, MediaContext::TRUE_COLORS, true));
            $lines = $widget->render(new RenderContext(60, 40));
            $texts = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']));
            $this->assertSame(16.0, $texts[0]['font-size']);
            $this->assertSame(32.0 * $density, $texts[0]['width']);
            $this->assertSame(32.0, $texts[1]['font-size']);
            $this->assertSame(96.0 * $density, $texts[1]['width']);
            $this->assertStringContainsString('BASE', implode('', $lines));
        }
    }

    public function testExistingFontSizeFixtureReservesAllScaledRows()
    {
        $html = file_get_contents(__DIR__.'/../Fixtures/Compat/css-font-size.html');
        foreach ([1, 2] as $density) {
            $media = new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, false, false, new CellSize(8 * $density, 16 * $density), false, MediaContext::TRUE_COLORS, true);
            $view = (new HtmlBuilder())->build($html);
            $legacy = new Renderer();
            $legacy->setMediaContext($media);
            $legacy->getStyleSheet()->addProvider($view->getStyleSheet());
            $oldLines = $legacy->renderFrame($view->getRoot(), 60, 40)->toArray();
            $metrics = new TerminalTextMeasurer(8 * $density, 16 * $density);
            $session = (new HtmlRenderer())->createSession($html, new RenderingContext(480 * $density, 640 * $density));
            $widget = (new DocumentWidget($session, $metrics))->setMediaContext($media);
            $lines = $widget->render(new RenderContext(60, 40));
            $this->assertSame('Small is the size of the text', rtrim(AnsiUtils::stripAnsiCodes($oldLines[13])));
            $this->assertSame('Small is the size of the text', rtrim(AnsiUtils::stripAnsiCodes($lines[13])));
            preg_match_all('/\x1b_pi:s;([^\x07]*)\x07/', implode('', $lines), $matches);
            $payload = implode('', array_map(base64_decode(...), $matches[1]));
            $this->assertStringContainsString("\x1b]66;s=2;", $payload);
            $this->assertStringContainsString("\x1b]66;s=3;", $payload);
            $this->assertStringContainsString("\x1b]66;s=4;", $payload);
        }
    }

    public function testNativeRendererWithoutProtocolTransportKeepsOrdinarySizedVisibleText()
    {
        $session = (new HtmlRenderer())->createSession('<h1 style="font-size:2em;margin:0">Big</h1>', new RenderingContext(160, 64));
        $widget = (new DocumentWidget($session))->setMediaContext(new MediaContext());
        $widget->render(new RenderContext(20, 4));
        $ordinary = $session->render()->getDisplayList()->getOperations();
        $widget->setMediaContext(new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, false, false, null, false, MediaContext::TRUE_COLORS, true));
        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(null, $terminal);
        $tui->add($widget);
        $tui->start();
        try {
            $tui->processRender();
            $output = $terminal->getOutput();
            $this->assertStringContainsString('Big', $output);
            $this->assertStringNotContainsString(TextMarkers::SCALED_PREFIX, $output);
            $this->assertStringNotContainsString("\x1b]66;", $output);
            $texts = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']));
            $this->assertSame(24.0, $texts[0]['width']);
            $this->assertSame($ordinary, $session->render()->getDisplayList()->getOperations());
        } finally {
            $tui->stop();
        }
    }

    public function testKeepsTheDecoratedRenderersFocusPolicy()
    {
        $inner = $this->createMockForIntersectionOfInterfaces([RendererInterface::class, FocusPolicyInterface::class]);
        $first = new InputWidget();
        $second = new InputWidget();
        $inner->expects($this->once())->method('allowsFocus')->with($first)->willReturn(false);
        $inner->expects($this->once())->method('orderFocusCycle')->with([$first, $second])->willReturn([$second, $first]);
        $renderer = new TerminalRenderer($inner);
        $this->assertFalse($renderer->allowsFocus($first));
        $this->assertSame([$second, $first], $renderer->orderFocusCycle([$first, $second]));
    }

    public function testNativeTuiWritesScaledTextAfterItsReservedRowsAndKeepsNativeWidgets()
    {
        $session = (new HtmlRenderer())->createSession('<h1 style="font-size:2em;margin:0">ABCDEFGHIJ KLMNOPQRST</h1><p style="margin:0">After</p>', new RenderingContext(160, 128));
        $widget = (new DocumentWidget($session))->setMediaContext(new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, false, false, null, false, MediaContext::TRUE_COLORS, true));
        $terminal = new VirtualTerminal(20, 8);
        $tui = new Tui(null, $terminal);
        $native = $tui->getRenderer();
        $renderer = new TerminalRenderer($native);
        $tui->setRenderer($renderer);
        $this->assertSame($native, $renderer->getDecoratedRenderer());
        $tui->add($widget);
        $tui->start();
        try {
            $tui->processRender();
            $output = $terminal->getOutput();
            $this->assertStringNotContainsString(TextMarkers::SCALED_PREFIX, $output);
            $this->assertStringContainsString("\x1b]66;s=2;A\x07", $output);
            $this->assertStringContainsString("\x1b]66;s=2;K\x07", $output);
            $bounds = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']));
            $this->assertCount(3, $bounds);
            $this->assertSame(160.0, $bounds[0]['width']);
            $this->assertSame(32.0, $bounds[0]['height']);
            $this->assertSame(32.0, $bounds[1]['y'] - $bounds[0]['y']);
            $this->assertSame(64.0, $bounds[2]['y']);
            $terminal->clearOutput();
            $tui->requestRender();
            $tui->processRender();
            $this->assertStringNotContainsString("\x1b]66;", $terminal->getOutput(), 'An unchanged document does not resend scaled text.');
            $input = new InputWidget();
            $tui->add($input);
            $tui->setFocus($input);
            $tui->handleInput('Native');
            $this->assertSame('Native', $input->getValue());
        } finally {
            $tui->stop();
        }
    }
}
