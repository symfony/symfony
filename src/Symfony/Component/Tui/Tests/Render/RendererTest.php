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
use Symfony\Component\Tui\Exception\RenderException;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\Tui\Widget\TextWidget;

class RendererTest extends TestCase
{
    // ---------------------------------------------------------------
    // Render caching
    // ---------------------------------------------------------------

    public function testRenderCacheInvalidatedWhenWidgetChanges()
    {
        $renderer = new Renderer();
        $text = new TextWidget('Before');
        $root = new ContainerWidget();
        $root->add($text);

        $before = $renderer->render($root, 40, 10);
        $text->setText('After');
        $after = $renderer->render($root, 40, 10);

        $this->assertNotSame($before, $after);
        $this->assertStringContainsString('Before', $before[0]);
        $this->assertStringContainsString('After', $after[0]);
    }

    public function testRenderCacheInvalidatedWhenDimensionsChange()
    {
        $renderer = new Renderer();
        $fill = new ContainerWidget();
        $fill->expandVertically(true);
        $fill->add(new TextWidget('A'));

        $root = new ContainerWidget();
        $root->add($fill);

        $small = $renderer->render($root, 40, 5);
        $large = $renderer->render($root, 40, 10);

        // The fill child expands differently with different rows
        $this->assertNotSame($small, $large);
        $this->assertCount(5, $small);
        $this->assertCount(10, $large);
    }

    public function testRenderCacheInvalidatedWhenChildrenChange()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->add(new TextWidget('First'));

        $oneChild = $renderer->render($root, 40, 10);

        $root->add(new TextWidget('Second'));
        $twoChildren = $renderer->render($root, 40, 10);

