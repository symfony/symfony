<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer;

use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;
use Symfony\Component\HtmlRenderer\Layout\ScrollbarGeometry;
use Symfony\Component\HtmlRenderer\Layout\ScrollGeometry;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\Paint\PaintOrder;
use Symfony\Component\HtmlRenderer\Paint\Transform;
use Symfony\Component\HtmlRenderer\Text\CaretGeometry;

final class RenderingResult
{
    private readonly \SplObjectStorage $boundsIndex;
    private readonly \SplObjectStorage $pseudoBoundsIndex;
    private readonly \SplObjectStorage $scrollIndex;
    private ?array $paintOrder = null;
    private readonly TextMeasurerInterface $textMeasurer;

    public function __construct(private readonly \Dom\HTMLDocument $document, private readonly \SplObjectStorage $styles, private readonly Fragment $layout, private readonly DisplayList $displayList, ?TextMeasurerInterface $textMeasurer = null, private readonly ?\Dom\Element $modal = null, private readonly ?LayoutMetricsInterface $layoutMetrics = null, private readonly ?\SplObjectStorage $composedParents = null)
    {
        $this->boundsIndex = new \SplObjectStorage();
        $this->pseudoBoundsIndex = new \SplObjectStorage();
        $this->scrollIndex = new \SplObjectStorage();
        $this->textMeasurer = $textMeasurer ?? new MonospaceTextMeasurer();
    }

    public function getDocument(): \Dom\HTMLDocument
    {
        return $this->document;
    }

    public function getComputedStyle(\Dom\Element $element): array
    {
        return $this->styles[$element] ?? [];
    }

    public function getLayout(): Fragment
    {
        return $this->layout;
    }

    public function getDisplayList(): DisplayList
    {
        return $this->displayList;
    }

    /** @return list<Rect> */
    public function getBounds(\Dom\Node $node, ?string $pseudoElement = null): array
    {
        if (!isset($this->boundsIndex[$this->document])) {
            $visit = function (Fragment $fragment) use (&$visit): void {
                if (null !== $fragment->node) {
                    if (null !== $fragment->scroll) {
                        $scroll = $this->scrollIndex[$fragment->node] ?? [];
                        $scroll[$fragment->pseudoElement ?? ''] = $fragment->scroll;
                        $this->scrollIndex[$fragment->node] = $scroll;
                    }
                    $bounds = $this->boundsIndex[$fragment->node] ?? [];
                    $bounds[] = $fragment->bounds;
                    $this->boundsIndex[$fragment->node] = $bounds;
                    if (null !== $fragment->pseudoElement) {
                        $pseudo = $this->pseudoBoundsIndex[$fragment->node] ?? [];
                        $pseudo[$fragment->pseudoElement][] = $fragment->bounds;
                        $this->pseudoBoundsIndex[$fragment->node] = $pseudo;
                    }
                }
                foreach ($fragment->children as $child) {
                    $visit($child);
                }
            };
            $visit($this->layout);
            if (!isset($this->boundsIndex[$this->document])) {
                $this->boundsIndex[$this->document] = [];
            }
        }

        return null === $pseudoElement ? ($this->boundsIndex[$node] ?? []) : (($this->pseudoBoundsIndex[$node] ?? [])[$pseudoElement] ?? []);
    }

    public function getScrollGeometry(\Dom\Element $element, ?string $pseudoElement = null): ?ScrollGeometry
    {
        $this->getBounds($element);

        return ($this->scrollIndex[$element] ?? [])[$pseudoElement ?? ''] ?? null;
    }

