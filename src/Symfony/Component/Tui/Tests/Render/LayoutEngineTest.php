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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class LayoutEngineTest extends TestCase
{
    public function testLayoutVerticalSingleChild()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->add(new TextWidget('Hello'));

        $result = $renderer->render($root, 80, 24);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Hello', $result[0]);
    }

    public function testLayoutVerticalMultipleChildren()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->add(new TextWidget('First'));
        $root->add(new TextWidget('Second'));

        $result = $renderer->render($root, 80, 24);

        $this->assertCount(2, $result);
        $this->assertStringContainsString('First', $result[0]);
        $this->assertStringContainsString('Second', $result[1]);
    }

    public function testLayoutVerticalWithGap()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(gap: 2));
        $root->add(new TextWidget('First'));
        $root->add(new TextWidget('Second'));

        $result = $renderer->render($root, 80, 24);

        $this->assertCount(4, $result);
        $this->assertStringContainsString('First', $result[0]);
        $this->assertSame(str_repeat(' ', 80), $result[1]);
        $this->assertSame(str_repeat(' ', 80), $result[2]);
        $this->assertStringContainsString('Second', $result[3]);
    }

    public function testLayoutHorizontalMultipleChildren()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));
        $root->add(new TextWidget('Left'));
        $root->add(new TextWidget('Right'));

        $result = $renderer->render($root, 80, 24);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Left', $result[0]);
        $this->assertStringContainsString('Right', $result[0]);
    }

    public function testLayoutHorizontalWithGap()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal, gap: 4));
        $root->add(new TextWidget('A'));
        $root->add(new TextWidget('B'));

        $result = $renderer->render($root, 80, 24);

        $this->assertCount(1, $result);
        // Gap of 4 spaces between the two columns
        $this->assertMatchesRegularExpression('/A\s{4,}B/', $result[0]);
    }

    public function testLayoutVerticalFillDistributesRemainderRows()
    {
        $renderer = new Renderer();

        // 1 non-fill child of 1 row + 3 fill children, total 10 rows
        // remaining = 10 - 1 = 9, base = intdiv(9, 3) = 3, extra = 9 % 3 = 0
        // All fill children get 3 rows => 1 + 3*3 = 10 rows total
        $root = new ContainerWidget();
        $root->add(new TextWidget('Header'));
        $fill1 = new ContainerWidget()->expandVertically(true);
        $fill1->add(new TextWidget('A'));
        $fill2 = new ContainerWidget()->expandVertically(true);
        $fill2->add(new TextWidget('B'));
        $fill3 = new ContainerWidget()->expandVertically(true);
        $fill3->add(new TextWidget('C'));
        $root->add($fill1);
        $root->add($fill2);
        $root->add($fill3);

        // With 10 rows: 1 for header, 9 remaining for 3 fill children (no remainder)
        $result = $renderer->render($root, 20, 10);
        $this->assertCount(10, $result);

        // With 11 rows: 1 for header, 10 remaining for 3 fill children
        // base=3, extra=1 => first fill gets 4, others get 3 => 1+4+3+3 = 11
        $result = $renderer->render($root, 20, 11);
        $this->assertCount(11, $result, 'Remainder rows should be distributed to fill children, not lost');

        // With 12 rows: 1 for header, 11 remaining for 3 fill children
        // base=3, extra=2 => first two fills get 4, last gets 3 => 1+4+4+3 = 12
        $result = $renderer->render($root, 20, 12);
        $this->assertCount(12, $result, 'All remainder rows should be distributed across fill children');
    }

    public function testHorizontalFlexProportionalDistribution()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));

        // 1:2:1 ratio across 80 columns → 20, 40, 20
        $left = new TextWidget('L');
        $left->setStyle(new Style(flex: 1));
        $center = new TextWidget('C');
        $center->setStyle(new Style(flex: 2));
        $right = new TextWidget('R');
        $right->setStyle(new Style(flex: 1));

        $root->add($left);
        $root->add($center);
        $root->add($right);

        $result = $renderer->render($root, 80, 24);

        $this->assertCount(1, $result);
        // L starts at position 0, C at position 20, R at position 60
        $line = $result[0];
        $this->assertSame('L', $line[0]);
        $this->assertSame('C', $line[20]);
        $this->assertSame('R', $line[60]);
    }

    public function testHorizontalFlexZeroUsesIntrinsicWidth()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));

        // Fixed-width sidebar + flexible content
        $sidebar = new TextWidget('Menu');
        $sidebar->setStyle(new Style(flex: 0));
        $content = new TextWidget('Content');
        $content->setStyle(new Style(flex: 1));

        $root->add($sidebar);
        $root->add($content);

        $result = $renderer->render($root, 80, 24);

        $this->assertCount(1, $result);
        // "Menu" is 4 chars; sidebar gets intrinsic width (4)
        // Content gets 80 - 4 = 76
        $line = $result[0];
        $this->assertSame('M', $line[0]);
        $this->assertSame('C', $line[4]);
    }

    public function testHorizontalFlexZeroWithMaxColumns()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));

        // Text wider than maxColumns: intrinsic width should be capped by maxColumns
        $sidebar = new TextWidget('ABCDEFGHIJKLMNO');
        $sidebar->setStyle(new Style(flex: 0, maxColumns: 10));
        $content = new TextWidget('Main');
        $content->setStyle(new Style(flex: 1));

        $root->add($sidebar);
        $root->add($content);

        $result = $renderer->render($root, 80, 24);

        // Text wraps within the 10-column constraint; content starts at column 10
        $this->assertSame('M', $result[0][10]);
    }

    public function testHorizontalFlexMixedWithNullTreatsNullAsFlex1()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));

        // One child with explicit flex, one without (null)
        $left = new TextWidget('L');
        $left->setStyle(new Style(flex: 1));
        $right = new TextWidget('R');
        // No flex set on right; should be treated as flex: 1

        $root->add($left);
        $root->add($right);

        $result = $renderer->render($root, 80, 24);

        $this->assertCount(1, $result);
        // Both get equal space: 40 each
        $line = $result[0];
        $this->assertSame('L', $line[0]);
        $this->assertSame('R', $line[40]);
    }

    public function testHorizontalNoFlexSetEqualDistribution()
    {
        // Backward compatibility: no flex set anywhere → equal split
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));

        $root->add(new TextWidget('A'));
        $root->add(new TextWidget('B'));
        $root->add(new TextWidget('C'));

        $result = $renderer->render($root, 90, 24);

        $this->assertCount(1, $result);
        // 90 / 3 = 30 each
        $line = $result[0];
        $this->assertSame('A', $line[0]);
        $this->assertSame('B', $line[30]);
        $this->assertSame('C', $line[60]);
    }

    public function testHorizontalFlexWithGap()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal, gap: 2));

        $left = new TextWidget('L');
        $left->setStyle(new Style(flex: 1));
        $right = new TextWidget('R');
        $right->setStyle(new Style(flex: 1));

        $root->add($left);
        $root->add($right);

        $result = $renderer->render($root, 82, 24);

        $this->assertCount(1, $result);
        // Available = 82 - 2 gap = 80, each gets 40
        // Right starts at 40 + 2 gap = 42
        $line = $result[0];
        $this->assertSame('L', $line[0]);
        $this->assertSame('R', $line[42]);
    }

    public function testHorizontalFlexZeroWithPadding()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));

        // flex:0 with padding: intrinsic width = padding-left + content + padding-right
        $sidebar = new TextWidget('Hi');
        $sidebar->setStyle(new Style(flex: 0, padding: new Padding(0, 2, 0, 2)));
        $content = new TextWidget('Main');
        $content->setStyle(new Style(flex: 1));

        $root->add($sidebar);
        $root->add($content);

        $result = $renderer->render($root, 80, 24);

        $this->assertCount(1, $result);
        // "Hi" = 2 chars + 2 pad left + 2 pad right = 6 cols
        $this->assertSame('M', $result[0][6]);
    }

    public function testHorizontalFlexZeroWithBorder()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));

        // flex:0 with border: intrinsic width = border-left + content + border-right
        $sidebar = new TextWidget('Hi');
        $sidebar->setStyle(new Style(flex: 0, border: Border::from([1])));
        $content = new TextWidget('Main');
        $content->setStyle(new Style(flex: 1));

        $root->add($sidebar);
        $root->add($content);

        $result = $renderer->render($root, 80, 24);

        // "Hi" = 2 chars + 1 border left + 1 border right = 4 cols
        // Border chars are multibyte; use mb_substr for character-level position
        $stripped = AnsiUtils::stripAnsiCodes($result[0]);
        $this->assertSame('M', mb_substr($stripped, 4, 1));
    }

    public function testHorizontalFlexZeroWithBorderAndPadding()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));

        // flex:0 with border + padding
        $sidebar = new TextWidget('Hi');
        $sidebar->setStyle(new Style(flex: 0, border: Border::from([1]), padding: new Padding(0, 1, 0, 1)));
        $content = new TextWidget('Main');
        $content->setStyle(new Style(flex: 1));

        $root->add($sidebar);
        $root->add($content);

        $result = $renderer->render($root, 80, 24);

        // "Hi" = 2 + 1 border-left + 1 pad-left + 1 pad-right + 1 border-right = 6
        $stripped = AnsiUtils::stripAnsiCodes($result[0]);
        $this->assertSame('M', mb_substr($stripped, 6, 1));
    }

    public function testHorizontalFlexZeroAndProportionalWithGap()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal, gap: 2));

        $sidebar = new TextWidget('AB');
        $sidebar->setStyle(new Style(flex: 0));
        $main = new TextWidget('M');
        $main->setStyle(new Style(flex: 1));
        $aside = new TextWidget('X');
        $aside->setStyle(new Style(flex: 1));

        $root->add($sidebar);
        $root->add($main);
        $root->add($aside);

        $result = $renderer->render($root, 80, 24);

        $this->assertCount(1, $result);
        // Gap total = 2 * 2 = 4, available = 80 - 4 = 76
        // Sidebar intrinsic = 2, remaining = 76 - 2 = 74, each flex gets 37
        // Sidebar: 0..1, gap: 2..3, Main: 4..40, gap: 41..42, Aside: 43..79
        $line = $result[0];
        $this->assertSame('A', $line[0]);
        $this->assertSame('M', $line[4]);
        $this->assertSame('X', $line[43]);
    }
}
