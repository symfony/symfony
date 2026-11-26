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
use Symfony\Bridge\TuiHtml\Internal\StyleBridge;
use Symfony\Bridge\TuiHtml\Render\FacadeRenderer;
use Symfony\Bridge\TuiHtml\Render\Renderer as LegacyRenderer;
use Symfony\Bridge\TuiHtml\Style\CellSize;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\Style as HtmlStyle;
use Symfony\Bridge\TuiHtml\Style\StyleSheet as HtmlStyleSheet;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Widget\ContainerWidget;

class FacadeStylePolicyTest extends TestCase
{
    public function testOwnMaximumWidthRetainsNativePrecedenceAndMinimumConflict()
    {
        foreach (['max-width:15ch', 'max-width:3ch!important', 'width:15ch', 'min-width:12ch;max-width:15ch', 'padding:0 1ch;border:1px solid;max-width:15ch'] as $css) {
            foreach ([new CellSize(8, 16), new CellSize(16, 32)] as $cell) {
                $this->assertLegacyEqual($css, new Style()->withMaxColumns(8), null, false, $cell);
            }
        }
    }

    public function testExplicitNativeVisibilityOverridesCssNoneWithoutChangingOtherDisplays()
    {
        $this->assertLegacyEqual('display:none', new Style()->withHidden(false));
        $this->assertLegacyEqual('display:block', new Style()->withHidden(true));
        $this->assertLegacyEqual('display:inline-block;width:8ch', new Style()->withHidden(false));
    }

    public function testNativeProviderOrderRemainsAuthoritativeForExplicitFields()
    {
        foreach ([false, true] as $before) {
            $theme = new StyleSheet([RichTextWidget::class => new Style()->withMaxColumns(8)]);
            $this->assertLegacyEqual('max-width:3ch', null, $theme, $before);
            $theme = new StyleSheet([RichTextWidget::class => new Style()->withHidden(true)]);
            $this->assertLegacyEqual('display:block', null, $theme, $before);
        }
    }