    /** @internal */
    public function getScrollOffsetToReveal(\Dom\Element $element, \Dom\Element $container, bool $caret = false, array $alignment = ['nearest', 'nearest']): ?array
    {
        $geometry = $this->getScrollGeometry($container);
        if (null === $geometry) {
            return null;
        }
        $matrix = null;
        $targets = [];
        foreach ($this->paintSteps() as $step) {
            $fragment = $step['fragment'];
            if ('background' === $step['type'] && $fragment->node === $container && null === $fragment->pseudoElement) {
                $matrix ??= $step['matrix'];
            }
            if (($caret ? 'content' : 'background') !== $step['type'] || null !== $fragment->pseudoElement || $caret && null === CaretGeometry::bounds($fragment, $this->textMeasurer, $this->layoutMetrics)) {
                continue;
            }
            if ($fragment->node === $element && array_any($step['clips'], static fn (array $clip): bool => $clip['owner']->node === $container)) {
                $targets[] = $step;
            }
        }
        if (null === $matrix || !$targets) {
            return null;
        }
        $step = $targets[$caret ? array_key_last($targets) : 0];
        $bounds = $caret ? CaretGeometry::bounds($step['fragment'], $this->textMeasurer, $this->layoutMetrics) : $step['fragment']->bounds;
        $target = $step['matrix'];
        $points = [];
        foreach ([[$bounds->x, $bounds->y], [$bounds->x + $bounds->width, $bounds->y], [$bounds->x, $bounds->y + $bounds->height], [$bounds->x + $bounds->width, $bounds->y + $bounds->height]] as [$x, $y]) {
            $point = Transform::inversePoint($matrix, $target[0] * $x + $target[2] * $y + $target[4], $target[1] * $x + $target[3] * $y + $target[5]);
            if (null === $point) {
                return null;
            }
            $points[] = $point;
        }
        $x = min(array_column($points, 0));
        $y = min(array_column($points, 1));
        $right = max(array_column($points, 0));
        $bottom = max(array_column($points, 1));

        return $this->getScrollOffsetToRevealBounds(new Rect($x, $y, $right - $x, $bottom - $y), $container, $alignment);
    }

    /**
     * @internal the bounds use the container's untransformed document coordinates, including current scroll translation
     *
     * @param array{"nearest"|"start", "nearest"|"start"} $alignment Physical x/y alignment
     */
    public function getScrollOffsetToRevealBounds(Rect $bounds, \Dom\Element $container, array $alignment = ['nearest', 'nearest']): ?array
    {
        if (null === $geometry = $this->getScrollGeometry($container)) {
            return null;
        }
        $x = $bounds->x;
        $y = $bounds->y;
        $right = $x + $bounds->width;
        $bottom = $y + $bounds->height;
        $port = $geometry->port;
        if (2 !== \count($alignment) || !\in_array($alignment[0] ?? null, ['nearest', 'start'], true) || !\in_array($alignment[1] ?? null, ['nearest', 'start'], true)) {
            throw new \InvalidArgumentException('Scroll alignment must specify nearest or start for both physical axes.');
        }
        $dx = 'start' === $alignment[0] ? $x - $port->x : self::nearestEdge($x, $right, $port->x, $port->x + $port->width);
        $dy = 'start' === $alignment[1] ? $y - $port->y : self::nearestEdge($y, $bottom, $port->y, $port->y + $port->height);

        return [max(0.0, min($geometry->maxX, $geometry->offsetX + $dx)), max(0.0, min($geometry->maxY, $geometry->offsetY + $dy))];
    }

    public function hitTest(float $x, float $y): ?\Dom\Element
    {
        return $this->getHitWitness($x, $y)['element'] ?? null;
    }

    /** @internal @return array{element: \Dom\Element, axis: string}|null */
    public function getScrollbarAt(float $x, float $y): ?array
    {
        $hit = $this->getHitWitness($x, $y);

        return null !== ($hit['axis'] ?? null) ? ['element' => $hit['element'], 'axis' => $hit['axis']] : null;
    }

    /** @internal @return array{element: \Dom\Element, action: string, disabled: bool}|null */
    public function getScrollControlAt(float $x, float $y): ?array
    {
        return $this->getHitWitness($x, $y)['control'] ?? null;
    }

