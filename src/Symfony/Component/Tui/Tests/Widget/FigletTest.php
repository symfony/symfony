<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\TailwindStylesheet;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;
use Symfony\Component\Tui\Widget\TextWidget;

class FigletTest extends TestCase
{
    #[DataProvider('fontProvider')]
    public function testRenderWithFont(string $font, int $expectedLines)
    {
        $widget = new TextWidget('Hi');
        $lines = $this->renderWidgetWithFont($widget, $font, 80, 24);

        $this->assertCount($expectedLines, $lines);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function fontProvider(): iterable
    {
        yield 'big' => ['big', 6];
        yield 'small' => ['small', 4];
        yield 'slant' => ['slant', 5];
        yield 'standard' => ['standard', 5];
        yield 'mini' => ['mini', 3];
    }

    public function testRenderEmptyText()
    {
        $widget = new TextWidget('');
        $lines = $widget->render(new RenderContext(80, 24, new Style(font: 'big')));

        $this->assertSame([], $lines);
    }

    public function testRenderWhitespaceOnlyText()
    {
        $widget = new TextWidget('   ');
        $lines = $widget->render(new RenderContext(80, 24, new Style(font: 'big')));

        $this->assertSame([], $lines);
    }

    public function testTruncatesWhenTooWide()
    {
        $widget = new TextWidget('Hello World!');
        $width = 30;
        $lines = $widget->render(new RenderContext($width, 24, new Style(font: 'big')));

        foreach ($lines as $line) {
            $this->assertLessThanOrEqual($width, AnsiUtils::visibleWidth($line));
        }
    }

    public function testSetText()
    {
        $widget = new TextWidget('A');
        $linesA = $this->renderWidgetWithFont($widget, 'big', 80, 24);

        $widget->setText('B');
        $linesB = $this->renderWidgetWithFont($widget, 'big', 80, 24);

        $this->assertNotSame($linesA, $linesB);
    }

    public function testChangingFontViaStyle()
    {
        $widget = new TextWidget('Hi');
        $linesBig = $this->renderWidgetWithFont($widget, 'big', 80, 24);
        $linesSmall = $this->renderWidgetWithFont($widget, 'small', 80, 24);

        // Small font should produce fewer lines
        $this->assertLessThan(\count($linesBig), \count($linesSmall));
    }

    public function testUnknownFontThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not registered');

        $widget = new TextWidget('Hi');
        $widget->render(new RenderContext(80, 24, new Style(font: 'nonexistent_font')));
    }

    public function testWithoutFontRendersNormalText()
    {
        $widget = new TextWidget('Hi');
        $lines = $this->renderWidget($widget, 80, 24);

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Hi', AnsiUtils::stripAnsiCodes($lines[0]));
    }

    public function testStyleFontSwitchesRendering()
    {
        $widget = new TextWidget('Ok');

        // Normal text (no font in style)
        $normalLines = $this->renderWidget($widget, 80, 24);
        $this->assertCount(1, $normalLines);

        // FIGlet (font in style)
        $figletLines = $this->renderWidgetWithFont($widget, 'small', 80, 24);
        $this->assertCount(4, $figletLines);
    }

    public function testFontFromStylesheet()
    {
        $stylesheet = new StyleSheet([
            TextWidget::class => new Style(font: 'big'),
        ]);
        $renderer = new Renderer($stylesheet);

        $root = new ContainerWidget();
        $widget = new TextWidget('Ok');
        $root->add($widget);

        $lines = $renderer->render($root, 80, 24);

        // Should render as FIGlet (multi-line), not normal text
        $this->assertCount(6, $lines);
    }

    public function testFontFromCssClassRule()
    {
        $stylesheet = new StyleSheet([
            '.title' => new Style(font: 'small'),
        ]);
        $renderer = new Renderer($stylesheet);

        $root = new ContainerWidget();
        $widget = new TextWidget('Hi');
        $widget->addStyleClass('title');
        $root->add($widget);

        $lines = $renderer->render($root, 80, 24);

        // Should render as FIGlet
        $this->assertCount(4, $lines);
    }