    public function testNativeUsedValuesDoNotRewriteCssFactsAndRefreshWithWidgetMutations()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p id="p" style="margin:0;max-width:3ch!important">abcdefghijklmnop</p>');
        $widget = $view->getById('p');
        $widget->setStyle(new Style()->withMaxColumns(8));
        $outer = new ContainerWidget();
        $outer->add($view->getRoot());
        $renderer = new FacadeRenderer(new Renderer());
        $renderer->getStyleSheet()->addProvider($view->getStyleSheet());
        $renderer->renderFrame($outer, 20, 6);
        $snapshot = $view->getStyleSheet()->getMirrorProjection();
        $node = $snapshot['nodes'][$widget];
        $first = $view->getRoot()->getPresentation()->getResult();
        self::assertSame('3ch', $first->getComputedStyle($node)['max-width']);
        self::assertSame(64.0, $first->getBounds($node)[0]->width);
        $widget->setStyle(new Style()->withMaxColumns(4));
        $renderer->renderFrame($outer, 20, 6);
        $second = $view->getRoot()->getPresentation()->getResult();
        self::assertSame($node, $view->getStyleSheet()->getMirrorProjection()['nodes'][$widget]);
        self::assertSame('3ch', $second->getComputedStyle($node)['max-width']);
        self::assertSame(32.0, $second->getBounds($node)[0]->width);
    }

    public function testDirectNativeRulesRefreshTheirBoundedPreCascadeSnapshot()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p id="p" style="margin:0">abcdefghijklmnop</p>');
        $outer = new ContainerWidget();
        $outer->add($view->getRoot());
        $renderer = new FacadeRenderer(new Renderer());
        $renderer->getStyleSheet()->addProvider($view->getStyleSheet());
        $renderer->getStyleSheet()->addRule(RichTextWidget::class, new HtmlStyle()->withMaxColumns(8));
        $renderer->renderFrame($outer, 20, 6);
        $node = $view->getStyleSheet()->getMirrorProjection()['nodes'][$view->getById('p')];
        $first = $view->getRoot()->getPresentation()->getResult();
        self::assertSame(64.0, $first->getBounds($node)[0]->width);
        $renderer->renderFrame($outer, 20, 6);
        self::assertSame($first, $view->getRoot()->getPresentation()->getResult());
        $renderer->getStyleSheet()->addRule(RichTextWidget::class, new HtmlStyle()->withMaxColumns(4));
        $renderer->renderFrame($outer, 20, 6);
        self::assertSame(32.0, $view->getRoot()->getPresentation()->getResult()->getBounds($node)[0]->width);
    }

    public function testCssProvidersInsideNativeStylesheetsAreNotReversedIntoNativeConstraints()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p id="p" style="margin:0;box-sizing:content-box;max-width:8ch;padding:0 1ch">abcdefghijklmnop</p>');
        $outer = new ContainerWidget();
        $outer->add($view->getRoot());
        $renderer = new FacadeRenderer(new Renderer());
        $renderer->addStyleSheet(new HtmlStyleSheet()->addProvider($view->getStyleSheet()));
        $renderer->renderFrame($outer, 20, 6);
        $node = $view->getStyleSheet()->getMirrorProjection()['nodes'][$view->getById('p')];
        $result = $view->getRoot()->getPresentation()->getResult();
        self::assertSame('content-box', $result->getComputedStyle($node)['box-sizing']);
        self::assertSame(80.0, $result->getBounds($node)[0]->width);
    }

    public function testAuthorWrappingAndWhitespaceOverrideTheTerminalUaPolicy()
    {
        foreach (['overflow-wrap:normal', 'white-space:nowrap', 'white-space:pre'] as $css) {
            $cells = $this->render(true, 'max-width:8ch;'.$css);
            self::assertSame('abcdefghijklmnop', implode('', array_column($cells[0], 0)), $css);
            self::assertCount(1, $cells, $css);
        }
    }

    public function testOuterNativeCapWithAuthoredContentBoxChromeIsExplicitlyUnsupported()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Native outer width caps');
        $this->render(true, 'box-sizing:content-box;padding:0 1ch', new Style()->withMaxColumns(8));
    }

    public function testNativeSpanMutationThatDiffersFromTheMirrorIsExplicitlyUnsupported()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<table><colgroup><col span="2" width="64"></colgroup><tr><td>A</td><td>B</td></tr></table>');
        $column = $view->querySelector('col');
        StyleBridge::set($column, StyleBridge::get($column)->withColumnSpan(3));
        $outer = new ContainerWidget();
        $outer->add($view->getRoot());
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Native span overrides');
        new FacadeRenderer(new Renderer())->renderFrame($outer, 20, 6);
    }

    private function assertLegacyEqual(string $css, ?Style $own = null, ?StyleSheet $theme = null, bool $before = false, ?CellSize $cell = null): void
    {
        self::assertSame($this->render(false, $css, $own, $theme, $before, $cell), $this->render(true, $css, $own, $theme, $before, $cell), $css);
    }

    private function render(bool $core, string $css, ?Style $own = null, ?StyleSheet $theme = null, bool $before = false, ?CellSize $cell = null): array
    {
        $view = new HtmlBuilder()->useHtmlRenderer($core)->build('<p id="p" style="margin:0;'.$css.'">abcdefghijklmnop</p>');
        $view->getById('p')->setStyle($own);
        $outer = new ContainerWidget();
        $outer->add($view->getRoot());
        $renderer = $core ? new FacadeRenderer(new Renderer()) : new LegacyRenderer();
        if ($before && null !== $theme) {
            $renderer->addStyleSheet($theme);
        }
        $renderer->getStyleSheet()->addProvider($view->getStyleSheet());
        if (!$before && null !== $theme) {
            $renderer->addStyleSheet($theme);
        }
        $renderer->setMediaContext(new MediaContext(20, 6, ColorScheme::Dark, Density::Regular, false, false, false, $cell));

        return TerminalCellSnapshot::normalize(implode("\r\n", $renderer->renderFrame($outer, 20, 6)->toArray()), 20, 6, [229, 229, 229], [0, 0, 0]);
    }
}
