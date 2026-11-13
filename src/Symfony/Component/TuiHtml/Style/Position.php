<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

/**
 * How a widget is positioned, like CSS position with its insets and z-index.
 *
 * A relative widget stays in the flow and is the containing block of the
 * absolute widgets inside it. Absolute and fixed widgets leave the flow:
 * they are drawn over the others, at their insets from their containing
 * block (the closest positioned ancestor, or the screen for fixed ones),
 * by z-index then in document order among those of their closest stacking
 * context, like in CSS. With both horizontal insets, an auto margin
 * centers a widget of a given width between them; with both vertical insets
 * too, it is centered between them as well, like a modal dialog.
 *
 * @experimental
 */
final class Position
{
    /**
     * @param array<'top'|'right'|'bottom'|'left', int|PercentLength>                                      $percents     The insets in percents of the containing block, or sizes mixing them with cells, for the sides without one in cells
     * @param string|null                                                                                  $anchor       The anchor-name it is placed next to, like position-anchor
     * @param array{string, string}|null                                                                   $area         Where next to its anchor, block then inline, like position-area: bottom span-right
     * @param array<'top'|'right'|'bottom'|'left', array{?string, 'top'|'right'|'bottom'|'left'|'center'}> $anchorEdges  The insets at an edge of an anchor, like top: anchor(bottom): the anchor name, null for the default one, and the edge
     * @param list<'flip-block'|'flip-inline'>                                                             $flips        The other sides tried when it would not fit on the screen, like position-try-fallbacks
     * @param array{float, float}                                                                          $translate    A move in percents of its own width and height, like transform: translate(-50%, -50%)
     * @param array<string, array{?string, 'width'|'height', float, int, ?int}>                            $anchorSizes  The sizes after an anchor, like width: calc(anchor-size(width) + 2ch), by property (width, min-width, max-width, height, min-height, max-height): the anchor name, null for the default one, its dimension, a factor and an offset in cells, and the size without the anchor
     * @param list<self>                                                                                   $tries        The other places tried in order when it would not fit on the screen, from the @position-try rules and position-area values of position-try-fallbacks: their anchor, area and anchor edges
     * @param 'normal'|'most-width'|'most-height'                                                          $tryOrder     How its tries are sorted, like position-try-order: as listed, or by the room they leave on their side of the anchor, the widest or the tallest first
     * @param list<'anchors-valid'|'anchors-visible'|'no-overflow'>                                        $visibility   When it is hidden, like position-visibility: without its anchor, while its anchor is not drawn, or when it does not fit on the screen; [] for always
     * @param array{bool, bool}                                                                            $anchorCenter Whether it is centered on its anchor across and down, like justify-self and align-self: anchor-center
     */
    public function __construct(public readonly PositionMode $mode, public readonly ?int $top = null, public readonly ?int $right = null, public readonly ?int $bottom = null, public readonly ?int $left = null, public readonly int $zIndex = 0, public readonly array $percents = [], public readonly ?string $anchor = null, public readonly ?array $area = null, public readonly array $anchorEdges = [], public readonly array $flips = [], public readonly array $translate = [0.0, 0.0], public readonly array $anchorSizes = [], public readonly array $tries = [], public readonly array $visibility = ['anchors-visible'], public readonly string $tryOrder = 'normal', public readonly array $anchorCenter = [false, false])
    {
    }

    /**
     * @param array{float, float} $translate In percents of its own width and height
     */
    public function withTranslate(array $translate): self
    {
        return new self($this->mode, $this->top, $this->right, $this->bottom, $this->left, $this->zIndex, $this->percents, $this->anchor, $this->area, $this->anchorEdges, $this->flips, [max(-1000.0, min(1000.0, $translate[0])), max(-1000.0, min(1000.0, $translate[1]))], $this->anchorSizes, $this->tries, $this->visibility, $this->tryOrder, $this->anchorCenter);
    }

    /**
     * The move of its translation in cells, for a box of a size.
     *
     * @return array{int, int} Columns and rows
     */
    public function translation(int $columns, int $rows): array
    {
        // Half a cell rounds toward the end: -50% of one row stays on its row
        return [(int) floor($columns * $this->translate[0] / 100 + 0.5), (int) floor($rows * $this->translate[1] / 100 + 0.5)];
    }

    /**
     * The sizes after anchors in cells, like anchor-size(): from the rect of each anchor, the default one for null.
     *
     * @param \Closure(?string): ?array{int, int} $anchorSize The columns and rows of an anchor, null when it is not drawn
     *
     * @return array<string, int>
     */
    public function anchorSizes(\Closure $anchorSize): array
    {
        $sizes = [];
        foreach ($this->anchorSizes as $property => [$name, $dimension, $factor, $offset, $fallback]) {
            $anchor = $anchorSize($name);
            $cells = null !== $anchor ? (int) round($factor * $anchor['width' === $dimension ? 0 : 1]) + $offset : $fallback;
            if (null !== $cells) {
                $sizes[$property] = max(0, $cells);
            }
        }

        return $sizes;
    }

    /**
     * Whether it is placed next to an anchor, like position-anchor with position-area, anchor() or anchor-center.
     */
    public function isAnchored(): bool
    {
        return null !== $this->area || [] !== $this->anchorEdges || null !== $this->anchor && [false, false] !== $this->anchorCenter;
    }

    /**
     * Resolves an inset in cells, a percentage being of the containing block's width or height.
     *
     * @param 'top'|'right'|'bottom'|'left' $side
     */
    public function inset(string $side, int $containing): ?int
    {
        if (null !== $cells = $this->{$side}) {
            return $cells;
        }
        if (!isset($this->percents[$side])) {
            return null;
        }
        $percent = $this->percents[$side];

        return max(-10000, min(10000, $percent instanceof PercentLength ? (int) $percent->at($containing) : intdiv($containing * $percent, 100)));
    }

    /**
     * Whether the widget takes no room in the flow of its parent.
     */
    public function isOutOfFlow(): bool
    {
        return PositionMode::Relative !== $this->mode;
    }
}