    /** @internal One clipped and transformed pointer witness, including generated controls. */
    public function getHitWitness(float $x, float $y): ?array
    {
        foreach (array_reverse($this->paintSteps()) as $step) {
            if (!\in_array($step['type'], ['background', 'content', 'scrollbar'], true)) {
                continue;
            }
            $fragment = $step['fragment'];
            $point = $this->point($step, $x, $y);
            if (null === $point || 'hidden' === ($fragment->style['visibility'] ?? 'visible') || 'none' === ($fragment->style['pointer-events'] ?? 'auto') || !$fragment->bounds->contains($point[0], $point[1])) {
                continue;
            }
            $node = $fragment->node;
            $element = $node instanceof \Dom\Element ? $node : $node?->parentElement;
            if (null === $element || $element->ownerDocument !== $this->document || null !== $this->composedParents && !isset($this->composedParents[$element]) || $this->isInert($element) || null !== $this->modal && !$this->contains($this->modal, $element)) {
                continue;
            }
            if ('scrollbar' === $step['type']) {
                foreach (ScrollbarGeometry::regions($fragment) as $axis => $rect) {
                    if ($rect->contains($point[0], $point[1])) {
                        return ['element' => $element, 'axis' => $axis, 'control' => null];
                    }
                }
            } else {
                if ('scroll-marker' === $fragment->pseudoElement) {
                    for ($owner = $this->parent($element); null !== $owner; $owner = $this->parent($owner)) {
                        if (null !== $this->getScrollGeometry($owner)) {
                            return ['element' => $element, 'axis' => null, 'control' => ['element' => $owner, 'target' => $element, 'action' => 'marker', 'disabled' => false]];
                        }
                    }
                }
                $action = Html\ScrollControls::action($fragment->pseudoElement);
                $control = null === $action ? null : ['element' => $element, 'action' => $action, 'disabled' => !Html\ScrollControls::available($this->getScrollGeometry($element), $action)];

                return ['element' => $element, 'axis' => null, 'control' => $control];
            }
        }

        return null;
    }

    private function parent(\Dom\Element $element): ?\Dom\Element
    {
        if (null === $this->composedParents) {
            return $element->parentElement;
        }
        $parent = $this->composedParents[$element] ?? null;

        return $parent instanceof \Dom\Element ? $parent : null;
    }

    private function contains(\Dom\Element $ancestor, \Dom\Element $element): bool
    {
        for ($depth = 0; null !== $element && $depth <= 512; ++$depth, $element = $this->parent($element)) {
            if ($element === $ancestor) {
                return true;
            }
        }

        return false;
    }

    private function isInert(\Dom\Element $element): bool
    {
        for ($depth = 0; null !== $element && $depth <= 512; ++$depth, $element = $this->parent($element)) {
            if ($element->hasAttribute('inert')) {
                return true;
            }
        }

        return false;
    }

