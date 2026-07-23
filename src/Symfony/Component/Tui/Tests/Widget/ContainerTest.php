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
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class ContainerTest extends TestCase
{
    public function testRender()
    {
        $container = new ContainerWidget();
        $container->add(new TextWidget('First'));
        $container->add(new TextWidget('Second'));

        $lines = $this->renderContainer($container);

        $content = implode("\n", $lines);
        $this->assertStringContainsString('First', $content);
        $this->assertStringContainsString('Second', $content);
    }

    public function testRenderEmpty()
    {
        $container = new ContainerWidget();
        $lines = $this->renderContainer($container);

        $this->assertSame([], $lines);
    }

    public function testRenderEmptyWithBorder()
    {
        $container = new ContainerWidget();
        $container->setStyle(new Style(border: Border::all(1)));

        $lines = $this->renderContainer($container);

        $stripped = array_map(static fn ($l) => AnsiUtils::stripAnsiCodes($l), $lines);
        // Should have at least a top and bottom border line
        $this->assertGreaterThanOrEqual(2, \count($stripped));
        $this->assertStringContainsString('┌', $stripped[0]);
        $this->assertStringContainsString('└', $stripped[\count($stripped) - 1]);
    }

    public function testRenderEmptyWithPadding()
    {
        $container = new ContainerWidget();
        $container->setStyle(new Style(padding: new Padding(1, 0, 1, 0)));

        $lines = $this->renderContainer($container);

        // An empty container with vertical padding should still render padding lines
        $this->assertCount(2, $lines);
    }

    public function testRenderAllChildrenHiddenWithBorder()
    {
        $container = new ContainerWidget();
        $container->setStyle(new Style(border: Border::all(1)));
        $container->add(new TextWidget('Hidden')->setStyle(new Style(hidden: true)));

        $lines = $this->renderContainer($container);

        $stripped = array_map(static fn ($l) => AnsiUtils::stripAnsiCodes($l), $lines);
        $this->assertStringContainsString('┌', $stripped[0]);
        $this->assertStringContainsString('└', $stripped[\count($stripped) - 1]);
    }

    public function testRenderWithGap()
    {
        $container = new ContainerWidget();
        $container->setStyle(new Style(gap: 2));
        $container->add(new TextWidget('Top'));
        $container->add(new TextWidget('Bottom'));

        $lines = $this->renderContainer($container);

        // Should have at least 4 lines (Top + 2 gap + Bottom)
        $this->assertGreaterThanOrEqual(4, \count($lines));
    }

    public function testNestedContainers()
    {
        $outer = new ContainerWidget();
        $inner = new ContainerWidget();

        $inner->add(new TextWidget('Nested'));
        $outer->add(new TextWidget('Outer'));
        $outer->add($inner);

        $lines = $this->renderContainer($outer);
        $content = implode("\n", $lines);

        $this->assertStringContainsString('Outer', $content);
        $this->assertStringContainsString('Nested', $content);
    }

    public function testGapBetweenChildren()
    {
        $container = new ContainerWidget()->setStyle(new Style(gap: 2));
        $container->add(new TextWidget('First'));
        $container->add(new TextWidget('Second'));
        $container->add(new TextWidget('Third'));

        $lines = $this->renderContainer($container);

        // With gap=2, we expect: First, 2 empty lines, Second, 2 empty lines, Third
        // That's 3 text lines + 4 gap lines = 7 total (assuming each Text renders 1 line)
        $this->assertCount(7, $lines);
        $this->assertStringContainsString('First', $lines[0]);
        $this->assertSame('', trim($lines[1]));
        $this->assertSame('', trim($lines[2]));
        $this->assertStringContainsString('Second', $lines[3]);
        $this->assertSame('', trim($lines[4]));
        $this->assertSame('', trim($lines[5]));
        $this->assertStringContainsString('Third', $lines[6]);
    }

    public function testGapWithSingleChild()
    {
        $container = new ContainerWidget()->setStyle(new Style(gap: 2));
        $container->add(new TextWidget('Only'));

        $lines = $this->renderContainer($container);

        // With single child, no gap should be added
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Only', $lines[0]);
    }

    public function testGapWithNoChildren()
    {
        $container = new ContainerWidget()->setStyle(new Style(gap: 2));
        $lines = $this->renderContainer($container);

        $this->assertSame([], $lines);
    }

    public function testHorizontalDirectionRendersSingleLine()
    {
        $container = new ContainerWidget()->setStyle(new Style(direction: Direction::Horizontal));
        $container->add(new TextWidget('Left'));
        $container->add(new TextWidget('Right'));

        $lines = $this->renderContainer($container);

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Left', $lines[0]);
        $this->assertStringContainsString('Right', $lines[0]);
    }

    public function testHorizontalDirectionWithGap()
    {
        $container = new ContainerWidget()->setStyle(new Style(direction: Direction::Horizontal, gap: 2));
        $container->add(new TextWidget('Left'));
        $container->add(new TextWidget('Right'));

        $lines = $this->renderContainer($container);

        $this->assertCount(1, $lines);
        $line = AnsiUtils::stripAnsiCodes($lines[0]);
        $this->assertMatchesRegularExpression('/Left\s{2,}Right/', $line);
    }

    public function testStyleWithPadding()
    {
        // [1, 2] = top/bottom: 1, left/right: 2
        $container = new ContainerWidget()->setStyle(Style::padding([1, 2]));
        $container->add(new TextWidget('Hello'));

        $lines = $this->renderContainer($container);

        // Should have: 1 top padding + 1 content + 1 bottom padding = 3 lines
        $this->assertCount(3, $lines);
        // Content line should contain 'Hello'
        $this->assertStringContainsString('Hello', $lines[1]);
    }

    public function testStyleWithBackground()
    {
        $container = new ContainerWidget()->setStyle(Style::padding([0, 1])->withBackground('blue'));
        $container->add(new TextWidget('Hi'));

        $lines = $this->renderContainer($container, 20);

        // Should contain background color code
        $this->assertStringContainsString("\x1b[44m", $lines[0]);
    }

    public function testStyleWithGap()
    {
        $container = new ContainerWidget()->setStyle(Style::padding([0])->withGap(1));
        $container->add(new TextWidget('First'));
        $container->add(new TextWidget('Second'));

        $lines = $this->renderContainer($container);

        // Should have: First, 1 gap line, Second = 3 lines
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('First', $lines[0]);
        $this->assertSame('', trim($lines[1]));
        $this->assertStringContainsString('Second', $lines[2]);
    }

    public function testRenderWithinWidth()
    {
        // [1, 2] = top/bottom: 1, left/right: 2
        $container = new ContainerWidget()->setStyle(Style::padding([1, 2]));
        $container->add(new TextWidget('This is some longer text that should wrap'));

        $width = 30;
        $lines = $this->renderContainer($container, $width);

        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d', $i, $lineWidth, $width),
            );
        }
    }

    public function testGapViaStylesheet()
    {
        $container = new ContainerWidget()->addStyleClass('spaced');
        $container->add(new TextWidget('First'));
        $container->add(new TextWidget('Second'));

        $root = new ContainerWidget();
        $root->expandVertically(true);
        $root->add($container);

        $stylesheet = new StyleSheet([
            '.spaced' => new Style(gap: 2),
        ]);
        $renderer = new Renderer($stylesheet);
        $lines = $renderer->render($root, 40, 24);

        // With gap=2: First, 2 empty lines, Second = 4 total
        $this->assertCount(4, $lines);
        $this->assertStringContainsString('First', $lines[0]);
        $this->assertStringContainsString('Second', $lines[3]);
    }

    public function testDirectionViaStylesheet()
    {
        $container = new ContainerWidget()->addStyleClass('horizontal');
        $container->add(new TextWidget('Left'));
        $container->add(new TextWidget('Right'));

        $root = new ContainerWidget();
        $root->expandVertically(true);
        $root->add($container);

        $stylesheet = new StyleSheet([
            '.horizontal' => new Style(direction: Direction::Horizontal),
        ]);
        $renderer = new Renderer($stylesheet);
        $lines = $renderer->render($root, 40, 24);

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Left', $lines[0]);
        $this->assertStringContainsString('Right', $lines[0]);
    }

    public function testResponsiveDirectionViaBreakpoint()
    {
        $container = new ContainerWidget()->addStyleClass('panes');
        $container->add(new TextWidget('A'));
        $container->add(new TextWidget('B'));

        $root = new ContainerWidget();
        $root->expandVertically(true);
        $root->add($container);

        $stylesheet = new StyleSheet([
            '.panes' => new Style(direction: Direction::Vertical),
        ]);
        $stylesheet->addBreakpoint(100, '.panes', new Style(direction: Direction::Horizontal));

        $renderer = new Renderer($stylesheet);

        // Narrow terminal: vertical (2 lines)
        $lines = $renderer->render($root, 60, 24);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('A', $lines[0]);
        $this->assertStringContainsString('B', $lines[1]);

        // Wide terminal: horizontal (1 line)
        $lines = $renderer->render($root, 120, 24);
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('A', $lines[0]);
        $this->assertStringContainsString('B', $lines[0]);
    }

    /**
     * @return iterable<string, array{?StyleSheet}>
     */
    public static function hiddenWidgetProvider(): iterable
    {
        yield 'via instance style' => [null];
        yield 'via stylesheet' => [new StyleSheet(['.hide-me' => new Style(hidden: true)])];
    }

    #[DataProvider('hiddenWidgetProvider')]
    public function testHiddenWidgetIsNotRendered(?StyleSheet $stylesheet)
    {
        $container = new ContainerWidget();
        $container->add(new TextWidget('Visible'));
        $hiddenWidget = new TextWidget('Hidden');
        if (null !== $stylesheet) {
            $hiddenWidget->addStyleClass('hide-me');
        } else {
            $hiddenWidget->setStyle(new Style(hidden: true));
        }
        $container->add($hiddenWidget);
        $container->add(new TextWidget('Also Visible'));

        $root = new ContainerWidget();
        $root->expandVertically(true);
        $root->add($container);

        $renderer = new Renderer($stylesheet);
        $lines = $renderer->render($root, 40, 24);

        $text = implode("\n", array_map(static fn ($l) => AnsiUtils::stripAnsiCodes($l), $lines));
        $this->assertStringContainsString('Visible', $text);
        $this->assertStringNotContainsString('Hidden', $text);
        $this->assertStringContainsString('Also Visible', $text);
    }

    public function testHiddenWidgetTakesNoVerticalSpace()
    {
        $container = new ContainerWidget()->setStyle(new Style(gap: 1));
        $container->add(new TextWidget('A'));
        $container->add(new TextWidget('B')->setStyle(new Style(hidden: true)));
        $container->add(new TextWidget('C'));

        $root = new ContainerWidget();
        $root->expandVertically(true);
        $root->add($container);

        $renderer = new Renderer();
        $lines = $renderer->render($root, 40, 24);

        // A + gap + C = 3 lines (no gap for the hidden widget)
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('A', AnsiUtils::stripAnsiCodes($lines[0]));
        $this->assertStringContainsString('C', AnsiUtils::stripAnsiCodes($lines[2]));
    }

    public function testHiddenWidgetTakesNoHorizontalSpace()
    {
        $container = new ContainerWidget()->setStyle(new Style(direction: Direction::Horizontal));
        $container->add(new TextWidget('Left'));
        $container->add(new TextWidget('Middle')->setStyle(new Style(hidden: true)));
        $container->add(new TextWidget('Right'));

        $root = new ContainerWidget();
        $root->expandVertically(true);
        $root->add($container);

        $renderer = new Renderer();
        $lines = $renderer->render($root, 40, 24);

        // Hidden widget should not take column space: the visible widgets should share the full width
        $this->assertCount(1, $lines);
        $stripped = AnsiUtils::stripAnsiCodes($lines[0]);
        $this->assertStringContainsString('Left', $stripped);
        $this->assertStringNotContainsString('Middle', $stripped);
        $this->assertStringContainsString('Right', $stripped);

        // Each visible child gets 20 columns (40/2)
        $leftPos = mb_strpos($stripped, 'Left');
        $rightPos = mb_strpos($stripped, 'Right');
        $this->assertSame(0, $leftPos);
        $this->assertSame(20, $rightPos);
    }

    public function testHiddenWidgetViaBreakpoint()
    {
        $container = new ContainerWidget();
        $container->add(new TextWidget('Always'));
        $container->add(new TextWidget('Mobile Only')->addStyleClass('mobile-hint'));

        $root = new ContainerWidget();
        $root->expandVertically(true);
        $root->add($container);

        $stylesheet = new StyleSheet();
        // Hide the hint on wide terminals
        $stylesheet->addBreakpoint(100, '.mobile-hint', new Style(hidden: true));

        $renderer = new Renderer($stylesheet);

        // Narrow: both visible
        $lines = $renderer->render($root, 60, 24);
        $text = implode("\n", array_map(static fn ($l) => AnsiUtils::stripAnsiCodes($l), $lines));
        $this->assertStringContainsString('Always', $text);
        $this->assertStringContainsString('Mobile Only', $text);

        // Wide: hint hidden
        $lines = $renderer->render($root, 120, 24);
        $text = implode("\n", array_map(static fn ($l) => AnsiUtils::stripAnsiCodes($l), $lines));
        $this->assertStringContainsString('Always', $text);
        $this->assertStringNotContainsString('Mobile Only', $text);
    }

    public function testHiddenFalseOverridesInheritedHidden()
    {
        $container = new ContainerWidget();
        $container->add(new TextWidget('Visible')->addStyleClass('item')->setStyle(new Style(hidden: false)));

        $root = new ContainerWidget();
        $root->expandVertically(true);
        $root->add($container);

        $stylesheet = new StyleSheet([
            '.item' => new Style(hidden: true),
        ]);
        $renderer = new Renderer($stylesheet);
        $lines = $renderer->render($root, 40, 24);

        $text = implode("\n", array_map(static fn ($l) => AnsiUtils::stripAnsiCodes($l), $lines));
        $this->assertStringContainsString('Visible', $text);
    }

    public function testHiddenContainerHidesAllChildren()
    {
        $inner = new ContainerWidget();
        $inner->add(new TextWidget('Child A'));
        $inner->add(new TextWidget('Child B'));
        $inner->setStyle(new Style(hidden: true));

        $container = new ContainerWidget();
        $container->add(new TextWidget('Before'));
        $container->add($inner);
        $container->add(new TextWidget('After'));

        $root = new ContainerWidget();
        $root->expandVertically(true);
        $root->add($container);

        $renderer = new Renderer();
        $lines = $renderer->render($root, 40, 24);

        $text = implode("\n", array_map(static fn ($l) => AnsiUtils::stripAnsiCodes($l), $lines));
        $this->assertStringContainsString('Before', $text);
        $this->assertStringNotContainsString('Child A', $text);
        $this->assertStringNotContainsString('Child B', $text);
        $this->assertStringContainsString('After', $text);
    }

    public function testBeforeRenderIsCalledByRenderer()
    {
        $text = new TextWidget('initial');
        $container = new BeforeRenderTestContainer();
        $container->targetText = $text;
        $container->add($text);

        $lines = $this->renderContainer($container);

        $content = implode("\n", $lines);
        // beforeRender() should have updated the text before rendering
        $this->assertStringContainsString('updated by beforeRender', $content);
        $this->assertStringNotContainsString('initial', $content);
    }

    public function testChildrenStylesAppliedByRenderer()
    {
        // Child has padding: the Renderer should apply it as chrome
        $child = new TextWidget('Padded');
        $child->setStyle(new Style(padding: new Padding(0, 0, 0, 4)));

        $container = new ContainerWidget();
        $container->add($child);

        $lines = $this->renderContainer($container);

        // The text should be indented by 4 characters (left padding)
        $content = implode("\n", array_map(static fn ($l) => AnsiUtils::stripAnsiCodes($l), $lines));
        $this->assertStringContainsString('    Padded', $content);
    }

    public function testBorderOuterStyleInheritsFromGrandparent()
    {
        // Grandparent has a green background; intermediate container has no
        // background (only gap). The border of the leaf widget should use
        // the green background from the grandparent as the outer style,
        // so border characters reset to green (42) rather than default (49).
        $innerStyle = new Style()
            ->withBackground(Color::from('black'))
            ->withBorder([1], BorderPattern::fromName(BorderPattern::NORMAL))
        ;

        $child = new TextWidget('Hello');
        $child->setStyle($innerStyle);

        // Intermediate container with gap only (no color/background)
        $middle = new ContainerWidget();
        $middle->setStyle(new Style(gap: 1));
        $middle->add($child);

        // Outer container with green background
        $outer = new ContainerWidget();
        $outer->setStyle(new Style()->withBackground(Color::from('green')));
        $outer->add($middle);

        $root = new ContainerWidget();
        $root->expandVertically(true);
        $root->add($outer);

        $renderer = new Renderer();
        $lines = $renderer->render($root, 30, 10);

        $borderLine = $lines[0];

        // After the border corner "┌" with black bg (\e[40m), the border should
        // reset to green bg (\e[42m); not default bg (\e[49m); because the
        // outer style inherits the green background from the grandparent
        $this->assertStringContainsString("\x1b[40m┌\x1b[39m\x1b[42m", $borderLine, 'Border should reset to green (grandparent) background, not default');
        $this->assertStringNotContainsString("\x1b[40m┌\x1b[39m\x1b[49m", $borderLine, 'Border should not reset to default background');
    }

    public function testContainerWithGapAndBeforeRender()
    {
        $text1 = new TextWidget('First');
        $text2 = new TextWidget('');
        $container = new BeforeRenderTestContainer();
        $container->targetText = $text2;
        $container->setStyle(new Style(gap: 1));
        $container->add($text1);
        $container->add($text2);

        $lines = $this->renderContainer($container, 40);

        $stripped = array_map(static fn ($l) => AnsiUtils::stripAnsiCodes($l), $lines);

        // First and "updated" text should both be present with a gap between them
        $firstIdx = null;
        $updatedIdx = null;
        for ($i = 0; $i < \count($stripped); ++$i) {
            if (str_contains($stripped[$i], 'First')) {
                $firstIdx = $i;
            }
            if (str_contains($stripped[$i], 'updated by beforeRender')) {
                $updatedIdx = $i;
            }
        }

        // Gap of 1 means there should be exactly 1 line between them
        $this->assertSame(2, $updatedIdx - $firstIdx, 'Gap of 1 should produce 1 blank line between children');
    }

    /**
     * @return string[]
     */
    private function renderContainer(ContainerWidget $container, int $columns = 40, int $rows = 24): array
    {
        $root = new ContainerWidget();
        $root->expandVertically(true);
        $root->add($container);
        $renderer = new Renderer();

        return $renderer->render($root, $columns, $rows);
    }
}

/**
 * Test helper: a ContainerWidget subclass that updates child state in beforeRender().
 *
 * @internal
 */
class BeforeRenderTestContainer extends ContainerWidget
{
    public ?TextWidget $targetText = null;

    public function beforeRender(): void
    {
        if (null !== $this->targetText) {
            $this->targetText->setText('updated by beforeRender');
        }
    }
}
