<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Internal;

use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Symfony\Component\TuiHtml\Style\RasterImage;
use Symfony\Component\TuiHtml\Style\VectorImage;
use Symfony\Component\TuiHtml\Widget\Text\InlineImages;

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
class ContainerState
{
    private bool $passesDefiniteHeight = false;
    private ?AbstractWidget $legend = null;
    private bool $inlineFlow = false;
    /** @var list<array{string, int, int, int, 4?: list<int>|null, 5?: int}> The floats of a block around it that its content flows around */
    private array $floatExclusion = [];
    private int $clearance = 0;
    /** The rows of margin at the top of its content box collapsed from the margins above it, see setFloatExclusion() */
    private int $marginAbove = 0;
    /** @var list<array{string, int, int, int, list<int>|null, int, bool, LineBufferInterface, int}> The floats its last render lets out past its content, see setEscapedFloats() */
    private array $escapedFloats = [];
    /** Whether the block around lays it out among its floats, its own floats free to go on past it, see setFloatsPast() */
    private bool $floatsPast = false;
    private int $scrollTop = 0;
    private int $scrollHeight = 0;
    private int $clientHeight = 0;
    private bool $overscrollContained = false;
    /** @var array{?string, ?string} What CSS ::before and ::after give, drawn before and after the children */
    private array $generated = [null, null];
    /** @var list<array{0: RasterImage|VectorImage, 1: int, 2?: string}> */
    private array $generatedImages = [];
    /** @var array<'up'|'down'|'left'|'right', string> What CSS ::scroll-button() gives, by direction */
    private array $scrollButtons = [];
    /** @var array{?string, list<array{AbstractWidget, string}>} Where CSS scroll-marker-group draws the markers, and the children with a ::scroll-marker and its text */
    private array $scrollMarkers = [null, []];
    private int $scrollLeft = 0;
    private int $scrollWidth = 0;
    private int $clientWidth = 0;
    private bool $overscrollContainedSideways = false;

    /**
     * Scrolls the content of a scroll container (CSS overflow: auto or scroll) to a row, like Element::scrollTop.
     *
     * The row is kept within the content once it has been rendered.
     */
    public function scrollTo(int $row): ContainerWidget
    {
        $row = max(0, $row);
        if ($this->clientHeight > 0) {
            $row = min($row, max(0, $this->scrollHeight - $this->clientHeight));
        }
        if ($row !== $this->scrollTop) {
            $this->scrollTop = $row;
            $this->owner->invalidate();
        }

        return $this->owner;
    }

    /**
     * Scrolls the content by some rows, up when negative.
     */
    public function scrollBy(int $rows): ContainerWidget
    {
        return $this->scrollTo($this->scrollTop + $rows);
    }

    /**
     * The first row of the content shown, like Element::scrollTop.
     */
    public function getScrollTop(): int
    {
        return $this->scrollTop;
    }

    /**
     * The rows of the whole content at the last render, like Element::scrollHeight.
     */
    public function getScrollHeight(): int
    {
        return $this->scrollHeight;
    }

    /**
     * The rows of the content shown at the last render, like Element::clientHeight; 0 when the container does not scroll.
     */
    public function getClientHeight(): int
    {
        return $this->clientHeight;
    }

    /**
     * @internal
     */
    public function setScrollMetrics(int $top, int $height, int $client, bool $overscrollContained = false): void
    {
        $this->scrollTop = $top;
        $this->scrollHeight = $height;
        $this->clientHeight = $client;
        $this->overscrollContained = $overscrollContained;
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
        $images = \array_slice(array_values($images), 0, InlineImages::MAX);
        if ($images !== $this->generatedImages) {
            $this->generatedImages = $images;
            $this->owner->invalidate();
        }
    }

    /**
     * @return list<array{0: RasterImage|VectorImage, 1: int, 2?: string}>
     */
    public function getGeneratedImages(): array
    {
        return $this->generatedImages;
    }

