<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\TailwindStylesheet;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class AlignTest extends TestCase
{
    public function testAlignCenter()
    {
        $renderer = new Renderer(new StyleSheet([
            '.parent' => new Style(align: Align::Center),
            '.child' => new Style(maxColumns: 10),
        ]));

        $root = new ContainerWidget();
        $root->addStyleClass('parent');
        $child = new TextWidget('Hello');
        $child->addStyleClass('child');
        $root->add($child);

        $lines = $renderer->render($root, 30, 5);

        $this->assertCount(1, $lines);
        $visible = AnsiUtils::stripAnsiCodes($lines[0]);
        // "Hello" (5 chars) centered in 30-col parent: offset = floor((30-5)/2) = 12
        $this->assertSame(str_repeat(' ', 12).'Hello', $visible);
    }

    public function testAlignRight()
    {
        $renderer = new Renderer(new StyleSheet([
            '.parent' => new Style(align: Align::Right),
            '.child' => new Style(maxColumns: 10),
        ]));

        $root = new ContainerWidget();
        $root->addStyleClass('parent');
        $child = new TextWidget('Hello');
        $child->addStyleClass('child');
        $root->add($child);

        $lines = $renderer->render($root, 30, 5);

        $this->assertCount(1, $lines);
        $visible = AnsiUtils::stripAnsiCodes($lines[0]);
        // "Hello" (5 chars) right-aligned in 30-col parent: offset = 30-5 = 25
        $this->assertSame(str_repeat(' ', 25).'Hello', $visible);
    }

    public function testAlignCenterWithMultipleChildren()
    {
        $renderer = new Renderer(new StyleSheet([
            '.parent' => new Style(align: Align::Center),
            '.child' => new Style(maxColumns: 10),
        ]));

        $root = new ContainerWidget();
        $root->addStyleClass('parent');
        $child1 = new TextWidget('AB');
        $child1->addStyleClass('child');
        $child2 = new TextWidget('CDEF');
        $child2->addStyleClass('child');
        $root->add($child1);
        $root->add($child2);

        $lines = $renderer->render($root, 30, 5);

        $this->assertCount(2, $lines);
        $line1 = AnsiUtils::stripAnsiCodes($lines[0]);
        $line2 = AnsiUtils::stripAnsiCodes($lines[1]);
        // Widest line is "CDEF" (4 chars). Offset = floor((30-4)/2) = 13
        // All lines shifted uniformly by 13
        $this->assertSame(str_repeat(' ', 13).'AB', $line1);
        $this->assertSame(str_repeat(' ', 13).'CDEF', $line2);
    }

    public function testAlignCenterNoEffectWhenContentFillsWidth()
    {
        $renderer = new Renderer(new StyleSheet([
            '.parent' => new Style(align: Align::Center),
        ]));

        $root = new ContainerWidget();
        $root->addStyleClass('parent');
        // Text fills the full 10-column width
        $root->add(new TextWidget('0123456789'));

        $lines = $renderer->render($root, 10, 5);

        $this->assertCount(1, $lines);
        $visible = AnsiUtils::stripAnsiCodes($lines[0]);
        $this->assertSame('0123456789', $visible);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function alignViaTailwindProvider(): iterable
    {
        yield 'center' => ['align-center', str_repeat(' ', 14).'Hi'];
        yield 'right' => ['align-right', str_repeat(' ', 28).'Hi'];
        yield 'left' => ['align-left', 'Hi'];
    }

    #[DataProvider('alignViaTailwindProvider')]
    public function testAlignViaTailwind(string $class, string $expectedVisible)
    {
        $renderer = new Renderer(new TailwindStylesheet());

        $root = new ContainerWidget();
        $root->addStyleClass($class);
        $child = new TextWidget('Hi');
        $child->setStyle(new Style(maxColumns: 10));
        $root->add($child);

        $lines = $renderer->render($root, 30, 5);

        $this->assertCount(1, $lines);
        $visible = AnsiUtils::stripAnsiCodes($lines[0]);
        $this->assertSame($expectedVisible, $visible);
    }

    // --- Vertical align ---

    public function testVerticalAlignCenter()
    {
        $renderer = new Renderer(new StyleSheet([
            '.parent' => new Style(verticalAlign: VerticalAlign::Center),
        ]));

        $root = new ContainerWidget();
        $root->addStyleClass('parent');
        $root->add(new TextWidget('Hello'));

        $lines = $renderer->render($root, 10, 10);

        // 1 content line + centering: floor((10-1)/2) = 4 empty lines top, 5 bottom
        $this->assertCount(10, $lines);
        for ($i = 0; $i < 4; ++$i) {
            $this->assertSame('', $lines[$i], "Line $i should be empty (top padding)");
        }
        $this->assertStringContainsString('Hello', $lines[4]);
        for ($i = 5; $i < 10; ++$i) {
            $this->assertSame('', $lines[$i], "Line $i should be empty (bottom padding)");
        }
    }

    public function testVerticalAlignTop()
    {
        $renderer = new Renderer(new StyleSheet([
            '.parent' => new Style(verticalAlign: VerticalAlign::Top),
        ]));

        $root = new ContainerWidget();
        $root->addStyleClass('parent');
        $root->add(new TextWidget('Hello'));

        $lines = $renderer->render($root, 10, 10);

        // Content at top, all remaining rows are empty at bottom
        $this->assertCount(10, $lines);
        $this->assertStringContainsString('Hello', $lines[0]);
        for ($i = 1; $i < 10; ++$i) {
            $this->assertSame('', $lines[$i], "Line $i should be empty (bottom padding)");
        }
    }

    public function testVerticalAlignCenterViaTailwind()
    {
        $renderer = new Renderer(new TailwindStylesheet());

        $root = new ContainerWidget();
        $root->addStyleClass('valign-center');
        $root->add(new TextWidget('Hi'));

        $lines = $renderer->render($root, 10, 10);

        // 1 content line centered in 10 rows: 4 empty top, content, 5 empty bottom
        $this->assertCount(10, $lines);
        for ($i = 0; $i < 4; ++$i) {
            $this->assertSame('', $lines[$i], "Line $i should be empty (top padding)");
        }
        $this->assertStringContainsString('Hi', $lines[4]);
    }

    public function testVerticalAlignCenterWithMultipleChildren()
    {
        $renderer = new Renderer(new StyleSheet([
            '.parent' => new Style(verticalAlign: VerticalAlign::Center),
        ]));

        $root = new ContainerWidget();
        $root->addStyleClass('parent');
        $root->add(new TextWidget('Line1'));
        $root->add(new TextWidget('Line2'));

        $lines = $renderer->render($root, 10, 10);

        // 2 content lines centered in 10 rows: floor((10-2)/2) = 4 empty top
        $this->assertCount(10, $lines);
        for ($i = 0; $i < 4; ++$i) {
            $this->assertSame('', $lines[$i], "Line $i should be empty (top padding)");
        }
        $this->assertStringContainsString('Line1', $lines[4]);
        $this->assertStringContainsString('Line2', $lines[5]);
    }

    public function testBothAlignAndVerticalAlignCenter()
    {
        $renderer = new Renderer(new StyleSheet([
            '.parent' => new Style(align: Align::Center, verticalAlign: VerticalAlign::Center),
            '.child' => new Style(maxColumns: 10),
        ]));

        $root = new ContainerWidget();
        $root->addStyleClass('parent');
        $child = new TextWidget('Hi');
        $child->addStyleClass('child');
        $root->add($child);

        $lines = $renderer->render($root, 30, 10);

        // 1 content line centered both ways
        // Vertical: floor((10-1)/2) = 4 empty top
        // Horizontal: "Hi" (2 chars), offset = floor((30-2)/2) = 14
        $this->assertCount(10, $lines);
        $visible = AnsiUtils::stripAnsiCodes($lines[4]);
        $this->assertSame(str_repeat(' ', 14).'Hi', $visible);

        // Content should appear only on line 4
        for ($i = 0; $i < 10; ++$i) {
            if (4 === $i) {
                continue;
            }
            $this->assertStringNotContainsString('Hi', AnsiUtils::stripAnsiCodes($lines[$i]));
        }
    }
}
