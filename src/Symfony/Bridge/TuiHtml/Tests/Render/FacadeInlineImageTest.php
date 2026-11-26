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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Event\LinkEvent;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Internal\StyleBridge;
use Symfony\Bridge\TuiHtml\Render\FacadeRenderer;
use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\RasterImage;
use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\FacadeScenario;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;
use Symfony\Bridge\TuiHtml\Widget\Text\InlineNode;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

#[RequiresPhpExtension('gd')]
class FacadeInlineImageTest extends TestCase
{
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAPUlEQVRYhe3VsQ3AQAwDMSn77+wfQkUaqj+AcONechnWqU6+Ld8HAAAAAAAA0OTGj94u9e8XAAAAAAAAAHghNAU9l77oWAAAAABJRU5ErkJggg==';

    public function testOnlyInlineImageCellsBecomePortableReplacedContent()
    {
        $html = '<p>Before <a href="https://example.org"><img src="'.self::PNG.'" alt="docs"> After</a> later</p>';
        $this->assertLegacyCells($html);
        $this->assertLegacyCells($html, ['dppx' => 2]);
        $view = new HtmlBuilder()->useHtmlRenderer()->build($html);
        $renderer = new FacadeRenderer(new Renderer());
        $renderer->renderFrame($view->getRoot(), 40, 15);
        $result = $view->getRoot()->getPresentation()->getResult();
        $walk = static function (Fragment $fragment) use (&$walk): void {
            self::assertDoesNotMatchRegularExpression('/[\x{100000}-\x{10FFFF}]/u', $fragment->text ?? '');
            foreach ($fragment->children as $child) {
                $walk($child);
            }
        };
        $walk($result->getLayout());
        self::assertDoesNotMatchRegularExpression('/[\x{100000}-\x{10FFFF}]/u', json_encode($result->getDisplayList()->getOperations(), \JSON_UNESCAPED_UNICODE));
        self::assertSame('img', $result->getDocument()->querySelector('img')->localName);
        self::assertSame('https://example.org', $result->getDocument()->querySelector('a')->getAttribute('href'));
    }

    public function testWideImageCellsCanWrapInsideTheirOwnAllocatedWidth()
    {
        foreach ([3, 4, 8, 12] as $columns) {
            $this->assertLegacyCells('<p style="width:'.$columns.'ch">A <a href="/x"><img src="'.self::PNG.'" width="64" height="16" alt="wide"></a> Z</p>');
        }
    }

    public function testSeveralImagesKeepTheirOwnAssetAndInheritedVectorColor()
    {
        $svg = '<svg viewBox="0 0 24 24"><title>icon</title><rect width="24" height="24" fill="currentColor"/></svg>';
        $this->assertLegacyCells('<p><a href="/x" style="color:#00ff00">A '.$svg.' '.$svg.'</a> <span style="color:#ff0000">B '.$svg.'</span> C</p>');
    }

    public function testBrokenInlineImagesKeepTheNativeTextAlternative()
    {
        $this->assertLegacyCells('<p>Before <a href="/x"><img src="missing.png" alt="broken"> After</a></p>');
    }

    public function testVectorColorsFollowTheActualLightAndDarkTerminalProfile()
    {
        $svg = '<svg viewBox="0 0 24 24"><rect width="24" height="24" fill="currentColor"/></svg>';
        foreach (['light', 'dark'] as $scheme) {
            $this->assertLegacyCells('<p>Before <a href="/x">'.$svg.' After</a></p>', ['scheme' => $scheme]);
        }
    }

