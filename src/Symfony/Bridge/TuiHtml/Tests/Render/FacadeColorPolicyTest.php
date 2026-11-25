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
use Symfony\Bridge\TuiHtml\Render\FacadeRenderer;
use Symfony\Bridge\TuiHtml\Render\Renderer as LegacyRenderer;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\StyleSheet;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet as NativeStyleSheet;
use Symfony\Component\Tui\Widget\ContainerWidget;

class FacadeColorPolicyTest extends TestCase
{
    public function testDefaultForegroundPolicyPrecedesAuthoredHtmlBodyAndInheritedColors(): void
    {
        foreach (['', 'html{color:red}', 'body{color:blue}', 'body{color:green}p{color:inherit}'] as $css) {
            foreach ([ColorScheme::Dark, ColorScheme::Light] as $scheme) {
                $this->assertLegacyEqual('<style>p{margin:0}'.$css.'</style><p>A<span>B</span></p>', null, null, new MediaContext(20, 5, $scheme));
            }
        }
    }

    public function testOwnColorWinsImportantAndLeavesAuthoredInlineDescendantsUntouched(): void
    {
        foreach (['p{color:red}', 'p{color:red!important}', 'p{color:red}span{color:green}'] as $css) {
            $this->assertLegacyEqual('<style>p{margin:0}'.$css.'</style><p id="p">A<span>B</span></p>', 'p', 'blue');
        }
        $this->assertLegacyEqual('<style>@keyframes x{from{color:red}to{color:green}}p{margin:0;animation:x 1s infinite}</style><p id="p">A</p>', 'p', 'blue');
        $this->assertLegacyEqual('<style>p{margin:0;color:red}p::before{content:"X"}p::after{content:"Y";color:green}</style><p id="p">A<span>B</span></p>', 'p', 'blue');
        $this->assertLegacyEqual('<style>p{margin:0}</style><div id="p"><p>A<span>B</span></p></div>', 'p', 'blue');
        $this->assertLegacyEqual('<style>p{margin:0;color:red}</style><div id="p"><p>A<span>B</span></p></div>', 'p', 'blue');
    }

    public function testPseudoKeywordsAndInvalidVariablesRetainDistinctNativeColorBehavior(): void
    {
        foreach (['inherit', 'unset', 'initial', 'revert', 'var(--missing)', 'var(--missing,green)'] as $value) {
            $this->assertLegacyEqual('<style>p{margin:0;color:red}p::before{content:"X";color:'.$value.'}</style><p id="p">A<span>B</span></p>', 'p', 'blue');
        }
    }

    public function testNativeStylesheetColorIsBelowAuthoredCssAndOwnColor(): void
    {
        $theme = new NativeStyleSheet([RichTextWidget::class => new Style()->withColor('green')]);
        $this->assertLegacyEqual('<p style="margin:0">A<span>B</span></p>', null, null, null, $theme);
        $this->assertLegacyEqual('<p id="p" style="margin:0;color:red">A<span>B</span></p>', null, null, null, $theme);
        $this->assertLegacyEqual('<p id="p" style="margin:0;color:red">A<span>B</span></p>', 'p', 'blue', null, $theme);
        $this->assertLegacyEqual('<p id="p" style="margin:0;color:red">A<span>B</span></p>', null, null, null, $theme, true);
    }