    public function testFontFromTailwindUtilityClass()
    {
        $stylesheet = new TailwindStylesheet();
        $renderer = new Renderer($stylesheet);

        $root = new ContainerWidget();
        $widget = new TextWidget('Hi');
        $widget->addStyleClass('font-small');
        $root->add($widget);

        $lines = $renderer->render($root, 80, 24);

        // Should render as FIGlet
        $this->assertCount(4, $lines);
    }

    public function testInstanceStyleFontOverridesStylesheet()
    {
        $stylesheet = new StyleSheet([
            TextWidget::class => new Style(font: 'big'),
        ]);
        $renderer = new Renderer($stylesheet);

        $root = new ContainerWidget();
        $widget = new TextWidget('Hi');
        // Instance style overrides stylesheet rule
        $widget->setStyle(new Style(font: 'small'));
        $root->add($widget);

        $lines = $renderer->render($root, 80, 24);

        // Render with small font directly for comparison
        $renderer2 = new Renderer(new StyleSheet([
            TextWidget::class => new Style(font: 'small'),
        ]));
        $root2 = new ContainerWidget();
        $root2->add(new TextWidget('Hi'));
        $linesSmall = $renderer2->render($root2, 80, 24);

        // Both should use 'small' font, so same line count
        $this->assertCount(\count($linesSmall), $lines);
    }

    public function testNoFontRendersNormalTextEvenWithStylesheet()
    {
        // Stylesheet has no font rule; should render as normal text
        $stylesheet = new StyleSheet([
            TextWidget::class => new Style()->withColor('red'),
        ]);
        $renderer = new Renderer($stylesheet);

        $root = new ContainerWidget();
        $widget = new TextWidget('Hi');
        $root->add($widget);

        $lines = $renderer->render($root, 80, 24);

        // Normal text: single line
        $this->assertCount(1, $lines);
    }

    public function testCustomRegisteredFont()
    {
        $fontsDir = \dirname(__DIR__, 2).'/Widget/Figlet/fonts';

        $fontRegistry = new FontRegistry();
        $fontRegistry->register('my-custom', $fontsDir.'/mini.flf');

        $stylesheet = new StyleSheet([
            '.banner' => new Style(font: 'my-custom'),
        ]);
        $renderer = new Renderer($stylesheet, $fontRegistry);

        $root = new ContainerWidget();
        $widget = new TextWidget('Hi');
        $widget->addStyleClass('banner');
        $root->add($widget);

        $lines = $renderer->render($root, 80, 24);

        // Should render as FIGlet using the registered custom font
        $this->assertCount(3, $lines);
    }

    public function testCustomFontViaTailwindUtility()
    {
        $fontsDir = \dirname(__DIR__, 2).'/Widget/Figlet/fonts';

        $fontRegistry = new FontRegistry();
        $fontRegistry->register('my-title', $fontsDir.'/slant.flf');

        $stylesheet = new TailwindStylesheet();
        $renderer = new Renderer($stylesheet, $fontRegistry);

        $root = new ContainerWidget();
        $widget = new TextWidget('Hi');
        $widget->addStyleClass('font-my-title');
        $root->add($widget);

        $lines = $renderer->render($root, 80, 24);

        // Should render as FIGlet
        $this->assertCount(5, $lines);
    }

    /**
     * @return string[]
     */
    private function renderWidget(TextWidget $widget, int $columns, int $rows): array
    {
        $renderer = new Renderer();

        return $renderer->renderWidget($widget, new RenderContext($columns, $rows));
    }

    /**
     * @return string[]
     */
    private function renderWidgetWithFont(TextWidget $widget, string $font, int $columns, int $rows): array
    {
        $stylesheet = new StyleSheet([
            TextWidget::class => new Style(font: $font),
        ]);
        $renderer = new Renderer($stylesheet);

        $root = new ContainerWidget();
        $root->add($widget);

        return $renderer->render($root, $columns, $rows);
    }
}