    public function testVectorForegroundIntentDistinguishesUnknownDefaultFromEqualRgbValues()
    {
        $cases = [
            'unknown-light' => ['light', '', '', [0, 0, 0], '#e5e5e5'],
            'unknown-dark' => ['dark', '', '', [229, 229, 229], '#e5e5e5'],
            'ancestor-e5' => ['light', 'color:#e5e5e5', '', [229, 229, 229], '#e5e5e5'],
            'image-e5' => ['light', '', 'color:#e5e5e5', [229, 229, 229], '#e5e5e5'],
            'initial' => ['light', '', 'color:initial', [0, 0, 0], '#000000'],
            'unset' => ['light', '', 'color:unset', [0, 0, 0], '#e5e5e5'],
            'inherit' => ['light', '', 'color:inherit', [0, 0, 0], '#e5e5e5'],
            'currentColor' => ['light', '', 'color:currentColor', [0, 0, 0], '#e5e5e5'],
            'configured' => ['light', '', '', [18, 52, 86], '#123456'],
            'native-default' => ['light', '', '', [0, 0, 0], '#e5e5e5'],
            'native-rgb' => ['light', '', '', [229, 229, 229], '#e5e5e5'],
            'native-ancestor-rgb' => ['light', '', '', [18, 52, 86], '#123456'],
            'animated' => ['light', '', 'animation:ink 1s both', [229, 229, 229], '#e5e5e5'],
        ];
        foreach ($cases as $name => [$scheme, $parentStyle, $imageStyle, $pixels, $computedColor]) {
            $view = new HtmlBuilder()->useHtmlRenderer()->build('<style>@keyframes ink{from{color:#e5e5e5}to{color:#00ff00}}</style><p id="p" style="'.$parentStyle.'">Before <a href="/x"><svg style="'.$imageStyle.'" viewBox="0 0 24 24"><rect width="24" height="24" fill="currentColor"/></svg> After</a></p>');
            $view->getStyleSheet()->setAnimationHandlers(null, static fn (): float => 0.0);
            $widget = $view->getById('p');
            if ('native-default' === $name || 'native-rgb' === $name) {
                StyleBridge::set($widget, new Style()->withColor('native-default' === $name ? Color::named('default') : Color::hex('#e5e5e5')));
            } elseif ('native-ancestor-rgb' === $name) {
                StyleBridge::set($view->getBody(), new Style()->withColor(Color::hex('#123456')));
            }
            $renderer = new FacadeRenderer(new Renderer());
            $renderer->setMediaContext(new MediaContext(40, 20, ColorScheme::from($scheme)));
            if ('configured' === $name) {
                $renderer->setDefaultForeground('#123456');
            }
            $lines = $renderer->renderFrame($view->getRoot(), 40, 20)->toArray();
            $cells = TerminalCellSnapshot::normalize(implode("\n", $lines), 40, 20, [229, 229, 229]);
            $found = false;
            foreach ($cells as $row) {
                foreach ($row as $cell) {
                    if ('▀' === $cell[0]) {
                        self::assertSame($pixels, $cell[1], $name);
                        $found = true;
                    }
                }
            }
            self::assertTrue($found, $name);
            $result = $view->getRoot()->getPresentation()->getResult();
            self::assertSame($computedColor, $result->getComputedStyle($result->getDocument()->querySelector('svg'))['color'], $name);
        }
    }

    public function testVectorTextAlternativesStayNormalInlineContent()
    {
        $this->assertLegacyCells('<p>Before <a href="/x"><svg aria-label="logo"></svg> After</a></p>');
    }

