<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Widget;

use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\VerticallyExpandableInterface;
use Symfony\Component\Tui\Widget\WidgetContainerInterface;
use Symfony\Component\TuiHtml\Internal\ContainerState;
use Symfony\Component\TuiHtml\Style\RasterImage;
use Symfony\Component\TuiHtml\Style\VectorImage;
use Symfony\Component\TuiHtml\Widget\Text\GeneratedContentInterface;

/**
 * Container widget that groups child widgets with optional styling.
 *
 * Supports:
 * - Vertical or horizontal layout (via Style::direction)
 * - Gap between children (via Style::gap)
 * - Padding, border, background via Style
 * - Vertically expandable children
 *
 * Layout direction and gap are style properties, configurable via
 * stylesheets or inline styles:
 *
 *     $container->setStyle(new Style(direction: Direction::Horizontal, gap: 1));
 *
 * Or via stylesheet rules:
 *
 *     $stylesheet->addRule('.panes', new Style(direction: Direction::Horizontal, gap: 2));
 *
 * Layout and chrome rendering is handled by the Renderer.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ContainerWidget extends \Symfony\Component\Tui\Widget\ContainerWidget implements WidgetContainerInterface, VerticallyExpandableInterface, GeneratedContentInterface
{
    /**
     * Scrolls the content of a scroll container (CSS overflow: auto or scroll) to a row, like Element::scrollTop.
     *
     * The row is kept within the content once it has been rendered.
     *
     * @return $this
     */
    public function scrollTo(int $row): static
    {
        ContainerState::of($this)->scrollTo($row);

        return $this;
    }

    /**
     * Scrolls the content by some rows, up when negative.
     *
     * @return $this
     */
    public function scrollBy(int $rows): static
    {
        ContainerState::of($this)->scrollBy($rows);

        return $this;
    }

    /**
     * The first row of the content shown, like Element::scrollTop.
     */
    public function getScrollTop(): int
    {
        return ContainerState::of($this)->getScrollTop();
    }

    /**
     * The rows of the whole content at the last render, like Element::scrollHeight.
     */
    public function getScrollHeight(): int
    {
        return ContainerState::of($this)->getScrollHeight();
    }

    /**
     * The rows of the content shown at the last render, like Element::clientHeight; 0 when the container does not scroll.
     */
    public function getClientHeight(): int
    {
        return ContainerState::of($this)->getClientHeight();
    }

    /**
     * @internal
     */
    public function setScrollMetrics(int $top, int $height, int $client, bool $overscrollContained = false): void
    {
        ContainerState::of($this)->setScrollMetrics($top, $height, $client, $overscrollContained);
    }

    /**
     * Draws text before and after the children, like CSS ::before and ::after on a block: a line of its own each.
     */
    /**
     * Sets the images of its ::before and ::after, like content: url(icon.png), each standing for characters of their
     * text, see RichTextWidget::inlineImageText() with generated images.
     *
     * @param list<array{0: RasterImage|VectorImage, 1: int, 2?: string}> $images
     */
    public function setGeneratedImages(array $images): void
    {
        ContainerState::of($this)->setGeneratedImages($images);
    }

    /**
     * @return list<array{0: RasterImage|VectorImage, 1: int, 2?: string}>
     */
    public function getGeneratedImages(): array
    {
        return ContainerState::of($this)->getGeneratedImages();
    }

    public function setGeneratedContent(?string $before, ?string $after): void
    {
        ContainerState::of($this)->setGeneratedContent($before, $after);
    }

    /**
     * Draws buttons that scroll the content a page, like CSS ::scroll-button(): their text by direction.
     *
     * @param array<'up'|'down'|'left'|'right', string> $buttons
     *
     * @internal
     */
    public function setScrollButtons(array $buttons): void
    {
        ContainerState::of($this)->setScrollButtons($buttons);
    }

    /**
     * @return array<'up'|'down'|'left'|'right', string> The text of the buttons that scroll the content, by direction
     *
     * @internal
     */
    public function getScrollButtons(): array
    {
        return ContainerState::of($this)->getScrollButtons();
    }

    /**
     * Draws markers that scroll to the children they stand for, like CSS scroll-marker-group and ::scroll-marker.
     *
     * @param 'before'|'after'|null               $position Before the content or after it, null for none
     * @param list<array{AbstractWidget, string}> $markers  The children with a marker, and its text
     *
     * @internal
     */
    public function setScrollMarkers(?string $position, array $markers): void
    {
        ContainerState::of($this)->setScrollMarkers($position, $markers);
    }

    /**
     * @return array{?string, list<array{AbstractWidget, string}>} Where the markers are drawn, null for none, and the children with one and its text
     *
     * @internal
     */
    public function getScrollMarkers(): array
    {
        return ContainerState::of($this)->getScrollMarkers();
    }

    /**
     * @return array{?string, ?string} The text drawn before and after the children, null for none
     *
     * @internal
     */
    public function getGeneratedContent(): array
    {
        return ContainerState::of($this)->getGeneratedContent();
    }

    /**
     * Scrolls the content sideways so that a column is the first one shown, like Element::scrollLeft.
     *
     * @return $this
     */
    public function scrollToColumn(int $column): static
    {
        ContainerState::of($this)->scrollToColumn($column);

        return $this;
    }

    /**
     * Scrolls the content sideways by some columns, to the left when negative.
     *
     * @return $this
     */
    public function scrollByColumns(int $columns): static
    {
        ContainerState::of($this)->scrollByColumns($columns);

        return $this;
    }

    /**
     * The first column of the content shown, like Element::scrollLeft.
     */
    public function getScrollLeft(): int
    {
        return ContainerState::of($this)->getScrollLeft();
    }

    /**
     * The columns of the whole content at the last render, like Element::scrollWidth.
     */
    public function getScrollWidth(): int
    {
        return ContainerState::of($this)->getScrollWidth();
    }

    /**
     * The columns of the content shown at the last render, like Element::clientWidth; 0 when the container does not
     * scroll sideways.
     */
    public function getClientWidth(): int
    {
        return ContainerState::of($this)->getClientWidth();
    }

    /**
     * @internal
     */
    public function setHorizontalScrollMetrics(int $left, int $width, int $client, bool $overscrollContained = false): void
    {
        ContainerState::of($this)->setHorizontalScrollMetrics($left, $width, $client, $overscrollContained);
    }

    /**
     * Whether scrolling past the left and right ends of this container stops at it, like CSS overscroll-behavior-x: contain.
     *
     * @internal
     */
    public function isOverscrollContainedSideways(): bool
    {
        return ContainerState::of($this)->isOverscrollContainedSideways();
    }

    /**
     * Whether scrolling past the ends of this scroll container stops at it, like CSS overscroll-behavior: contain.
     *
     * @internal
     */
    public function isOverscrollContained(): bool
    {
        return ContainerState::of($this)->isOverscrollContained();
    }

    /**
     * Sets the floats of a block around this one that its content flows around, like CSS: each its side, its first
     * row, its rows and its width, in the content box of this one, under the rows its ::before that clears takes.
     *
     * @internal
     *
     * @param list<array{string, int, int, int, 4?: list<int>|null, 5?: int}> $floats
     * @param int                                                             $clearance   The rows of its content box the floats its ::before clears take, see Style::withBeforeClear(): blank, its content under them
     * @param int                                                             $marginAbove The rows at the top of its content box of the margins collapsed above it and its own, which the ::before of its first child is under, like CSS
     */
    public function setFloatExclusion(array $floats, int $clearance = 0, int $marginAbove = 0): void
    {
        ContainerState::of($this)->setFloatExclusion($floats, $clearance, $marginAbove);
    }

    /**
     * The rows of its content box its ::before that clears takes, see setFloatExclusion().
     *
     * @internal
     */
    public function getClearance(): int
    {
        return ContainerState::of($this)->getClearance();
    }

    /**
     * The rows of margin at the top of its content box collapsed from the margins above it, see setFloatExclusion().
     *
     * @internal
     */
    public function getMarginAbove(): int
    {
        return ContainerState::of($this)->getMarginAbove();
    }

    /**
     * @internal
     *
     * @return list<array{string, int, int, int, 4?: list<int>|null, 5?: int}>
     */
    public function getFloatExclusion(): array
    {
        return ContainerState::of($this)->getFloatExclusion();
    }

    /**
     * Sets whether the block around lays it out among floats, those of its content able to go on past it.
     *
     * @internal
     */
    public function setFloatsPast(bool $past): void
    {
        ContainerState::of($this)->setFloatsPast($past);
    }

    /**
     * Drops the floats of the block around once the layout that set them is gone, like when it moved to another parent or
     * was taken out of the flow: its content no longer beside them, see setFloatExclusion() and setFloatsPast().
     *
     * @internal
     */
    public function dropStaleFloats(): void
    {
        ContainerState::of($this)->dropStaleFloats();
    }

    /**
     * @internal
     */
    public function hasFloatsPast(): bool
    {
        return ContainerState::of($this)->hasFloatsPast();
    }

    /**
     * Sets the floats of its content that go on past it in the render just made, like CSS: the block after it flows
     * around them; each its side, its first row, its rows, its width, its shape, its columns from the side, whether its
     * width is a percentage, its lines and the row they start at, in its content box: the block around places them
     * from the content box of this one, see LayoutEngine::escapeFloats().
     *
     * @internal
     *
     * @param list<array{string, int, int, int, list<int>|null, int, bool, LineBufferInterface, int}> $floats
     */
    public function setEscapedFloats(array $floats): void
    {
        ContainerState::of($this)->setEscapedFloats($floats);
    }

    /**
     * The floats going on past it of its last render, drawn or taken from the render cache, where the Renderer keeps
     * them with its lines, see AbstractWidget::setRenderCache().
     *
     * @internal
     *
     * @return list<array{string, int, int, int, list<int>|null, int, bool, LineBufferInterface, int}>
     */
    public function getEscapedFloats(): array
    {
        return ContainerState::of($this)->getEscapedFloats();
    }

    /**
     * Lets the children of a container whose height is auto take percentage heights of the definite height of its
     * parent, minus its borders and padding, like <html> and <body> under the frame.
     */
    public function setPassesDefiniteHeight(bool $passes = true): static
    {
        ContainerState::of($this)->setPassesDefiniteHeight($passes);

        return $this;
    }

    /**
     * Draws a child in the top border, near its left corner, like the <legend> of a <fieldset>, instead of in the
     * flow; without a top border, it stays in the flow.
     */
    public function setLegend(?AbstractWidget $legend): static
    {
        ContainerState::of($this)->setLegend($legend);

        return $this;
    }

    public function getLegend(): ?AbstractWidget
    {
        return ContainerState::of($this)->getLegend();
    }

    /**
     * Lays out a wrapping row like text flows: a text that does not fit after the children before it takes the room
     * left on their line, wrapping there, rather than a line of its own, like the text after an image in a paragraph.
     */
    public function setInlineFlow(bool $flow = true): static
    {
        ContainerState::of($this)->setInlineFlow($flow);

        return $this;
    }

    public function isInlineFlow(): bool
    {
        return ContainerState::of($this)->isInlineFlow();
    }

    public function passesDefiniteHeight(): bool
    {
        return ContainerState::of($this)->passesDefiniteHeight();
    }
}