    public function setGeneratedContent(?string $before, ?string $after): void
    {
        // Text, which attr() can take from the document: no control bytes, which would write to the terminal
        $before = null !== $before ? StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($before)) : null;
        $after = null !== $after ? StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($after)) : null;
        if ([$before, $after] !== $this->generated) {
            $this->generated = [$before, $after];
            $this->owner->invalidate();
        }
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
        $buttons = array_map(static fn (string $text) => StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($text)), array_intersect_key($buttons, array_flip(['up', 'down', 'left', 'right'])));
        if ($buttons !== $this->scrollButtons) {
            $this->scrollButtons = $buttons;
            $this->owner->invalidate();
        }
    }

    /**
     * @return array<'up'|'down'|'left'|'right', string> The text of the buttons that scroll the content, by direction
     *
     * @internal
     */
    public function getScrollButtons(): array
    {
        return $this->scrollButtons;
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
        $markers = null === $position ? [] : array_map(static fn (array $marker) => [$marker[0], StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($marker[1]))], $markers);
        // Without markers, no group: as it is kept, so that the same markers set again change nothing
        $position = $markers ? $position : null;
        if ([$position, $markers] !== $this->scrollMarkers) {
            $this->scrollMarkers = [$position, $markers];
            $this->owner->invalidate();
        }
    }

    /**
     * @return array{?string, list<array{AbstractWidget, string}>} Where the markers are drawn, null for none, and the children with one and its text
     *
     * @internal
     */
    public function getScrollMarkers(): array
    {
        return $this->scrollMarkers;
    }

    /**
     * @return array{?string, ?string} The text drawn before and after the children, null for none
     *
     * @internal
     */
    public function getGeneratedContent(): array
    {
        return $this->generated;
    }

    /**
     * Scrolls the content sideways so that a column is the first one shown, like Element::scrollLeft.
     */
    public function scrollToColumn(int $column): ContainerWidget
    {
        $column = max(0, $column);
        if ($this->clientWidth > 0) {
            $column = min($column, max(0, $this->scrollWidth - $this->clientWidth));
        }
        if ($column !== $this->scrollLeft) {
            $this->scrollLeft = $column;
            $this->owner->invalidate();
        }

        return $this->owner;
    }

    /**
     * Scrolls the content sideways by some columns, to the left when negative.
     */
    public function scrollByColumns(int $columns): ContainerWidget
    {
        return $this->scrollToColumn($this->scrollLeft + $columns);
    }

    /**
     * The first column of the content shown, like Element::scrollLeft.
     */
    public function getScrollLeft(): int
    {
        return $this->scrollLeft;
    }

    /**
     * The columns of the whole content at the last render, like Element::scrollWidth.
     */
    public function getScrollWidth(): int
    {
        return $this->scrollWidth;
    }

    /**
     * The columns of the content shown at the last render, like Element::clientWidth; 0 when the container does not
     * scroll sideways.
     */
    public function getClientWidth(): int
    {
        return $this->clientWidth;
    }

    /**
     * @internal
     */
    public function setHorizontalScrollMetrics(int $left, int $width, int $client, bool $overscrollContained = false): void
    {
        $this->scrollLeft = $left;
        $this->scrollWidth = $width;
        $this->clientWidth = $client;
        $this->overscrollContainedSideways = $overscrollContained;
    }

    /**
     * Whether scrolling past the left and right ends of this container stops at it, like CSS overscroll-behavior-x: contain.
     *
     * @internal
     */
    public function isOverscrollContainedSideways(): bool
    {
        return $this->overscrollContainedSideways && $this->clientWidth > 0;
    }

    /**
     * Whether scrolling past the ends of this scroll container stops at it, like CSS overscroll-behavior: contain.
     *
     * @internal
     */
    public function isOverscrollContained(): bool
    {
        return $this->overscrollContained && $this->clientHeight > 0;
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
        $this->markFloatsSet();
        if ($floats !== $this->floatExclusion || $clearance !== $this->clearance || $marginAbove !== $this->marginAbove) {
            $this->floatExclusion = $floats;
            $this->clearance = $clearance;
            $this->marginAbove = $marginAbove;
            $this->owner->clearRenderCache();
        }
    }

    /**
     * The rows of its content box its ::before that clears takes, see setFloatExclusion().
     *
     * @internal
     */
    public function getClearance(): int
    {
        return $this->clearance;
    }

    /**
     * The rows of margin at the top of its content box collapsed from the margins above it, see setFloatExclusion().
     *
     * @internal
     */
    public function getMarginAbove(): int
    {
        return $this->marginAbove;
    }

    /**
     * @internal
     *
     * @return list<array{string, int, int, int, 4?: list<int>|null, 5?: int}>
     */
    public function getFloatExclusion(): array
    {
        return $this->floatExclusion;
    }

    /**
     * Sets whether the block around lays it out among floats, those of its content able to go on past it.
     *
     * @internal
     */
    public function setFloatsPast(bool $past): void
    {
        $this->markFloatsSet();
        if ($past !== $this->floatsPast) {
            $this->floatsPast = $past;
            $this->escapedFloats = [];
            $this->owner->clearRenderCache();
        }
    }

    /**
     * Drops the floats of the block around once the layout that set them is gone, like when it moved to another parent or
     * was taken out of the flow: its content no longer beside them, see setFloatExclusion() and setFloatsPast().
     *
     * @internal
     */
    public function dropStaleFloats(): void
    {
        if ([] === $this->floatExclusion && 0 === $this->clearance && 0 === $this->marginAbove && !$this->floatsPast || $this->floatsSetByLayout()) {
            return;
        }
        $this->floatExclusion = [];
        $this->clearance = 0;
        $this->marginAbove = 0;
        $this->floatsPast = false;
        $this->escapedFloats = [];
        $this->owner->clearRenderCache();
    }

    /**
     * @internal
     */
    public function hasFloatsPast(): bool
    {
        return $this->floatsPast;
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
        $this->escapedFloats = $floats;
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
        return $this->escapedFloats;
    }

    /**
     * Lets the children of a container whose height is auto take percentage heights of the definite height of its
     * parent, minus its borders and padding, like <html> and <body> under the frame.
     */
    public function setPassesDefiniteHeight(bool $passes = true): ContainerWidget
    {
        if ($this->passesDefiniteHeight !== $passes) {
            $this->passesDefiniteHeight = $passes;
            $this->owner->invalidate();
        }

        return $this->owner;
    }

    /**
     * Draws a child in the top border, near its left corner, like the <legend> of a <fieldset>, instead of in the
     * flow; without a top border, it stays in the flow.
     */
    public function setLegend(?AbstractWidget $legend): ContainerWidget
    {
        if ($this->legend !== $legend) {
            $this->legend = $legend;
            $this->owner->invalidate();
        }

        return $this->owner;
    }

    public function getLegend(): ?AbstractWidget
    {
        return $this->legend;
    }

    /**
     * Lays out a wrapping row like text flows: a text that does not fit after the children before it takes the room
     * left on their line, wrapping there, rather than a line of its own, like the text after an image in a paragraph.
     */
    public function setInlineFlow(bool $flow = true): ContainerWidget
    {
        if ($this->inlineFlow !== $flow) {
            $this->inlineFlow = $flow;
            $this->owner->invalidate();
        }

        return $this->owner;
    }

    public function isInlineFlow(): bool
    {
        return $this->inlineFlow;
    }

    public function passesDefiniteHeight(): bool
    {
        return $this->passesDefiniteHeight;
    }
    private ContainerWidget $owner;
    private ?array $floatOwner = null;

    /**
     * @template T of object
     *
     * @param T $widget
     *
     * @return ($widget is ContainerWidget ? self : T)
     */
    public static function of(object $widget): object
    {
        if (!$widget instanceof ContainerWidget) {
            return $widget;
        }
        $state = $widget->getExtensionState(self::class);
        $state->owner = $widget;

        return $state;
    }

    private function markFloatsSet(): void
    {
        $parent = $this->owner->getParent();
        $this->floatOwner = null === $parent ? null : [\WeakReference::create($parent), $parent->getRenderRevision()];
    }

    private function floatsSetByLayout(): bool
    {
        return null !== $this->floatOwner && null !== ($parent = $this->floatOwner[0]->get()) && $parent === $this->owner->getParent() && $parent->getRenderRevision() === $this->floatOwner[1];
    }
}