    /** @internal Tests the clipping of embedded painting, including overflow outside its flow bounds. */
    public function isContentPointVisible(\Dom\Element $element, float $x, float $y): bool
    {
        if (!is_finite($x) || !is_finite($y) || $element->ownerDocument !== $this->document || $this->isInert($element)) {
            return false;
        }
        foreach ($this->paintSteps() as $step) {
            $fragment = $step['fragment'];
            if ('content' !== $step['type'] || $fragment->node !== $element || null === $fragment->content || 'hidden' === ($fragment->style['visibility'] ?? 'visible') || 'none' === ($fragment->style['pointer-events'] ?? 'auto')) {
                continue;
            }
            if (null !== $point = $this->point($step, $x, $y)) {
                if (!$fragment->content->clipToBounds || $fragment->getContentBox()->contains($point[0], $point[1])) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @internal Maps viewport coordinates into an embedded document's content viewport. */
    public function getContentPoint(\Dom\Element $element, float $x, float $y, bool $captured = false): ?array
    {
        foreach (array_reverse($this->paintSteps()) as $step) {
            $fragment = $step['fragment'];
            if ($fragment->node !== $element || null !== $fragment->pseudoElement) {
                continue;
            }
            $point = $captured ? Transform::inversePoint($step['matrix'], $x, $y) : $this->point($step, $x, $y);
            $content = $fragment->getContentBox();
            if (null !== $point && ($captured || $content->contains($point[0], $point[1]))) {
                return [$point[0] - $content->x, $point[1] - $content->y];
            }
        }

        return null;
    }

    public function getTextPosition(\Dom\Element $element, float $x, float $y, bool $captured = false): ?int
    {
        if (!is_finite($x) || !is_finite($y)) {
            return null;
        }
        $best = $rank = null;
        foreach ($this->paintSteps() as $index => $step) {
            $fragment = $step['fragment'];
            if ('content' !== $step['type'] || null !== $fragment->pseudoElement || null === $fragment->run || null === $fragment->text || 'hidden' === ($fragment->style['visibility'] ?? 'visible') || 'none' === ($fragment->style['pointer-events'] ?? 'auto')) {
                continue;
            }
            $owner = $fragment->node instanceof \Dom\Element ? $fragment->node : $fragment->node?->parentElement;
            if ($owner !== $element || null === $point = ($captured ? Transform::inversePoint($step['matrix'], $x, $y) : $this->point($step, $x, $y))) {
                continue;
            }
            $bounds = $fragment->bounds;
            $dx = max($bounds->x - $point[0], $point[0] - $bounds->x - $bounds->width, 0.0);
            $dy = max($bounds->y - $point[1], $point[1] - $bounds->y - $bounds->height, 0.0);
            $current = [$dy, $dx, -$index];
            if (null === $rank || $current < $rank) {
                $rank = $current;
                $best = [$fragment, $point[0] - $bounds->x];
            }
        }
        if (null === $best) {
            return null;
        }
        [$fragment, $x] = $best;
        preg_match_all('/\X/u', $fragment->text, $matches);
        $graphemes = $matches[0];
        $count = \count($graphemes);
        $style = $fragment->style;
        $font = new FontStyle($style['font-family'] ?? 'sans-serif', (float) ($style['font-size'] ?? 16), $fragment->bounds->height, (int) ($style['font-weight'] ?? 400), $style['direction'] ?? 'ltr', $style['font-style'] ?? 'normal', (float) ($style['letter-spacing'] ?? 0), (float) ($style['word-spacing'] ?? 0));
        $positions = $fragment->run->caretPositions ?? $this->textMeasurer->measure($fragment->text, $font)->caretPositions;
        if (null !== $positions && \count($positions) === $count + 1) {
            $offset = 0;
            $distance = \INF;
            foreach ($positions as $index => $position) {
                if (($current = abs($position - $x)) <= $distance) {
                    $distance = $current;
                    $offset = $index;
                }
            }

            return $fragment->run->toSource($offset);
        }
        $prefixes = [0 => 0.0, $count => $fragment->bounds->width];
        $width = function (int $offset) use (&$prefixes, $graphemes, $font): float {
            return $prefixes[$offset] ??= $this->textMeasurer->measure(implode('', \array_slice($graphemes, 0, $offset)), $font)->width;
        };
        $low = 0;
        $high = $count;
        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $width($middle) < $x ? $low = $middle + 1 : $high = $middle;
        }
        $before = max(0, $low - 1);
        $offset = $x - $width($before) < $width($low) - $x ? $before : $low;

        return $fragment->run->toSource($offset);
    }

    private static function nearestEdge(float $start, float $end, float $portStart, float $portEnd): float
    {
        if ($start < $portStart && $end > $portEnd || $start >= $portStart && $end <= $portEnd) {
            return 0.0;
        }
        $larger = $end - $start > $portEnd - $portStart;
        if ($start < $portStart) {
            return $larger ? $end - $portEnd : $start - $portStart;
        }

        return $larger ? $start - $portStart : $end - $portEnd;
    }

    private function paintSteps(): array
    {
        return $this->paintOrder ??= PaintOrder::build($this->layout);
    }

    private function point(array $step, float $x, float $y): ?array
    {
        $point = Transform::inversePoint($step['matrix'], $x, $y);
        if (null === $point) {
            return null;
        }
        foreach ($step['clips'] as $clip) {
            $bounds = $clip['rect'];
            $local = Transform::inversePoint($clip['matrix'], $x, $y);
            if (null === $local || $clip['clip-x'] && ($local[0] < $bounds->x || $local[0] >= $bounds->x + $bounds->width) || $clip['clip-y'] && ($local[1] < $bounds->y || $local[1] >= $bounds->y + $bounds->height)) {
                return null;
            }
        }

        return $point;
    }
}