    public function testCallerForegroundAndOwnStyleMutationsInvalidatePreparedFrame(): void
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p id="p" style="margin:0">A</p>');
        $outer = new ContainerWidget();
        $outer->add($view->getRoot());
        $renderer = new FacadeRenderer(new Renderer());
        $renderer->setDefaultForeground('#123456');
        $renderer->renderFrame($outer, 20, 5);
        self::assertSame('#123456', $view->getRoot()->getPresentation()->getResult()->getDisplayList()->getOperations()[0]['color']);
        $view->getById('p')->setStyle(new Style()->withColor('blue'));
        $renderer->renderFrame($outer, 20, 5);
        self::assertSame('#0000ee', $view->getRoot()->getPresentation()->getResult()->getDisplayList()->getOperations()[0]['color']);
        $view->getById('p')->setStyle(null);
        $renderer->setDefaultForeground('#654321');
        $renderer->renderFrame($outer, 20, 5);
        self::assertSame('#654321', $view->getRoot()->getPresentation()->getResult()->getDisplayList()->getOperations()[0]['color']);
        $renderer->addStyleSheet(new NativeStyleSheet([RichTextWidget::class => new Style()->withColor('green')]));
        $renderer->renderFrame($outer, 20, 5);
        self::assertSame('#00cd00', $view->getRoot()->getPresentation()->getResult()->getDisplayList()->getOperations()[0]['color']);
    }

    public function testNativeParentForegroundIsInheritedBelowAuthoredColorsAndConfiguredFallback(): void
    {
        foreach (['' => '#cd0000', 'color:blue' => 'blue'] as $declaration => $expected) {
            $view = new HtmlBuilder()->useHtmlRenderer()->build('<p style="margin:0;'.$declaration.'">A</p>');
            $outer = new ContainerWidget();
            $outer->setStyle(new Style()->withColor('red'));
            $outer->add($view->getRoot());
            $renderer = new FacadeRenderer(new Renderer());
            $renderer->setDefaultForeground('#123456');
            $renderer->renderFrame($outer, 20, 5);
            self::assertSame($expected, $view->getRoot()->getPresentation()->getResult()->getDisplayList()->getOperations()[0]['color']);
        }
    }

    public function testTerminalBorderBoxDefaultRemainsBelowAuthoredBoxSizing(): void
    {
        foreach (['' => 80.0, 'box-sizing:content-box' => 112.0, 'box-sizing:inherit' => 112.0] as $declaration => $width) {
            $view = new HtmlBuilder()->useHtmlRenderer()->build('<style>body{box-sizing:content-box}p{margin:0;width:10ch;padding:0 2ch;'.$declaration.'}</style><p id="p">A</p>');
            $outer = new ContainerWidget();
            $outer->add($view->getRoot());
            $renderer = new FacadeRenderer(new Renderer());
            $renderer->renderFrame($outer, 20, 5);
            $snapshot = $view->getStyleSheet()->getMirrorProjection();
            $node = $snapshot['nodes'][$view->getById('p')];
            $result = $view->getRoot()->getPresentation()->getResult();
            self::assertSame($width, $result->getBounds($node)[0]->width, $declaration);
        }
    }

    private function assertLegacyEqual(string $html, ?string $id = null, ?string $ownColor = null, ?MediaContext $media = null, ?NativeStyleSheet $theme = null, bool $themeBeforeCss = false): void
    {
        $expected = $actual = [];
        foreach ([false, true] as $core) {
            $builder = new HtmlBuilder();
            $builder->useHtmlRenderer($core);
            $view = $builder->build($html);
            if (null !== $ownColor) {
                $view->getById($id)->setStyle(new Style()->withColor($ownColor));
            }
            $view->getStyleSheet()->setAnimationHandlers(null, static fn (): float => 0.0);
            $outer = new ContainerWidget();
            $outer->add($view->getRoot());
            $sheet = new StyleSheet();
            $renderer = $core ? new FacadeRenderer(new Renderer()) : new LegacyRenderer($sheet);
            if ($themeBeforeCss && null !== $theme) {
                $renderer->addStyleSheet($theme);
            }
            $renderer->getStyleSheet()->addProvider($view->getStyleSheet());
            if (!$themeBeforeCss && null !== $theme) {
                $renderer->addStyleSheet($theme);
            }
            $renderer->setMediaContext($media ?? new MediaContext(20, 5));
            $lines = $renderer->renderFrame($outer, 20, 5)->toArray();
            $cells = TerminalCellSnapshot::normalize(implode("\r\n", $lines), 20, 5, [229, 229, 229], [0, 0, 0]);
            if ($core) {
                $actual = $cells;
            } else {
                $expected = $cells;
            }
        }
        self::assertSame($expected, $actual, $html);
    }
}
