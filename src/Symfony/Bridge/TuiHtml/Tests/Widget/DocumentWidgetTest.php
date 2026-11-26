<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Event\FormSubmitEvent;
use Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\InputWidget;

final class DocumentWidgetTest extends TestCase
{
    public function testRendersTheStandaloneEngineOutputInATuiWidget()
    {
        $session = (new HtmlRenderer())->createSession('<div style="line-height:16px">Hello 中文</div>', new RenderingContext(160, 32));
        $widget = new DocumentWidget($session);
        $lines = $widget->render(new RenderContext(20, 2));
        $this->assertSame('Hello 中文', rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
        $this->assertSame(80.0, $session->render()->getBounds($session->getDocument()->querySelector('div')->firstChild)[0]->width);
    }

    public function testMountsAlongsideNativeWidgetsWithTheExistingTuiRenderer()
    {
        $session = (new HtmlRenderer())->createSession('<p style="margin:0;line-height:16px">Hello 中文</p>', new RenderingContext(160, 32));
        $widget = new DocumentWidget($session);
        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(null, $terminal);
        $tui->add($widget);
        $tui->start();
        try {
            $tui->processRender();
            $screen = new ScreenBuffer(20, 4);
            $screen->write($terminal->getOutput());
            $this->assertStringContainsString('Hello 中文', $screen->getScreen());
        } finally {
            $tui->stop();
        }
    }

    public function testCachedPaintingTracksDocumentAndTerminalCapabilities()
    {
        $session = (new HtmlRenderer())->createSession('<p style="color:red;margin:0">Before</p>', new RenderingContext(160, 64));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(20, 4);
        $first = $widget->render($context);
        $this->assertSame($first, $widget->render(new RenderContext(20, 4)));
        $list = $session->render()->getDisplayList();
        $previous = getenv('NO_COLOR');
        try {
            putenv('NO_COLOR=1');
            $monochrome = $widget->render($context);
            $this->assertNotSame($list, $session->render()->getDisplayList());
            $this->assertSame('active', $session->getContext()->mediaFeatures['forced-colors']);
            $this->assertNotSame($first, $monochrome);
            $this->assertSame('Before', rtrim(AnsiUtils::stripAnsiCodes($monochrome[0])));
        } finally {
            false === $previous ? putenv('NO_COLOR') : putenv('NO_COLOR='.$previous);
        }
        $session->getDocument()->querySelector('p')->textContent = 'After';
        $this->assertSame('After', rtrim(AnsiUtils::stripAnsiCodes($widget->render($context)[0])));
    }

    public function testPaintingCacheTracksTheNativeBidiPolicy()
    {
        if (!class_exists(\IntlChar::class)) {
            $this->markTestSkipped('The intl extension is required for bidirectional rendering.');
        }
        $widget = new DocumentWidget((new HtmlRenderer())->createSession('<p style="margin:0">אבג</p>', new RenderingContext(160, 64)));
        try {
            \Symfony\Component\Tui\Ansi\Bidi::setEnabled(true);
            $visual = $widget->render(new RenderContext(20, 4));
            \Symfony\Component\Tui\Ansi\Bidi::setEnabled(false);
            $logical = $widget->render(new RenderContext(20, 4));
            $this->assertNotSame($visual, $logical);
            $this->assertSame('אבג', rtrim(AnsiUtils::stripAnsiCodes($logical[0])));
        } finally {
            \Symfony\Component\Tui\Ansi\Bidi::setEnabled(null);
        }
    }

    public function testDropdownChromeUsesSemanticPseudoElementsAndAuthorOverrides()
    {
        $session = (new HtmlRenderer())->createSession('<select><option>Banana</option></select>', new RenderingContext(320, 64));
        $widget = new DocumentWidget($session);
        $this->assertSame('[ Banana ▾ ]', rtrim(AnsiUtils::stripAnsiCodes($widget->render(new RenderContext(40, 4))[0])));
        $session = (new HtmlRenderer())->createSession('<style>select::before{content:"("}select::picker-icon{content:"⌄";color:cyan}select::after{content:")"}</style><select><option>Banana</option></select>', new RenderingContext(320, 64));
        $lines = (new DocumentWidget($session))->render(new RenderContext(40, 4));
        $this->assertSame('(Banana ⌄)', rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
        $session = (new HtmlRenderer())->createSession('<select size="2"><option>Banana</option><option>Apple</option></select>', new RenderingContext(320, 64));
        $lines = (new DocumentWidget($session))->render(new RenderContext(40, 4));
        $this->assertStringNotContainsString('▾', implode('', $lines));
        $this->assertStringNotContainsString('[', AnsiUtils::stripAnsiCodes(implode('', $lines)));
    }

    public function testTerminalTableSpacingIsAnOverridableUserAgentPolicy()
    {
        foreach (['' => 8.0, '<style>table{border-spacing:0}</style>' => 0.0] as $style => $gap) {
            $session = (new HtmlRenderer())->createSession($style.'<table><tr><td>A</td><td>B</td></tr></table>', new RenderingContext(320, 64));
            $widget = new DocumentWidget($session);
            $widget->render(new RenderContext(40, 4));
            $cells = $session->getDocument()->querySelectorAll('td');
            $result = $session->render();
            $first = $result->getBounds($cells->item(0))[0];
            $second = $result->getBounds($cells->item(1))[0];
            $this->assertSame($gap, $second->x - $first->x - $first->width);
        }
    }

    public function testResolvedColorFunctionsKeepCssOperandsInsteadOfTerminalPaletteValues()
    {
        foreach (['dark' => [255, 255, 0], 'light' => [0, 0, 255]] as $scheme => $rgb) {
            $session = (new HtmlRenderer())->createSession('<style>:root{color-scheme:light dark}p{margin:0}#direct{color:yellow}#function{color:light-dark(blue,yellow)}</style><p id="direct">Direct</p><p id="function">Function</p>', new RenderingContext(320, 64, null, null, null, null, ['prefers-color-scheme' => $scheme]));
            $lines = (new DocumentWidget($session))->render(new RenderContext(40, 4));
            $cells = TerminalCellSnapshot::normalize(implode("\r\n", $lines), 40, 4);
            $this->assertSame([205, 205, 0], $cells[0][0][1]);
            $this->assertSame($rgb, $cells[1][0][1]);
        }
    }

    public function testEnvironmentMediaChangesRecomputeStylesAndPreserveCallerOverrides()
    {
        $previous = getenv('COLORFGBG');
        try {
            foreach ([[], ['prefers-color-scheme' => 'dark']] as $features) {
                putenv('COLORFGBG=15;0');
                $session = (new HtmlRenderer())->createSession('<p style="color:light-dark(blue,yellow)">Text</p>', new RenderingContext(320, 64, null, null, null, null, $features));
                $widget = new DocumentWidget($session);
                $context = new RenderContext(40, 4);
                $widget->render($context);
                $paragraph = $session->getDocument()->querySelector('p');
                $this->assertSame('#ffff00', $session->render()->getComputedStyle($paragraph)['color']);
                $session->setContext($session->getContext()->withViewport(320, 64));
                $widget->render($context);
                putenv('COLORFGBG=0;15');
                $widget->render($context);
                $this->assertSame($features ? '#ffff00' : '#0000ff', $session->render()->getComputedStyle($paragraph)['color']);
                $this->assertSame($features ? 'dark' : 'light', $session->getContext()->mediaFeatures['prefers-color-scheme']);
                $session->setContext(new RenderingContext(320, 64, null, null, null, null, ['prefers-color-scheme' => 'light']));
                putenv('COLORFGBG=15;0');
                $widget->render($context);
                $this->assertSame('#0000ff', $session->render()->getComputedStyle($paragraph)['color']);
            }
        } finally {
            false === $previous ? putenv('COLORFGBG') : putenv('COLORFGBG='.$previous);
        }
    }

    public function testResolvedDocumentBorderRadiiReachTerminalCornerGlyphs()
    {
        $session = (new HtmlRenderer())->createSession('<div style="width:4ch;height:3lh;border:1px solid;border-radius:50%"></div>', new RenderingContext(160, 64));
        $lines = (new DocumentWidget($session))->render(new RenderContext(20, 4));
        $this->assertSame(['╭──╮', '│  │', '╰──╯'], array_map(static fn ($line) => rtrim(AnsiUtils::stripAnsiCodes($line)), \array_slice($lines, 0, 3)));
    }

    public function testExplicitMediaContextControlsCssAndBackendCapabilitiesAndCanReset()
    {
        $previous = getenv('COLORFGBG');
        try {
            putenv('COLORFGBG=15;0');
            $session = (new HtmlRenderer())->createSession('<p style="color:light-dark(blue,yellow);background:#123456;margin:0">Text</p>', new RenderingContext(320, 64));
            $widget = new DocumentWidget($session);
            $paragraph = $session->getDocument()->querySelector('p');
            $context = new RenderContext(40, 4);
            $this->assertSame($widget, $widget->setMediaContext(new MediaContext(80, 24, ColorScheme::Light, Density::Regular, false, false, false, null, false, 16)));
            $lines = $widget->render($context);
            $this->assertSame('#0000ff', $session->render()->getComputedStyle($paragraph)['color']);
            $this->assertStringNotContainsString('38;2;', implode('', $lines));
            $this->assertStringNotContainsString('48;2;', implode('', $lines));
            $this->assertSame(320.0, $session->getContext()->width);
            $displayList = $session->render()->getDisplayList();
            $widget->setMediaContext(new MediaContext(80, 24, ColorScheme::Light, Density::Regular, false, false, false, null, false, 16));
            $widget->render($context);
            $this->assertSame($displayList, $session->render()->getDisplayList());
            $widget->setMediaContext(new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, true));
            $lines = $widget->render($context);
            $this->assertSame('active', $session->getContext()->mediaFeatures['forced-colors']);
            $this->assertStringNotContainsString('48;', implode('', $lines));
            $widget->setMediaContext(null);
            $widget->render($context);
            $this->assertSame('#ffff00', $session->render()->getComputedStyle($paragraph)['color']);
            $this->assertSame('none', $session->getContext()->mediaFeatures['forced-colors']);
            $session->setContext(new RenderingContext(320, 64, null, null, null, null, ['prefers-color-scheme' => 'dark']));
            $widget->setMediaContext(new MediaContext(80, 24, ColorScheme::Light));
            $widget->render($context);
            $this->assertSame('#ffff00', $session->render()->getComputedStyle($paragraph)['color']);
        } finally {
            false === $previous ? putenv('COLORFGBG') : putenv('COLORFGBG='.$previous);
        }
    }

    public function testExplicitReducedMotionControlsOnlyThisDocumentsNativeTicker()
    {
        $session = (new HtmlRenderer())->createSession('<style>@keyframes fade{from{opacity:0}to{opacity:1}}div{animation:fade 1s infinite}</style><div>Animated</div>', new RenderingContext(160, 64));
        $widget = new DocumentWidget($session);
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        $tui->add($widget);
        $tick = new \ReflectionProperty($widget, 'scheduledTickId');
        try {
            $widget->setMediaContext(new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, false, true));
            $widget->render(new RenderContext(20, 4));
            $this->assertNull($tick->getValue($widget));
            $widget->setMediaContext(new MediaContext());
            $widget->render(new RenderContext(20, 4));
            $this->assertNotNull($tick->getValue($widget));
            $widget->setMediaContext(new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, false, true));
            $widget->render(new RenderContext(20, 4));
            $this->assertNull($tick->getValue($widget));
        } finally {
            $tui->remove($widget);
        }
    }

    public function testTextSizingUpdatesOwnedDeviceLineHeightsAndPreservesExplicitPolicies()
    {
        $session = (new HtmlRenderer())->createSession('<div id="large" style="font-size:32px;line-height:16px">Big</div><div>After</div>', new RenderingContext(160, 128));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(20, 8);
        $large = $session->getDocument()->getElementById('large');
        foreach ([false => 16.0, true => 32.0] as $enabled => $height) {
            $widget->setMediaContext(new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, false, false, null, false, MediaContext::TRUE_COLORS, (bool) $enabled));
            $widget->render($context);
            $this->assertSame($height, $session->render()->getBounds($large)[0]->height);
        }
        $widget->setMediaContext(new MediaContext());
        $widget->render($context);
        $this->assertSame(16.0, $session->render()->getBounds($large)[0]->height);
        $policy = new TerminalLayoutMetrics();
        $session->setContext(new RenderingContext(160, 128, null, null, $policy));
        $widget->setMediaContext(new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, false, false, null, false, MediaContext::TRUE_COLORS, true));
        $widget->render($context);
        $this->assertSame($policy, $session->getContext()->layoutMetrics);
        $this->assertSame(16.0, $session->render()->getBounds($large)[0]->height);
    }

    public function testTerminalRubyAnnotationSizeIsAnOverridableUserAgentPolicy()
    {
        foreach (['' => '16px', '<style>rt{font-size:8px}</style>' => '8px'] as $style => $size) {
            $session = (new HtmlRenderer())->createSession($style.'<ruby>漢<rt>kan</rt></ruby>', new RenderingContext(320, 64));
            (new DocumentWidget($session))->render(new RenderContext(40, 4));
            $this->assertSame($size, $session->render()->getComputedStyle($session->getDocument()->querySelector('rt'))['font-size']);
        }
    }

    public function testAuthoredRootLineHeightOverridesTheFixedCellUserAgentDefault()
    {
        foreach (['' => 16.0, '<style>html{line-height:48px}</style>' => 48.0] as $style => $height) {
            $session = (new HtmlRenderer())->createSession($style.'<div>Text</div>', new RenderingContext(320, 128));
            (new DocumentWidget($session))->render(new RenderContext(40, 8));
            $this->assertSame($height, $session->render()->getBounds($session->getDocument()->querySelector('div'))[0]->height);
        }
    }

    public function testProgrammaticChangesInvalidateTheMountedNativeWidgetCache()
    {
        $session = (new HtmlRenderer())->createSession('<input value="Initial"><p>Before</p>', new RenderingContext(320, 64));
        $widget = new DocumentWidget($session);
        $terminal = new VirtualTerminal(40, 4);
        $tui = new Tui(null, $terminal);
        $tui->add($widget);
        $tui->start();
        try {
            $tui->processRender();
            $session->setValue($session->getDocument()->querySelector('input'), 'Updated');
            $tui->processRender();
            $screen = new ScreenBuffer(40, 4);
            $screen->write($terminal->getOutput());
            $this->assertStringContainsString('Updated', $screen->getScreen());
            $session->getDocument()->querySelector('p')->textContent = 'After';
            $session->invalidate();
            $tui->processRender();
            $screen->write($terminal->getOutput());
            $this->assertStringContainsString('After', $screen->getScreen());
            $tui->remove($widget);
            $this->assertNull((new \ReflectionProperty($widget, 'unsubscribeInvalidation'))->getValue($widget));
        } finally {
            $tui->stop();
        }
    }

    public function testViewportResizingPreservesTheApplicationResourceLoader()
    {
        $loader = new ResourceLoader(static fn (string $source): string => '<svg xmlns="http://www.w3.org/2000/svg" width="8" height="16"><rect width="8" height="16"/></svg>');
        $session = (new HtmlRenderer())->createSession('<img src="icon.svg">', new RenderingContext(160, 32, null, $loader));
        $widget = new DocumentWidget($session);
        $widget->render(new RenderContext(40, 4));
        $this->assertSame($loader, $session->getContext()->resourceLoader);
        $this->assertContains('svg', array_column($session->render()->getDisplayList()->getOperations(), 'type'));
    }

    public function testRendersControlStateFromTheDocumentPipeline()
    {
        $session = (new HtmlRenderer())->createSession('<input value="Hello"><input type="checkbox" checked><input type="password" value="secret">', new RenderingContext(320, 160));
        $widget = new DocumentWidget($session);
        $screen = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), $widget->render(new RenderContext(40, 10))));
        $this->assertStringContainsString('Hello', $screen);
        $this->assertStringContainsString('[x]', $screen);
        $this->assertStringContainsString('••••••', $screen);
        $this->assertStringNotContainsString('secret', $screen);
        $widget->setFocused(true);
        $widget->handleInput('!');
        $screen = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), $widget->render(new RenderContext(40, 10))));
        $this->assertStringContainsString('Hello!', $screen);
    }

    public function testTerminalStylesheetPreservesBorderBoxSizingAndAuthorOverrides()
    {
        $session = (new HtmlRenderer())->createSession('<style>p{margin:0}#box{width:24ch;height:7;border:1px solid}</style><div id="box"><p id="text">Text</p></div>', new RenderingContext(480, 320));
        (new DocumentWidget($session))->render(new RenderContext(60, 20));
        $result = $session->render();
        $box = $result->getBounds($session->getDocument()->getElementById('box'))[0];
        $this->assertSame(192.0, $box->width);
        $this->assertSame(112.0, $box->height);
        $this->assertSame('0', $result->getComputedStyle($session->getDocument()->getElementById('text'))['margin-top']);
        $this->assertStringContainsString('box-sizing: border-box', $session->getContext()->userAgentStylesheet);
    }

    public function testKeepsAnExplicitUserAgentStylesheetAcrossResizing()
    {
        $stylesheet = '*{box-sizing:content-box}p{margin:0;color:red}';
        $metrics = new TerminalTextMeasurer();
        $layout = new TerminalLayoutMetrics($metrics);
        $font = new FontStyle('custom', 16, 16);
        $loader = new ResourceLoader();
        $session = (new HtmlRenderer())->createSession('<p id="text">Text</p>', new RenderingContext(160, 64, $metrics, $loader, $layout, $font, ['grid' => 0], $stylesheet));
        $widget = new DocumentWidget($session, $metrics);
        $widget->render(new RenderContext(20, 4));
        $widget->render(new RenderContext(30, 6));
        $this->assertSame(240.0, $session->getContext()->width);
        $this->assertSame(96.0, $session->getContext()->height);
        $this->assertSame($metrics, $session->getContext()->textMeasurer);
        $this->assertSame($loader, $session->getContext()->resourceLoader);
        $this->assertSame(0, $session->getContext()->mediaFeatures['grid']);
        $this->assertSame($stylesheet, $session->getContext()->userAgentStylesheet);
        $this->assertSame($layout, $session->getContext()->layoutMetrics);
        $this->assertSame($font, $session->getContext()->defaultFont);
        $this->assertSame('content-box', $session->render()->getComputedStyle($session->getDocument()->getElementById('text'))['box-sizing']);
    }

    public function testDenseMetricsAreNotAppliedAgainToInheritedFontsOrTracks()
    {
        $metrics = new TerminalTextMeasurer(16, 32);
        $font = new FontStyle('monospace', 32, 32);
        $context = new RenderingContext(640, 320, $metrics, null, new TerminalLayoutMetrics($metrics), $font);
        $html = '<style>html,body,p{margin:0}#flex{display:flex;flex-direction:column;width:4ch}#grid{display:grid;grid-template-columns:1fr 1fr;width:4ch}</style><main><section><p id="text">AB</p></section></main><div id="flex"><span>A</span><span>B</span></div><div id="grid"><span id="left">L</span><span id="right">R</span></div>';
        $session = (new HtmlRenderer())->createSession($html, $context);
        $widget = new DocumentWidget($session, $metrics);
        $widget->render(new RenderContext(40, 10));
        $result = $session->render();
        $document = $session->getDocument();
        $this->assertSame($font, $session->getContext()->defaultFont);
        $this->assertSame('32px', $result->getComputedStyle($document->getElementById('text'))['font-size']);
        $this->assertSame(32.0, $result->getBounds($document->getElementById('text')->firstChild)[0]->width);
        $this->assertSame(64.0, $result->getBounds($document->getElementById('flex'))[0]->height);
        $this->assertSame(64.0, $result->getBounds($document->getElementById('grid'))[0]->width);
        $this->assertSame(32.0, $result->getBounds($document->getElementById('left'))[0]->width);
        $this->assertSame(32.0, $result->getBounds($document->getElementById('right'))[0]->x);
    }

    public function testProvidesTerminalMediaFeaturesAndKeepsApplicationOverrides()
    {
        foreach ([[], ['grid' => 0]] as $features) {
            $session = (new HtmlRenderer())->createSession('<style>#text{color:black}@media (grid:1){#text{color:#123456}}</style><p id="text">Text</p>', new RenderingContext(160, 32, null, null, null, null, $features));
            (new DocumentWidget($session))->render(new RenderContext(20, 2));
            $color = $session->render()->getComputedStyle($session->getDocument()->getElementById('text'))['color'];
            $this->assertSame($features ? 'black' : '#123456', $color);
        }
    }

    public function testUsesTuiTicksOnlyWhileDocumentAnimationsNeedFrames()
    {
        $session = (new HtmlRenderer())->createSession('<style>@keyframes fade{from{opacity:0}to{opacity:1}}div{animation:fade 1s linear forwards}</style><div>Animated</div>', new RenderingContext(160, 64));
        $widget = new DocumentWidget($session);
        $now = 10.0;
        (new \ReflectionProperty($widget, 'clock'))->setValue($widget, static function () use (&$now): float { return $now; });
        $tick = new \ReflectionProperty($widget, 'scheduledTickId');
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        $tui->add($widget);
        $widget->render(new RenderContext(20, 4));
        $this->assertNotNull($tick->getValue($widget));
        $now = 10.5;
        (new \ReflectionMethod($widget, 'onScheduledTick'))->invoke($widget);
        $this->assertEqualsWithDelta(0.5, (float) $session->render()->getComputedStyle($session->getDocument()->querySelector('div'))['opacity'], 0.000001);
        $now = 12.0;
        (new \ReflectionMethod($widget, 'onScheduledTick'))->invoke($widget);
        $this->assertNull($tick->getValue($widget));
        $this->assertSame('1', $session->render()->getComputedStyle($session->getDocument()->querySelector('div'))['opacity']);
        $session->getDocument()->querySelector('style')->textContent = '@keyframes fade{from{opacity:0}to{opacity:1}}div{animation:fade 1s linear infinite}';
        $widget->render(new RenderContext(20, 4));
        $this->assertNotNull($tick->getValue($widget));
        $tui->remove($widget);
        $this->assertNull($tick->getValue($widget));
        $static = new DocumentWidget((new HtmlRenderer())->createSession('<p>Static</p>', new RenderingContext(160, 64)));
        $tui->add($static);
        $static->render(new RenderContext(20, 4));
        $this->assertNull($tick->getValue($static));
    }

    public function testDocumentCaretReachesTheNativeScreenWriter()
    {
        $session = (new HtmlRenderer())->createSession('<input value="A中B" style="padding:0;border:0;width:80px;height:16px">', new RenderingContext(160, 64));
        $widget = new DocumentWidget($session);
        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(null, $terminal);
        $tui->add($widget);
        $tui->setFocus($widget);
        $tui->start();
        try {
            $tui->processRender();
            $writer = (new \ReflectionProperty($tui, 'screenWriter'))->getValue($tui);
            $cursor = new \ReflectionProperty($writer, 'previousCursorPos');
            $this->assertSame(6, $cursor->getValue($writer)['col']);
            $this->assertStringNotContainsString(AnsiUtils::cursorMarker(), $terminal->getOutput());
            $tui->handleInput("\x1b[D");
            $tui->processRender();
            $this->assertSame(5, $cursor->getValue($writer)['col']);
        } finally {
            $tui->stop();
        }
    }

    public function testRtlCaretReachesTheNativeScreenWriterAtVisualBoundaries()
    {
        if (!\Symfony\Component\Tui\Ansi\Bidi::isAvailable()) {
            $this->markTestSkipped('The Intl extension is needed for terminal bidi.');
        }
        $session = (new HtmlRenderer())->createSession('<input value="אבג" style="padding:0;border:0">', new RenderingContext(160, 64));
        $element = $session->getDocument()->querySelector('input');
        $widget = new DocumentWidget($session);
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        $tui->add($widget);
        $tui->setFocus($widget);
        $tui->start();
        try {
            foreach ([0 => 4, 1 => 3, 2 => 2, 3 => 5] as $logical => $visual) {
                $session->setTextSelection($element, $logical, $logical);
                $widget->invalidate();
                $tui->requestRender();
                $tui->processRender();
                $writer = (new \ReflectionProperty($tui, 'screenWriter'))->getValue($tui);
                $cursor = (new \ReflectionProperty($writer, 'previousCursorPos'))->getValue($writer);
                $this->assertSame($visual, $cursor['col']);
            }
        } finally {
            $tui->stop();
        }
    }

    public function testPointerPositionsTheCaretBeforeTyping()
    {
        $session = (new HtmlRenderer())->createSession('<input value="ABCD" style="padding:0;border:0;width:80px;height:16px">', new RenderingContext(160, 64));
        $widget = new DocumentWidget($session);
        $widget->setFocused(true);
        $widget->render(new RenderContext(20, 4));
        $widget->handlePointer(0, 3, true);
        $widget->handleInput('X');
        $this->assertSame('AXBCD', $session->getValue($session->getDocument()->querySelector('input')));
    }

    public function testColorFieldSwatchTracksEditableStateWithoutSourceMutation()
    {
        $session = (new HtmlRenderer())->createSession('<input type="color">', new RenderingContext(320, 64));
        $element = $session->getDocument()->querySelector('input');
        $before = $session->getDocument()->saveHtml();
        $widget = new DocumentWidget($session);
        $lines = $widget->render(new RenderContext(40, 4));
        $this->assertSame('██ #000000', rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
        $context = $session->getContext();
        $session->focus($element);
        $session->input('blue');
        $lines = $widget->render(new RenderContext(40, 4));
        $this->assertSame('░░ blue', rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
        $screen = new ScreenBuffer(40, 4);
        $screen->write(implode("\r\n", $lines));
        $this->assertStringContainsString('2;', $screen->getCells()[0][0]['style']);
        $session->input('#AABBCC');
        $lines = $widget->render(new RenderContext(40, 4));
        $screen = new ScreenBuffer(40, 4);
        $screen->write(implode("\r\n", $lines));
        $this->assertSame('██ #AABBCC', rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
        $this->assertStringContainsString('38;2;170;187;204', $screen->getCells()[0][0]['style']);
        $this->assertSame($context, $session->getContext());
        $this->assertSame($before, $session->getDocument()->saveHtml());
    }

    public function testFileFallbackLabelsDoNotReadFilesOrRewriteInitialValues()
    {
        foreach (['' => 'Path to a file', ' multiple' => 'Paths to files'] as $attribute => $label) {
            $session = (new HtmlRenderer())->createSession('<input type="file" value="ignored"'.$attribute.'>', new RenderingContext(320, 64));
            $element = $session->getDocument()->querySelector('input');
            $before = $session->getDocument()->saveHtml();
            $widget = new DocumentWidget($session);
            $lines = $widget->render(new RenderContext(40, 4));
            $this->assertSame('> '.$label, rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
            $this->assertSame('', $session->getValue($element));
            $session->focus($element);
            $session->input('report.txt');
            $screen = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), $widget->render(new RenderContext(40, 4))));
            $this->assertStringContainsString('> report.txt', $screen);
            $this->assertStringNotContainsString($label, $screen);
            $this->assertSame($before, $session->getDocument()->saveHtml());
        }
    }

    public function testExplicitFilePlaceholderAndDatalistSuggestionPreserveDocumentValues()
    {
        $session = (new HtmlRenderer())->createSession('<input type="file" placeholder="Pick"><input id="city" list="cities" value="Pa"><datalist id="cities"><option value="Paris"></datalist>', new RenderingContext(480, 128));
        $before = $session->getDocument()->saveHtml();
        $city = $session->getDocument()->getElementById('city');
        $session->focus($city);
        $widget = new DocumentWidget($session);
        $screen = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), $widget->render(new RenderContext(60, 8))));
        $this->assertStringContainsString('> Pick', $screen);
        $this->assertStringContainsString('> Paris', $screen);
        $this->assertSame('Pa', $session->getValue($city));
        $this->assertSame($before, $session->getDocument()->saveHtml());
    }

    public function testAuthorContentOverridesFileAndColorPresentationDefaults()
    {
        $session = (new HtmlRenderer())->createSession('<style>input::before{content:"C";color:red;opacity:1}input::after{content:"Own"}</style><input type="color"><input type="file">', new RenderingContext(480, 128));
        $screen = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), (new DocumentWidget($session))->render(new RenderContext(60, 8))));
        $this->assertStringContainsString('C#000000', $screen);
        $this->assertStringContainsString('Own', $screen);
        $this->assertStringNotContainsString('██', $screen);
        $this->assertStringNotContainsString('Path to a file', $screen);
    }

    public function testIndeterminateProgressUsesEngineTimeAndTheExistingTuiScheduler()
    {
        $session = (new HtmlRenderer())->createSession('<progress></progress>', new RenderingContext(240, 64));
        $widget = new DocumentWidget($session);
        $now = 10.0;
        (new \ReflectionProperty($widget, 'clock'))->setValue($widget, static function () use (&$now): float { return $now; });
        $tick = new \ReflectionProperty($widget, 'scheduledTickId');
        $tui = new Tui(null, new VirtualTerminal(30, 4));
        $tui->add($widget);
        try {
            $widget->render(new RenderContext(30, 4));
            $this->assertTrue($session->isAnimating());
            $this->assertNotNull($tick->getValue($widget));
            $context = $session->getContext();
            $before = $session->getDocument()->saveHtml();
            $rectangles = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $operation): bool => 'rectangle' === $operation['type']));
            $first = $rectangles[1];
            $this->assertGreaterThan(0, $first['width']);
            $now = 10.75;
            (new \ReflectionMethod($widget, 'onScheduledTick'))->invoke($widget);
            $rectangles = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $operation): bool => 'rectangle' === $operation['type']));
            $this->assertGreaterThan($first['x'], $rectangles[1]['x']);
            $this->assertSame($first['width'], $rectangles[1]['width']);
            $this->assertSame($before, $session->getDocument()->saveHtml());
            $this->assertSame($context, $session->getContext());
            $session->getDocument()->querySelector('progress')->setAttribute('value', '.75');
            $screen = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), $widget->render(new RenderContext(30, 4))));
            $this->assertFalse($session->isAnimating());
            $this->assertNull($tick->getValue($widget));
            $this->assertStringContainsString('75%', $screen);
        } finally {
            $tui->remove($widget);
        }
    }

    public function testReducedMotionKeepsAStaticIndeterminateSegmentWithoutScheduling()
    {
        foreach ([['prefers-reduced-motion' => 'reduce'], []] as $features) {
            $html = ($features ? '' : '<style>progress{animation:none}</style>').'<progress></progress>';
            $session = (new HtmlRenderer())->createSession($html, new RenderingContext(240, 64, null, null, null, null, $features));
            $widget = new DocumentWidget($session);
            $tui = new Tui(null, new VirtualTerminal(30, 4));
            $tui->add($widget);
            try {
                $widget->render(new RenderContext(30, 4));
                $first = $session->render()->getDisplayList()->getOperations();
                $this->assertFalse($session->isAnimating());
                $this->assertNull((new \ReflectionProperty($widget, 'scheduledTickId'))->getValue($widget));
                $rectangles = array_values(array_filter($first, static fn (array $operation): bool => 'rectangle' === $operation['type']));
                $this->assertGreaterThan(0, $rectangles[1]['width']);
                $session->advance(1.0);
                $this->assertSame($first, $session->render()->getDisplayList()->getOperations());
            } finally {
                $tui->remove($widget);
            }
        }
    }

    public function testNestedScriptsReuseTerminalGlyphsWithoutMovingToAnotherRow()
    {
        $session = (new HtmlRenderer())->createSession('<p style="margin:0">mc<sup><b>2</b></sup> H<sub>2</sub>O x<sup>n</sup> <sup>q2</sup></p>', new RenderingContext(320, 64));
        $lines = (new DocumentWidget($session))->render(new RenderContext(40, 4));
        $this->assertSame('mc² H₂O xⁿ q2', rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
        $this->assertSame('', rtrim(AnsiUtils::stripAnsiCodes($lines[1])));
    }

    public function testNumericLabelsReuseModelsAndRefreshOnlyWhenPresentationChanges()
    {
        $html = '<progress value="3" max="4"></progress><progress value="2.7" max="5.4"></progress><meter value=".2"></meter><input id="range" type="range" min="0" max="1" step=".1" value=".3">';
        $session = (new HtmlRenderer())->createSession($html, new RenderingContext(480, 320));
        $widget = new DocumentWidget($session);
        $screen = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), $widget->render(new RenderContext(60, 20))));
        foreach (['75%', '60%', '20%', '0.3'] as $label) {
            $this->assertStringContainsString($label, $screen);
        }
        $context = $session->getContext();
        $result = $session->render();
        $before = $session->getDocument()->saveHtml();
        $widget->render(new RenderContext(60, 20));
        $this->assertSame($context, $session->getContext());
        $this->assertSame($result, $session->render());
        $session->focus($session->getDocument()->getElementById('range'));
        $session->input('0.7');
        $screen = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), $widget->render(new RenderContext(60, 20))));
        $this->assertStringContainsString('0.7', $screen);
        $this->assertSame($context, $session->getContext());
        $this->assertSame($before, $session->getDocument()->saveHtml());
    }

    public function testNumericPoliciesAreClearedForRemovedOrRetypedControls()
    {
        $session = (new HtmlRenderer())->createSession('<input id="range" type="range" value=".3" min="0" max="1" step=".1"><progress id="progress" value="3" max="4"></progress>', new RenderingContext(480, 128));
        $widget = new DocumentWidget($session);
        $widget->render(new RenderContext(60, 8));
        $context = $session->getContext();
        $session->getDocument()->getElementById('range')->setAttribute('type', 'text');
        $session->getDocument()->getElementById('progress')->remove();
        $screen = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), $widget->render(new RenderContext(60, 8))));
        $this->assertStringContainsString('> .3', $screen);
        $this->assertStringNotContainsString('0.3', $screen);
        $this->assertStringNotContainsString('75%', $screen);
        $this->assertSame($context, $session->getContext());
    }

    public function testExplicitUserAgentReplacementRemovesManagedNumericPolicies()
    {
        $session = (new HtmlRenderer())->createSession('<progress value="3" max="4"></progress>', new RenderingContext(240, 64));
        $widget = new DocumentWidget($session);
        $widget->render(new RenderContext(30, 4));
        $stylesheet = 'progress::after{content:"Custom";position:absolute;right:0;top:0}';
        $session->setContext(new RenderingContext(240, 64, null, null, null, null, [], $stylesheet));
        $screen = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), $widget->render(new RenderContext(30, 4))));
        $this->assertStringContainsString('Custom', $screen);
        $this->assertStringNotContainsString('75%', $screen);
        $this->assertSame($stylesheet, $session->getContext()->userAgentStylesheet);
    }

    public function testNumericLabelPolicyPreservesAuthorPseudoContentAndExplicitUserAgents()
    {
        $session = (new HtmlRenderer())->createSession('<style>progress::after{content:"Author"}</style><progress value="3" max="4"></progress>', new RenderingContext(240, 64));
        $screen = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), (new DocumentWidget($session))->render(new RenderContext(30, 4))));
        $this->assertStringContainsString('Author', $screen);
        $this->assertStringNotContainsString('75%', $screen);
        $stylesheet = 'progress::after{content:"Custom";position:absolute;right:0;top:0}';
        $session = (new HtmlRenderer())->createSession('<progress value="3" max="4"></progress>', new RenderingContext(240, 64, null, null, null, null, [], $stylesheet));
        (new DocumentWidget($session))->render(new RenderContext(30, 4));
        $this->assertSame($stylesheet, $session->getContext()->userAgentStylesheet);
    }

    public function testDefaultPlaceholderKeepsTheRetainedTerminalDimmingAppearance()
    {
        $html = '<style>body{color:default}</style><input placeholder="Hint">';
        $view = (new \Symfony\Bridge\TuiHtml\Html\HtmlBuilder())->build($html);
        $renderer = new \Symfony\Bridge\TuiHtml\Render\Renderer();
        $renderer->getStyleSheet()->addProvider($view->getStyleSheet());
        $expected = implode("\r\n", $renderer->renderFrame($view->getRoot(), 30, 4)->toArray());
        $session = (new HtmlRenderer())->createSession($html, new RenderingContext(240, 64));
        $actual = implode("\r\n", (new DocumentWidget($session))->render(new RenderContext(30, 4)));
        $this->assertSame(
            TerminalCellSnapshot::normalize($expected, 30, 4, [229, 229, 229]),
            TerminalCellSnapshot::normalize($actual, 30, 4, [229, 229, 229]),
        );
    }

    public function testTerminalColorAliasesRemainOverridableAndSurviveResizing()
    {
        $html = '<p id="ansi" style="color:bright-red">ANSI</p><p id="palette" style="color:208">Palette</p><p id="shade" style="color:emerald-500">Shade</p><p id="mix" style="color:color-mix(in srgb, bright-red 50%,208)">Mix</p><div style="width:10ch;height:1lh;background:linear-gradient(90deg,bright-red,emerald-500)">Gradient</div><p id="rgb" style="color:rgb(208 0 0)">RGB</p>';
        $session = (new HtmlRenderer())->createSession($html, new RenderingContext(480, 320, null, null, null, null, [], '', ['bright-red' => '#123456']));
        $widget = new DocumentWidget($session);
        $widget->render(new RenderContext(60, 20));
        $widget->render(new RenderContext(80, 24));
        $result = $session->render();
        $document = $session->getDocument();
        $this->assertSame('#123456', $result->getComputedStyle($document->getElementById('ansi'))['color']);
        $this->assertSame('#ff8700', $result->getComputedStyle($document->getElementById('palette'))['color']);
        $this->assertSame('#10b981', $result->getComputedStyle($document->getElementById('shade'))['color']);
        $this->assertSame('#d00000', $result->getComputedStyle($document->getElementById('rgb'))['color']);
        $this->assertNotSame('#ffffff', $result->getComputedStyle($document->getElementById('mix'))['color']);
        $this->assertSame('#123456', $session->getContext()->colors['bright-red']);
        $gradients = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'gradient' === $operation['type']));
        $this->assertCount(1, $gradients);
        $this->assertSame([18 / 255, 52 / 255, 86 / 255], $gradients[0]['gradient']['stops'][0][0][0]);
    }

    public function testPointerGesturesDragSelectionAndDismissWithoutClickThrough()
    {
        $session = (new HtmlRenderer())->createSession('<input id="text" value="ABCD"><button style="width:10ch">Outside</button><dialog closedby="any" style="width:8ch;height:3lh"><input></dialog>', new RenderingContext(480, 320));
        $widget = new DocumentWidget($session);
        $widget->render(new RenderContext(60, 20));
        $widget->handlePointerGesture('down', 0, 2);
        $widget->handlePointerGesture('move', 0, 5);
        $widget->handlePointerGesture('up', 0, 5);
        $this->assertSame([0, 3], $session->getTextSelection($session->getDocument()->getElementById('text')));
        $dialog = $session->getDocument()->querySelector('dialog');
        $session->showModal($dialog);
        $widget->render(new RenderContext(60, 20));
        $clicks = 0;
        $session->on('click', static function () use (&$clicks) { ++$clicks; });
        $widget->handlePointer(0, 0, true);
        $this->assertFalse($dialog->hasAttribute('open'));
        $this->assertSame(0, $clicks);
    }

    public function testDensityUsesHostStateAndNearestDocumentOverride()
    {
        $html = '<style>:condensed > legend{display:none}:condensed{color:red}:regular{color:blue}p{margin:0}</style><fieldset id="outer" data-density="condensed"><legend id="legend">Hidden</legend><p id="compact">Compact</p><section data-density="regular"><p id="regular">Regular</p></section></fieldset><p id="host">Host</p>';
        $session = (new HtmlRenderer())->createSession($html, new RenderingContext(480, 320));
        $standalone = $session->render();
        $this->assertNotSame('none', $standalone->getComputedStyle($session->getDocument()->getElementById('legend'))['display'] ?? null);
        $this->assertNotSame('red', $standalone->getComputedStyle($session->getDocument()->getElementById('compact'))['color'] ?? null);
        $widget = new DocumentWidget($session);
        $widget->render(new RenderContext(60, 20));
        $result = $session->render();
        $document = $session->getDocument();
        $this->assertSame('none', $result->getComputedStyle($document->getElementById('legend'))['display']);
        $this->assertSame([], $result->getBounds($document->getElementById('legend')));
        $this->assertSame('0', $result->getComputedStyle($document->getElementById('compact'))['margin-top']);
        $this->assertSame('condensed', $document->getElementById('outer')->getAttribute('data-density'));
        $this->assertSame('red', $result->getComputedStyle($document->getElementById('compact'))['color']);
        $this->assertSame('blue', $result->getComputedStyle($document->getElementById('regular'))['color']);
        $this->assertSame('blue', $result->getComputedStyle($document->getElementById('host'))['color']);
        $widget->setDensity(Density::Condensed);
        $widget->render(new RenderContext(60, 20));
        $result = $session->render();
        $this->assertSame('0', $result->getComputedStyle($document->getElementById('host'))['margin-top']);
        $this->assertSame('red', $result->getComputedStyle($document->getElementById('host'))['color']);
        $this->assertSame('blue', $result->getComputedStyle($document->getElementById('regular'))['color']);
        $this->assertSame('0', $result->getComputedStyle($document->getElementById('regular'))['margin-top']);
        $widget->render(new RenderContext(60, 20));
        $this->assertSame($result, $session->render());
    }

    public function testTerminalInputReservesItsPromptAndCursorWithoutOverridingAuthorWidth()
    {
        foreach ([8 => 184.0, 16 => 368.0] as $cellWidth => $expected) {
            $metrics = new TerminalTextMeasurer($cellWidth, $cellWidth * 2);
            $session = (new HtmlRenderer())->createSession('<input id="default" value="70"><span>kg</span><input id="authored" style="width:8ch" value="A">', new RenderingContext(480, 128));
            $lines = (new DocumentWidget($session, $metrics))->render(new RenderContext(60, 8));
            $result = $session->render();
            $document = $session->getDocument();
            $this->assertSame($expected, $result->getBounds($document->getElementById('default'))[0]->width);
            $this->assertSame($cellWidth * 8.0, $result->getBounds($document->getElementById('authored'))[0]->width);
            $this->assertStringContainsString('> 70                   kg', AnsiUtils::stripAnsiCodes($lines[0]));
        }
    }

    public function testTerminalControlAppearanceRemainsOverridableByAuthorStyles()
    {
        $html = '<style>input::before,button::before,button::after{content:none}input[type=checkbox]{appearance:auto;width:1em}</style><input value="Text"><button>Save</button><input type="checkbox" checked>';
        $session = (new HtmlRenderer())->createSession($html, new RenderingContext(480, 128));
        $lines = (new DocumentWidget($session))->render(new RenderContext(60, 8));
        $screen = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), $lines));
        $this->assertStringContainsString('Text', $screen);
        $this->assertStringContainsString('Save', $screen);
        $this->assertStringContainsString('✓', $screen);
        $this->assertStringNotContainsString('> ', $screen);
        $this->assertStringNotContainsString('[x]', $screen);
        $this->assertStringNotContainsString('[ Save ]', $screen);
    }

    public function testTranslatesInputAndSubmitsFormValues()
    {
        $session = (new HtmlRenderer())->createSession('<form action="/save" method="post"><input name="title" value="A"><button>Save</button></form>', new RenderingContext(160, 32, new TerminalTextMeasurer()));
        $widget = new DocumentWidget($session);
        $submitted = null;
        $widget->on(FormSubmitEvent::class, static function (FormSubmitEvent $event) use (&$submitted): void { $submitted = $event; });
        $widget->setFocused(true);
        $widget->handleInput('中');
        $widget->handleInput("\r");
        $this->assertSame(['title' => 'A中'], $submitted?->getValues());
        $this->assertSame('/save', $submitted?->getAction());
        $this->assertSame('post', $submitted?->getMethod());
        $widget->handleInput("\x7f");
        $widget->handleInput("\r");
        $this->assertSame(['title' => 'A'], $submitted?->getValues());
    }

    public function testDecodesPrintableKeysFromTheTerminalProtocol()
    {
        $session = (new HtmlRenderer())->createSession('<input>', new RenderingContext(160, 32));
        $widget = new DocumentWidget($session);
        $widget->setFocused(true);
        $widget->handleInput("\x1b[97u");
        $widget->handleInput("\x1b[C");
        $this->assertSame('a', $session->getValue($session->getFocusedElement()));
    }

    public function testPointerAndKeyboardForwardEachUncancelledFormSubmissionOnce()
    {
        $session = (new HtmlRenderer())->createSession('<form><button style="width:32px;height:16px;padding:0;border:0">Go</button></form>', new RenderingContext(160, 64));
        $widget = new DocumentWidget($session);
        $submissions = 0;
        $widget->on(FormSubmitEvent::class, static function () use (&$submissions): void { ++$submissions; });
        $widget->render(new RenderContext(20, 4));
        $widget->handlePointer(0, 0, true);
        $this->assertSame(1, $submissions);
        $widget->handleInput("\r");
        $this->assertSame(2, $submissions);
        $session->on('submit', static function ($event): void { $event->preventDefault(); });
        $widget->handlePointer(0, 0, true);
        $widget->handleInput("\r");
        $this->assertSame(2, $submissions);
    }

    public function testRoutesEditingSelectionAndChunkedPasteToTheDocumentEngine()
    {
        $session = (new HtmlRenderer())->createSession('<input value="A中é">', new RenderingContext(160, 64));
        $widget = new DocumentWidget($session);
        $widget->setFocused(true);
        $element = $session->getFocusedElement();
        $widget->handleInput("\x1b[D");
        $widget->handleInput("\x7f");
        $this->assertSame('Aé', $session->getValue($element));
        $widget->handleInput("\x01");
        $this->assertSame([0, 2], $session->getTextSelection($element));
        $widget->handleInput("\x1b[200~Hello");
        $widget->handleInput(" 中文\x1b[201~");
        $this->assertSame('Hello 中文', $session->getValue($element));
        $widget->handleInput("\x1a");
        $this->assertSame('Aé', $session->getValue($element));
        $widget->handleInput("\x19");
        $this->assertSame('Hello 中文', $session->getValue($element));
    }

    public function testRoutesSelectAndNumberKeysThroughSemanticDefaultActions()
    {
        $session = (new HtmlRenderer())->createSession('<select><option value="a">A</option><option value="b">B</option></select><input type="number" value="10" step="2">', new RenderingContext(160, 64));
        $widget = new DocumentWidget($session);
        $widget->setFocused(true);
        $widget->handleInput("\x1b[B");
        $this->assertSame('a', $session->getValue($session->getFocusedElement()));
        $this->assertNotEmpty($session->render()->getBounds($session->getDocument()->querySelectorAll('option')->item(1)));
        $widget->handleInput("\x1b[B");
        $this->assertSame('a', $session->getValue($session->getFocusedElement()));
        $widget->handleInput("\r");
        $this->assertSame('b', $session->getValue($session->getFocusedElement()));
        $widget->handleInput("\t");
        $widget->handleInput("\x1b[A");
        $this->assertSame('12', $session->getValue($session->getFocusedElement()));
        $session->on('keydown', static function ($event): void { $event->preventDefault(); });
        $widget->handleInput('9');
        $this->assertSame('12', $session->getValue($session->getFocusedElement()));
    }

    public function testKittyTabsUseTheSameNativeFocusBoundary()
    {
        $session = (new HtmlRenderer())->createSession('<input id="first"><input id="last">', new RenderingContext(160, 64));
        $widget = new DocumentWidget($session);
        $other = new InputWidget();
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        $tui->add($widget);
        $tui->add($other);
        $tui->setFocus($widget);
        $tui->handleInput("\x1b[9u");
        $this->assertSame('last', $session->getFocusedElement()?->id);
        $tui->handleInput("\x1b[9u");
        $this->assertSame($other, $tui->getFocus());
    }

    public function testSpaceActivatesCheckboxesWithoutChangingTheirValue()
    {
        $session = (new HtmlRenderer())->createSession('<input type="checkbox" value="accepted">', new RenderingContext(160, 32));
        $widget = new DocumentWidget($session);
        $widget->setFocused(true);
        $element = $session->getFocusedElement();
        $widget->handleInput(' ');
        $this->assertTrue($session->isChecked($element));
        $this->assertFalse($element->hasAttribute('checked'));
        $this->assertSame('accepted', $session->getValue($element));
        $widget->handleInput(' ');
        $this->assertFalse($session->isChecked($element));
        $this->assertFalse($element->hasAttribute('checked'));
    }

    public function testEnterInATextareaInsertsANewline()
    {
        $session = (new HtmlRenderer())->createSession('<form><textarea name="text">First</textarea></form>', new RenderingContext(160, 32));
        $widget = new DocumentWidget($session);
        $submitted = false;
        $widget->on(FormSubmitEvent::class, static function () use (&$submitted): void { $submitted = true; });
        $widget->setFocused(true);
        $widget->handleInput("\r");
        $this->assertSame("First\n", $session->getValue($session->getFocusedElement()));
        $this->assertFalse($submitted);
    }

    public function testNativeFocusPreservesAnExistingDocumentFocus()
    {
        $session = (new HtmlRenderer())->createSession('<input id="first"><input id="last">', new RenderingContext(160, 32));
        $last = $session->getDocument()->getElementById('last');
        $session->focus($last);
        $widget = new DocumentWidget($session);
        $widget->setFocused(true);
        $this->assertSame($last, $session->getFocusedElement());
        $widget->setFocused(false);
        $this->assertNull($session->getFocusedElement());
        $widget->setFocused(true);
        $this->assertSame($last, $session->getFocusedElement());
    }

    public function testPointerActivationSynchronizesNativeAndDocumentFocus()
    {
        $session = (new HtmlRenderer())->createSession('<input style="width:80px;height:16px">', new RenderingContext(160, 32));
        $widget = new DocumentWidget($session);
        $other = new InputWidget();
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        $tui->add($widget);
        $tui->add($other);
        $tui->setFocus($other);
        $widget->handlePointer(0, 0, true);
        $this->assertSame($widget, $tui->getFocus());
        $this->assertSame('input', $session->getFocusedElement()?->localName);
        $tui->remove($widget);
        $this->assertFalse($widget->isFocused());
        $this->assertNull($session->getFocusedElement());
    }

    public function testTabIndexOrderDoesNotDependOnDomOrderAtTheNativeBoundary()
    {
        $session = (new HtmlRenderer())->createSession('<input id="two" tabindex="2"><input id="one" tabindex="1"><input id="zero">', new RenderingContext(160, 64));
        $widget = new DocumentWidget($session);
        $other = new InputWidget();
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        $tui->add($widget);
        $tui->add($other);
        $tui->setFocus($widget);
        $this->assertSame('one', $session->getFocusedElement()?->id);
        $tui->handleInput("\t");
        $this->assertSame($widget, $tui->getFocus());
        $this->assertSame('two', $session->getFocusedElement()?->id);
        $tui->handleInput("\t");
        $this->assertSame('zero', $session->getFocusedElement()?->id);
        $tui->handleInput("\t");
        $this->assertSame($other, $tui->getFocus());
    }

    public function testInternalTabOrderYieldsToTheTuiFocusManagerAtTheBoundary()
    {
        $session = (new HtmlRenderer())->createSession('<input id="first"><input id="last">', new RenderingContext(160, 32));
        $widget = new DocumentWidget($session);
        $next = new InputWidget();
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        $tui->add($widget);
        $tui->add($next);
        $tui->setFocus($widget);
        $tui->handleInput("\t");
        $this->assertSame('last', $session->getFocusedElement()?->id);
        $this->assertSame($widget, $tui->getFocus());
        $tui->handleInput("\t");
        $this->assertSame($next, $tui->getFocus());
        $this->assertNull($session->getFocusedElement());
    }
}