        $this->assertCount(1, $oneChild);
        $this->assertCount(2, $twoChildren);
    }

    public function testRenderCacheInvalidatedWhenChildRemovedAfterFirstRender()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $first = new TextWidget('First');
        $second = new TextWidget('Second');
        $root->add($first);
        $root->add($second);

        $before = $renderer->render($root, 40, 10);

        $root->remove($second);
        $after = $renderer->render($root, 40, 10);

        $this->assertCount(2, $before);
        $this->assertCount(1, $after);
        $this->assertStringContainsString('First', $after[0]);
    }

    // ---------------------------------------------------------------
    // Fill-height distribution with multiple fill children
    // ---------------------------------------------------------------

    public function testFillHeightDistributesEvenlyAmongTwoFillChildren()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        $fill1 = new ContainerWidget();
        $fill1->expandVertically(true);
        $fill1->add(new TextWidget('A'));

        $fill2 = new ContainerWidget();
        $fill2->expandVertically(true);
        $fill2->add(new TextWidget('B'));

        $root->add($fill1);
        $root->add($fill2);

        // 10 rows / 2 fill children = 5 each
        $result = $renderer->render($root, 20, 10);
        $this->assertCount(10, $result);
    }

    public function testFillHeightWithGapBetweenFillChildren()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(gap: 2));

        $fill1 = new ContainerWidget();
        $fill1->expandVertically(true);
        $fill1->add(new TextWidget('A'));

        $fill2 = new ContainerWidget();
        $fill2->expandVertically(true);
        $fill2->add(new TextWidget('B'));

        $root->add($fill1);
        $root->add($fill2);

        // 10 rows - 2 gap = 8 remaining, 8 / 2 = 4 each, total = 4 + 2 + 4 = 10
        $result = $renderer->render($root, 20, 10);
        $this->assertCount(10, $result);
    }

    public function testFillHeightDistributesRemainderToFirstChildren()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        $fill1 = new ContainerWidget();
        $fill1->expandVertically(true);
        $fill1->add(new TextWidget('A'));

        $fill2 = new ContainerWidget();
        $fill2->expandVertically(true);
        $fill2->add(new TextWidget('B'));

        $root->add($fill1);
        $root->add($fill2);

        // 11 rows / 2 = 5 base + 1 extra => first gets 6, second gets 5 = 11
        $result = $renderer->render($root, 20, 11);
        $this->assertCount(11, $result, 'Remainder rows should be distributed');
    }

    // ---------------------------------------------------------------
    // Chrome application (borders + padding + background)
    // ---------------------------------------------------------------

    /**
     * @return iterable<string, array{Style, int}>
     */
    public static function chromeLineCountProvider(): iterable
    {
        yield 'padding only' => [new Style(padding: new Padding(1, 0, 1, 0)), 3];
        yield 'border only' => [new Style(border: Border::all(1, 'none')), 3];
        yield 'border and padding' => [new Style(padding: Padding::all(1), border: Border::all(1, 'none')), 5];
    }

    #[DataProvider('chromeLineCountProvider')]
    public function testChromeLineCount(Style $style, int $expectedLines)
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle($style);
        $root->add(new TextWidget('Content'));

        $result = $renderer->render($root, 40, 10);

        $this->assertCount($expectedLines, $result);
    }

    public function testChromeWithBackgroundAppliesAnsiCodes()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(background: 'red'));
        $root->add(new TextWidget('BG'));

        $result = $renderer->render($root, 20, 10);

        // Red background ANSI code (\e[41m) should be in the output
        $this->assertStringContainsString("\x1b[41m", $result[0]);
    }

    public function testChromeWithHorizontalPaddingReducesContentWidth()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        // 5 left + 5 right padding = 10 columns consumed
        $root->setStyle(new Style(padding: new Padding(0, 5, 0, 5)));
        // Text that would be wider than the inner area
        $root->add(new TextWidget('ABCDEFGHIJKLMNOPQRSTUVWXYZ'));

        $result = $renderer->render($root, 20, 10);

        // Inner width = 20 - 10 = 10, text should wrap or be contained
        foreach ($result as $line) {
            $this->assertLessThanOrEqual(20, AnsiUtils::visibleWidth($line));
        }
    }

    // ---------------------------------------------------------------
    // Hidden widget filtering
    // ---------------------------------------------------------------

    public function testHiddenChildrenDoNotTakeLayoutSpace()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->add(new TextWidget('Visible'));

        $hidden = new TextWidget('Hidden');
        $hidden->setStyle(new Style(hidden: true));
        $root->add($hidden);

        $root->add(new TextWidget('Also visible'));

        $result = $renderer->render($root, 40, 10);

        // Only 2 visible children should produce lines
        $this->assertCount(2, $result);

        $visible = implode("\n", array_map(static fn (string $line) => AnsiUtils::stripAnsiCodes($line), $result));
        $this->assertStringContainsString('Visible', $visible);
        $this->assertStringContainsString('Also visible', $visible);
        $this->assertStringNotContainsString('Hidden', $visible);
    }

    public function testHiddenChildrenDoNotAffectGap()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(gap: 1));
        $root->add(new TextWidget('First'));

        $hidden = new TextWidget('Hidden');
        $hidden->setStyle(new Style(hidden: true));
        $root->add($hidden);

        $root->add(new TextWidget('Second'));

        $result = $renderer->render($root, 40, 10);

        // Only 2 visible children + 1 gap = 3 lines
        $this->assertCount(3, $result);
    }

    public function testHiddenContainerRendersNothing()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(hidden: true));
        $root->add(new TextWidget('Should not render'));

        $result = $renderer->render($root, 40, 10);
        $this->assertSame([], $result);
    }

    // ---------------------------------------------------------------
    // Outer style resolution (ancestor chain merging)
    // ---------------------------------------------------------------

    public function testOuterStylePropagatesThroughAncestors()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(background: 'blue'));

        $child = new ContainerWidget();
        $child->setStyle(new Style(border: Border::all(1, 'none')));

        $innerText = new TextWidget('Deep');
        $child->add($innerText);
        $root->add($child);

        $result = $renderer->render($root, 40, 10);

        // The root's blue background (ANSI code \e[44m) should appear in the
        // child's border rows since border segments reset to the outer background
        $borderTopRow = $result[0];
        $this->assertStringContainsString("\x1b[44m", $borderTopRow, 'Outer blue background should propagate to child border');
    }

    public function testOuterStyleCloserAncestorOverridesDistant()
    {
        $renderer = new Renderer();

        // Grandparent sets red background
        $grandparent = new ContainerWidget();
        $grandparent->setStyle(new Style(background: 'red'));

        // Parent overrides with green background
        $parent = new ContainerWidget();
        $parent->setStyle(new Style(background: 'green'));

        // Child with a border will use outer style for border rendering
        $child = new ContainerWidget();
        $child->setStyle(new Style(border: Border::all(1, 'none')));
        $child->add(new TextWidget('Nested'));

        $grandparent->add($parent);
        $parent->add($child);

        $result = $renderer->render($grandparent, 40, 10);

        // border-top + content + border-bottom = 3 lines
        $this->assertCount(3, $result);

        // The parent's green background (\e[42m) should appear in the child's
        // border rows: the outer style chain resolves green (closer ancestor)
        // as the outer background for border segment reset codes
        $this->assertStringContainsString("\x1b[42m", $result[0], 'Parent green background should propagate as outer style to child border');
    }

    // ---------------------------------------------------------------
    // Nested containers with conflicting styles
    // ---------------------------------------------------------------

    public function testNestedContainersWithDifferentDirections()
    {
        $renderer = new Renderer();

        // Outer: vertical
        $outer = new ContainerWidget();
        $outer->setStyle(new Style(direction: Direction::Vertical));

        // Inner: horizontal
        $inner = new ContainerWidget();
        $inner->setStyle(new Style(direction: Direction::Horizontal));
        $inner->add(new TextWidget('Left'));
        $inner->add(new TextWidget('Right'));

        $outer->add(new TextWidget('Top'));
        $outer->add($inner);

        $result = $renderer->render($outer, 40, 10);

        // Top is on first line, inner's two children on one line
        $this->assertCount(2, $result);
        $this->assertStringContainsString('Top', AnsiUtils::stripAnsiCodes($result[0]));
        $innerLine = AnsiUtils::stripAnsiCodes($result[1]);
        $this->assertStringContainsString('Left', $innerLine);
        $this->assertStringContainsString('Right', $innerLine);
    }

    public function testNestedContainersWithConflictingPadding()
    {
        $renderer = new Renderer();

        $outer = new ContainerWidget();
        $outer->setStyle(new Style(padding: Padding::all(1)));

        $inner = new ContainerWidget();
        $inner->setStyle(new Style(padding: new Padding(0, 2, 0, 2)));
        $inner->add(new TextWidget('Content'));

        $outer->add($inner);

        $result = $renderer->render($outer, 40, 10);

        // Outer: 1 top + 1 bottom padding
        // Inner: 0 top + 0 bottom but 2 left/right padding
        // Total: 1 top + 1 content + 1 bottom = 3
        $this->assertCount(3, $result);

        // Content line should have outer padding + inner padding
        foreach ($result as $line) {
            $this->assertLessThanOrEqual(40, AnsiUtils::visibleWidth($line));
        }
    }

    public function testNestedContainersWithBordersStack()
    {
        $renderer = new Renderer();

        $outer = new ContainerWidget();
        $outer->setStyle(new Style(border: Border::all(1, 'none')));

        $inner = new ContainerWidget();
        $inner->setStyle(new Style(border: Border::all(1, 'none')));
        $inner->add(new TextWidget('Deep'));

        $outer->add($inner);

        $result = $renderer->render($outer, 40, 10);

        // Outer border top + inner border top + content + inner border bottom + outer border bottom = 5
        $this->assertCount(5, $result);
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    public function testContainerWithZeroWidthAfterChromeDeduction()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        // Border takes 2 columns (left + right), padding takes 8 columns, total chrome = 10
        // With width 10, inner width = max(1, 10 - 10) = 1
        $root->setStyle(new Style(
            padding: new Padding(0, 4, 0, 4),
            border: Border::all(1, 'none'),
        ));
        $root->add(new TextWidget('X'));

        $result = $renderer->render($root, 10, 10);

        // Should render without error, content area is 1 column
        $this->assertStringContainsString('X', implode("\n", $result));
    }

    public function testManyChildrenExceedingAvailableVerticalSpace()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        for ($i = 0; $i < 50; ++$i) {
            $root->add(new TextWidget("Line $i"));
        }

        // Only 10 rows available but 50 children
        $result = $renderer->render($root, 40, 10);

        // All 50 children still produce lines (no truncation at container level)
        $this->assertCount(50, $result);
    }

    public function testHorizontalLayoutWithMoreChildrenThanColumns()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));

        // 10 children with only 5 columns: only the first 5 fit
        for ($i = 0; $i < 10; ++$i) {
            $root->add(new TextWidget((string) $i));
        }

        $result = $renderer->render($root, 5, 10);

        // Only children 0–4 are rendered (one per column), each 1 column wide
        $visible = AnsiUtils::stripAnsiCodes($result[0]);
        $this->assertSame(5, AnsiUtils::visibleWidth($result[0]));
        $this->assertStringContainsString('0', $visible);
        $this->assertStringContainsString('4', $visible);
        // Child "5" and beyond are truncated away
        $this->assertStringNotContainsString('5', $visible);
    }

    /**
     * @return iterable<string, array{Style, int}>
     */
    public static function emptyContainerChromeProvider(): iterable
    {
        yield 'border only' => [new Style(border: Border::all(1, 'none')), 2];
        yield 'border and padding' => [new Style(padding: Padding::all(1), border: Border::all(1, 'none')), 4];
    }

    #[DataProvider('emptyContainerChromeProvider')]
    public function testEmptyContainerWithChrome(Style $style, int $expectedLines)
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle($style);

        $result = $renderer->render($root, 20, 10);

        $this->assertCount($expectedLines, $result);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function horizontalLayoutColumnsProvider(): iterable
    {
        yield 'equal distribution (30/3)' => [30];
        yield 'remainder distribution (31/3)' => [31];
    }

    #[DataProvider('horizontalLayoutColumnsProvider')]
    public function testHorizontalLayoutColumnDistribution(int $totalColumns)
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));

        $root->add(new TextWidget('A'));
        $root->add(new TextWidget('B'));
        $root->add(new TextWidget('C'));

        $result = $renderer->render($root, $totalColumns, 10);

        $this->assertCount(1, $result);
        $this->assertSame($totalColumns, AnsiUtils::visibleWidth($result[0]));
    }

    public function testContainerWithOnlyHiddenChildrenProducesNoOutput()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        $h1 = new TextWidget('Hidden 1');
        $h1->setStyle(new Style(hidden: true));
        $h2 = new TextWidget('Hidden 2');
        $h2->setStyle(new Style(hidden: true));

        $root->add($h1);
        $root->add($h2);

        $result = $renderer->render($root, 40, 10);
        $this->assertSame([], $result);
    }

    public function testStyleSheetRuleAppliedToWidget()
    {
        $styleSheet = new StyleSheet([
            TextWidget::class => new Style(bold: true),
        ]);
        $renderer = new Renderer($styleSheet);
        $root = new ContainerWidget();
        $root->add(new TextWidget('Bold text'));

        $result = $renderer->render($root, 40, 10);

        // Bold ANSI code should be in the output
        $this->assertStringContainsString("\x1b[1m", $result[0]);
    }

    // ---------------------------------------------------------------
    // Inner dimension computation consistency
    // ---------------------------------------------------------------

    public function testContainerInnerDimensionsWithBorderAndPadding()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        // border=1 each side + padding=2 each side => 6 off each dimension
        $root->setStyle(new Style(
            border: Border::all(1, 'none'),
            padding: Padding::all(2),
        ));

        // outer=40 cols => inner = 40 - 2 (border) - 4 (padding) = 34 content width
        // 'X' * 34 fits exactly one content line
        $root->add(new TextWidget(str_repeat('X', 34)));

        $result = $renderer->render($root, 40, 10);

        // Row count: border-top(1) + padding-top(2) + content(1) + padding-bottom(2) + border-bottom(1) = 7
        $this->assertCount(7, $result);

        // Every line should have visible width = 40 (full outer width with chrome)
        $this->assertSame(40, AnsiUtils::visibleWidth($result[3]));
    }

    public function testInnerDimensionsNeverBelowOne()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        // Huge padding that exceeds the available space
        $root->setStyle(new Style(
            padding: Padding::all(50),
        ));
        $root->add(new TextWidget('X'));

        // Even with 10x5 viewport and padding=50 each side, padding is clamped
        // so inner content gets at least 1 column and lines don't exceed width
        $result = $renderer->render($root, 10, 5);

        // Content is rendered and no line exceeds the container width
        $this->assertStringContainsString('X', implode('', $result));
        foreach ($result as $line) {
            $this->assertLessThanOrEqual(10, AnsiUtils::visibleWidth($line));
        }
    }

    // ---------------------------------------------------------------
    // Widget position tracking
    // ---------------------------------------------------------------

    public function testWidgetPositionTrackingForRootWidget()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->add(new TextWidget('Hello'));

        $result = $renderer->render($root, 40, 10);
        $rect = $renderer->getWidgetRect($root);

        $this->assertSame(0, $rect->row);
        $this->assertSame(0, $rect->col);
        $this->assertSame(40, $rect->columns);
        $this->assertSame(\count($result), $rect->rows);
    }

    public function testWidgetPositionTrackingForVerticalChildren()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $child1 = new TextWidget('Line 1');
        $child2 = new TextWidget('Line 2');
        $child3 = new TextWidget('Line 3');
        $root->add($child1);
        $root->add($child2);
        $root->add($child3);

        $renderer->render($root, 40, 10);

        $rect1 = $renderer->getWidgetRect($child1);
        $rect2 = $renderer->getWidgetRect($child2);
        $rect3 = $renderer->getWidgetRect($child3);

        // Each child takes 1 row, no gap
        $this->assertSame(0, $rect1->row);
        $this->assertSame(1, $rect2->row);
        $this->assertSame(2, $rect3->row);

        // All children span the full width
        $this->assertSame(40, $rect1->columns);
        $this->assertSame(40, $rect2->columns);
        $this->assertSame(40, $rect3->columns);
    }

    public function testWidgetPositionTrackingWithGap()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(gap: 2));
        $child1 = new TextWidget('A');
        $child2 = new TextWidget('B');
        $root->add($child1);
        $root->add($child2);

        $renderer->render($root, 40, 20);

        $rect1 = $renderer->getWidgetRect($child1);
        $rect2 = $renderer->getWidgetRect($child2);

        // First child at row 0, second at row 3 (1 row content + 2 gap)
        $this->assertSame(0, $rect1->row);
        $this->assertSame(3, $rect2->row);
    }

    public function testWidgetPositionTrackingWithPaddingAndBorder()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(
            padding: new Padding(2, 1, 0, 1),
            border: Border::all(1),
        ));
        $child = new TextWidget('Inside');
        $root->add($child);

        $renderer->render($root, 40, 20);

        $rect = $renderer->getWidgetRect($child);

        // Chrome offset: border top (1) + padding top (2) = 3
        $this->assertSame(3, $rect->row);
        // Chrome offset: border left (1) + padding left (1) = 2
        $this->assertSame(2, $rect->col);
    }

    public function testWidgetPositionTrackingForHorizontalChildren()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));
        $child1 = new TextWidget('Left');
        $child2 = new TextWidget('Right');
        $root->add($child1);
        $root->add($child2);

        $renderer->render($root, 40, 10);

        $rect1 = $renderer->getWidgetRect($child1);
        $rect2 = $renderer->getWidgetRect($child2);

        // Both at row 0
        $this->assertSame(0, $rect1->row);
        $this->assertSame(0, $rect2->row);

        // First child starts at col 0, second at col 20 (40 / 2 children)
        $this->assertSame(0, $rect1->col);
        $this->assertSame(20, $rect2->col);

        // Each gets half the width
        $this->assertSame(20, $rect1->columns);
        $this->assertSame(20, $rect2->columns);
    }

    public function testWidgetPositionTrackingForNestedContainers()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(padding: new Padding(1, 0, 0, 2)));

        $inner = new ContainerWidget();
        $inner->setStyle(new Style(padding: new Padding(1, 0, 0, 3)));
        $leaf = new TextWidget('Deep');
        $inner->add($leaf);
        $root->add($inner);

        $renderer->render($root, 40, 20);

        $innerRect = $renderer->getWidgetRect($inner);
        $leafRect = $renderer->getWidgetRect($leaf);

        // Inner container: root padding-top=1, padding-left=2
        $this->assertSame(1, $innerRect->row);
        $this->assertSame(2, $innerRect->col);

        // Leaf: root padding(1,2) + inner padding(1,3) = row 2, col 5
        $this->assertSame(2, $leafRect->row);
        $this->assertSame(5, $leafRect->col);
    }

    public function testWidgetPositionTrackingReturnsNullForUnrenderedWidget()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->add(new TextWidget('A'));

        $renderer->render($root, 40, 10);

        // A widget that was not part of the tree
        $orphan = new TextWidget('Orphan');
        $this->assertNull($renderer->getWidgetRect($orphan));
    }

    public function testWidgetPositionTrackingForNonFillChildAfterFillChild()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        // Fill child: expands to consume remaining vertical space
        $fillChild = new ContainerWidget();
        $fillChild->expandVertically(true);
        $fillLeaf = new TextWidget('Fill');
        $fillChild->add($fillLeaf);

        // Non-fill child: renders after the fill child
        $footer = new ContainerWidget();
        $footer->setStyle(new Style(padding: new Padding(0, 0, 0, 1)));
        $footerLeaf = new TextWidget('Footer');
        $footer->add($footerLeaf);

        $root->add($fillChild);
        $root->add($footer);

        $renderer->render($root, 40, 20);

        $fillRect = $renderer->getWidgetRect($fillChild);
        $footerRect = $renderer->getWidgetRect($footer);
        $footerLeafRect = $renderer->getWidgetRect($footerLeaf);

        // Fill child starts at row 0
        $this->assertSame(0, $fillRect->row);

        // Footer starts after fill child (20 rows - 1 row footer = 19 rows fill)
        $this->assertSame(19, $footerRect->row);

        // Footer leaf accounts for footer's padding-left=1
        $this->assertSame(19, $footerLeafRect->row);
        $this->assertSame(1, $footerLeafRect->col);
    }

    public function testWidgetPositionTrackingForDescendantsInHorizontalLayout()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal));

        // Left pane with nested content
        $leftPane = new ContainerWidget();
        $leftPane->setStyle(new Style(padding: new Padding(1, 0, 0, 2)));
        $leftLeaf = new TextWidget('Left');
        $leftPane->add($leftLeaf);

        // Right pane with nested content
        $rightPane = new ContainerWidget();
        $rightPane->setStyle(new Style(padding: new Padding(1, 0, 0, 2)));
        $rightLeaf = new TextWidget('Right');
        $rightPane->add($rightLeaf);

        $root->add($leftPane);
        $root->add($rightPane);

        $renderer->render($root, 40, 10);

        $leftLeafRect = $renderer->getWidgetRect($leftLeaf);
        $rightLeafRect = $renderer->getWidgetRect($rightLeaf);

        // Left leaf: pane padding top=1, left=2
        $this->assertSame(1, $leftLeafRect->row);
        $this->assertSame(2, $leftLeafRect->col);

        // Right leaf: pane starts at col 20 (40/2), padding top=1, left=2
        $this->assertSame(1, $rightLeafRect->row);
        $this->assertSame(22, $rightLeafRect->col);
    }

    public function testVerticalLayoutDoesNotReRenderLeafChildrenInSecondPass()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        $first = new CountingLeafWidget();
        $second = new CountingLeafWidget();
        $root->add($first);
        $root->add($second);

        $renderer->render($root, 40, 10);

        $this->assertSame(1, $first->renderCount);
        $this->assertSame(1, $second->renderCount);
    }

    public function testVerticalLayoutStillRerendersNonFillParentChildrenInSecondPass()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        $childParent = new CountingParentWidget();
        $childParent->add(new TextWidget('Leaf'));
        $root->add($childParent);

        $renderer->render($root, 40, 10);

        $this->assertSame(2, $childParent->renderCount);
    }

    // ---------------------------------------------------------------
    // Render cache
    // ---------------------------------------------------------------

    public function testRenderCacheSkipsReRenderForUnchangedWidget()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        $widget = new CountingLeafWidget();
        $root->add($widget);

        $renderer->render($root, 40, 10);
        $this->assertSame(1, $widget->renderCount);

        // Second render with no changes: cache hit, render() not called again
        $renderer->render($root, 40, 10);
        $this->assertSame(1, $widget->renderCount);
    }

    public function testRenderCacheInvalidatesOnWidgetChange()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        $widget = new CountingLeafWidget();
        $root->add($widget);

        $renderer->render($root, 40, 10);
        $this->assertSame(1, $widget->renderCount);

        // Invalidate the widget: next render must call render() again
        $widget->invalidate();
        $renderer->render($root, 40, 10);
        $this->assertSame(2, $widget->renderCount);
    }

    public function testRenderCacheInvalidatesOnDimensionChange()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        $widget = new CountingLeafWidget();
        $root->add($widget);

        $renderer->render($root, 40, 10);
        $this->assertSame(1, $widget->renderCount);

        // Different columns: cache miss, render() called again
        $renderer->render($root, 60, 10);
        $this->assertSame(2, $widget->renderCount);
    }

    public function testRenderCacheSkipsSiblingWhenOnlyOneChildChanges()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        $stable = new CountingLeafWidget();
        $changing = new CountingLeafWidget();
        $root->add($stable);
        $root->add($changing);

        $renderer->render($root, 40, 10);
        $this->assertSame(1, $stable->renderCount);
        $this->assertSame(1, $changing->renderCount);

        // Only invalidate one child: the other should be cached
        $changing->invalidate();
        $renderer->render($root, 40, 10);
        $this->assertSame(1, $stable->renderCount);
        $this->assertSame(2, $changing->renderCount);
    }

    public function testRenderCachePreservesCorrectOutput()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        $widget = new TextWidget('Hello');
        $root->add($widget);

        $first = $renderer->render($root, 40, 10);
        $second = $renderer->render($root, 40, 10);

        $this->assertSame($first, $second);
    }

    public function testRenderCacheStillTracksDescendantPositions()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();

        $inner = new ContainerWidget();
        $inner->setStyle(new Style(padding: new Padding(1, 0, 0, 2)));
        $leaf = new TextWidget('Nested');
        $inner->add($leaf);
        $root->add($inner);

        // First render populates positions
        $renderer->render($root, 40, 20);
        $leafRect = $renderer->getWidgetRect($leaf);
        $this->assertNotNull($leafRect);
        $this->assertSame(1, $leafRect->row);
        $this->assertSame(2, $leafRect->col);

        // Second render: inner + leaf unchanged, positions still tracked
        $renderer->render($root, 40, 20);
        $leafRect2 = $renderer->getWidgetRect($leaf);
        $this->assertNotNull($leafRect2);
        $this->assertSame(1, $leafRect2->row);
        $this->assertSame(2, $leafRect2->col);
    }

    // ---------------------------------------------------------------
    // Width contract validation
    // ---------------------------------------------------------------

    public function testOverwideWidgetThrowsRenderExceptionWithWidgetContext()
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->add(new OverwideWidget(50));

        try {
            $renderer->render($root, 20, 10);
            $this->fail('Expected RenderException');
        } catch (RenderException $e) {
            $this->assertStringContainsString('OverwideWidget', $e->getMessage());
            $this->assertSame(50, $e->getLineWidth());
            $this->assertSame(20, $e->getTerminalWidth());
        }
    }

    /**
     * @return iterable<string, array{int, int, int, int}>
     */
    public static function horizontalOverflowProvider(): iterable
    {
        // [childCount, gap, columns, expectedWidth]
        yield 'no gap, 20 children in 8 cols' => [20, 0, 8, 8];
        yield 'gap=1, 10 children in 7 cols' => [10, 1, 7, 7];
        yield 'gap=2, 10 children in 10 cols' => [10, 2, 10, 10];
    }

    #[DataProvider('horizontalOverflowProvider')]
    public function testHorizontalLayoutCapsChildrenToAvailableColumns(int $childCount, int $gap, int $columns, int $expectedWidth)
    {
        $renderer = new Renderer();
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Horizontal, gap: $gap));

        for ($i = 0; $i < $childCount; ++$i) {
            $root->add(new TextWidget((string) $i));
        }

        $result = $renderer->render($root, $columns, 10);
        $this->assertSame($expectedWidth, AnsiUtils::visibleWidth($result[0]));
    }
}

/**
 * Test widget that intentionally produces a line wider than the context allows.
 *
 * @internal
 */
class OverwideWidget extends AbstractWidget
{
    public function __construct(private readonly int $outputWidth)
    {
    }

    public function render(RenderContext $context): array
    {
        return [str_repeat('X', $this->outputWidth)];
    }
}

/**
 * @internal
 */
class CountingLeafWidget extends AbstractWidget
{
    public int $renderCount = 0;

    public function render(RenderContext $context): array
    {
        ++$this->renderCount;

        return ['leaf'];
    }
}

/**
 * @internal
 */
class CountingParentWidget extends AbstractWidget implements ParentInterface
{
    public int $renderCount = 0;

    /** @var list<AbstractWidget> */
    private array $children = [];

    public function add(AbstractWidget $child): void
    {
        $child->setParent($this);
        $this->children[] = $child;
        $this->invalidate();
    }

    /**
     * @return list<AbstractWidget>
     */
    public function all(): array
    {
        return $this->children;
    }

    public function render(RenderContext $context): array
    {
        ++$this->renderCount;

        return ['parent'];
    }
}