    public function testRepeatedLinkTargetsRetainDistinctNativeLinkIds()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p id="p"><a href="https://example.org"><img src="'.self::PNG.'" alt="first"></a> gap <a href="https://example.org"><img src="'.self::PNG.'" alt="second"></a></p>');
        $renderer = new FacadeRenderer(new Renderer());
        $lines = implode("\n", $renderer->renderFrame($view->getRoot(), 40, 20)->toArray());
        $widget = $view->getById('p');
        self::assertStringContainsString("\x1b]8;id=".RichTextWidget::linkId($widget, 0).';https://example.org', $lines);
        self::assertStringContainsString("\x1b]8;id=".RichTextWidget::linkId($widget, 1).';https://example.org', $lines);
        $result = $view->getRoot()->getPresentation()->getResult();
        foreach ($result->getDocument()->querySelectorAll('img') as $index => $node) {
            $bounds = $result->getBounds($node)[0];
            self::assertSame(RichTextWidget::linkId($widget, $index), $renderer->getLinkAt((int) ($bounds->y / 16), (int) ($bounds->x / 8)));
        }
    }

    public function testAnImageInAnOuterBlockLinkKeepsItsActualNativeOwner()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<a href="https://example.org" style="display:block"><div><p id="p">Before <span><img src="'.self::PNG.'" alt="image"></span> After</p></div></a>');
        $renderer = new FacadeRenderer(new Renderer());
        $lines = implode("\n", $renderer->renderFrame($view->getRoot(), 40, 20)->toArray());
        $widget = $view->getById('p');
        while (!$widget instanceof RichTextWidget) {
            self::assertInstanceOf(\Symfony\Component\Tui\Widget\ParentInterface::class, $widget);
            $widget = $widget->all()[0];
        }
        self::assertStringContainsString("\x1b]8;id=".RichTextWidget::linkId($widget, 0).';https://example.org', $lines);
        $result = $view->getRoot()->getPresentation()->getResult();
        $bounds = $result->getBounds($result->getDocument()->querySelector('img'))[0];
        self::assertSame(RichTextWidget::linkId($widget, 0), $renderer->getLinkAt((int) ($bounds->y / 16), (int) ($bounds->x / 8)));
    }

    public function testImageClickEmitsTheActualNativeLinkedTextEvent()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p id="p"><a href="https://example.org"><img src="'.self::PNG.'" alt="image"></a></p>');
        $renderer = new FacadeRenderer(new Renderer());
        $tui = new Tui(null, new VirtualTerminal(40, 20), null, null, $renderer);
        $widget = $view->getById('p');
        $events = [];
        $widget->on(LinkEvent::class, static function (LinkEvent $event) use (&$events): void {
            $events[] = [$event->getTarget(), $event->getHref()];
            $event->stopPropagation();
        });
        try {
            $view->mount($tui);
            $tui->start();
            $tui->processRender();
            $result = $view->getRoot()->getPresentation()->getResult();
            $bounds = $result->getBounds($result->getDocument()->querySelector('img'))[0];
            self::assertTrue($view->click((int) ($bounds->y / 16), (int) ($bounds->x / 8), true));
            self::assertSame([[$widget, 'https://example.org']], $events);
        } finally {
            $view->unmount();
            $tui->stop();
        }
    }

    public function testProgrammaticBlockHrefUsesTheSameNativeImageOwner()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p id="p">Before</p>');
        $widget = $view->getById('p');
        $widget->setInlineNodes([InlineNode::text('Before '), InlineNode::element('img', ['alt' => 'image'], [InlineNode::text(RichTextWidget::inlineImageText(0, 2))]), InlineNode::text(' After')]);
        $widget->setInlineImages([[new RasterImage(base64_decode(substr(self::PNG, \strlen('data:image/png;base64,')))), 2]]);
        $widget->setLink('https://example.org');
        $renderer = new FacadeRenderer(new Renderer());
        $lines = implode("\n", $renderer->renderFrame($view->getRoot(), 40, 20)->toArray());
        self::assertStringContainsString("\x1b]8;id=".RichTextWidget::linkId($widget, 0).';https://example.org', $lines);
        $result = $view->getRoot()->getPresentation()->getResult();
        $bounds = $result->getBounds($result->getDocument()->querySelector('img'))[0];
        self::assertSame(RichTextWidget::linkId($widget, 0), $renderer->getLinkAt((int) ($bounds->y / 16), (int) ($bounds->x / 8)));
    }

    public function testImageRevisionReplacesPreparedPixelsWithoutChangingNativeInstances()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p id="p">Before <a href="/x"><img src="'.self::PNG.'" alt="image"></a> After</p>');
        $renderer = new FacadeRenderer(new Renderer());
        $before = $renderer->renderFrame($view->getRoot(), 40, 20)->toArray();
        $widget = $view->getById('p');
        $png = imagecreatetruecolor(2, 2);
        imagefill($png, 0, 0, 0x00FF00);
        ob_start();
        imagepng($png);
        $bytes = ob_get_clean();
        $widget->setInlineImages([[new RasterImage($bytes), 2]]);
        $after = $renderer->renderFrame($view->getRoot(), 40, 20)->toArray();
        self::assertSame($widget, $view->getById('p'));
        self::assertNotSame($before, $after);
        self::assertStringContainsString('38;2;0;255;0', implode("\n", $after));
        self::assertSame($after, $renderer->renderFrame($view->getRoot(), 40, 20)->toArray());
    }

    public function testUnmountAndRemountPreserveTheNativeWidgetAndReleasePreparedOutput()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p id="p">Before <a href="/x"><img src="'.self::PNG.'" alt="image"></a> After</p>');
        $widget = $view->getById('p');
        $presentation = $view->getRoot()->getPresentation();
        $tui = new Tui(null, new VirtualTerminal(40, 20), null, null, new FacadeRenderer(new Renderer()));
        try {
            $view->mount($tui);
            $tui->start();
            $tui->processRender();
            $before = $presentation->getResult();
            self::assertNotNull($widget->getContext());
            $view->unmount();
            self::assertNull($presentation->getResult());
            self::assertNull($widget->getContext());
            $view->mount($tui);
            $tui->processRender();
            self::assertSame($widget, $view->getById('p'));
            self::assertNotSame($before, $presentation->getResult());
            self::assertSame($before->getDisplayList()->getOperations(), $presentation->getResult()->getDisplayList()->getOperations());
        } finally {
            $view->unmount();
            $tui->stop();
        }
    }

    public function testAuthorImageConstraintsRemainCascadeFactsAndUsedBounds()
    {
        foreach (['max-width:none;width:10ch' => 80.0, 'min-width:6ch' => 48.0, 'width:2ch!important;max-width:none' => 16.0] as $style => $width) {
            $view = new HtmlBuilder()->useHtmlRenderer()->build('<p style="width:3ch">Before <a href="/x"><img src="'.self::PNG.'" style="'.$style.'" alt="image"></a></p>');
            $renderer = new FacadeRenderer(new Renderer());
            $renderer->renderFrame($view->getRoot(), 40, 20);
            $result = $view->getRoot()->getPresentation()->getResult();
            $node = $result->getDocument()->querySelector('img');
            self::assertSame($width, $result->getBounds($node)[0]->width);
            self::assertSame($style, $node->getAttribute('style'));
        }
    }

    public function testAuthoredImageHeightRemainsAuthoritative()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p><a href="/x"><img src="'.self::PNG.'" style="height:2lh!important;width:2ch;max-width:none" alt="image"></a></p>');
        new FacadeRenderer(new Renderer())->renderFrame($view->getRoot(), 40, 20);
        $result = $view->getRoot()->getPresentation()->getResult();
        $node = $result->getDocument()->querySelector('img');
        self::assertSame(32.0, $result->getBounds($node)[0]->height);
        self::assertSame('2lh', $result->getComputedStyle($node)['height']);
    }

    private function assertLegacyCells(string $html, array $options = []): void
    {
        $options += ['columns' => 40, 'rows' => 20];
        $old = FacadeScenario::render($html, $options);
        $new = FacadeScenario::render($html, $options, true);
        self::assertDoesNotMatchRegularExpression('/[\x{100000}-\x{10FFFF}]/u', $new);
        self::assertSame(AnsiUtils::stripAnsiCodes(str_replace('⟨', "\x1b[", $old)), AnsiUtils::stripAnsiCodes(str_replace('⟨', "\x1b[", $new)));
        self::assertSame(TerminalCellSnapshot::normalize($old, $options['columns'], $options['rows'], [229, 229, 229]), TerminalCellSnapshot::normalize($new, $options['columns'], $options['rows'], [229, 229, 229]));
    }
}
