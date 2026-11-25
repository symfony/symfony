<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Paint;

use Symfony\Component\HtmlRenderer\Css\ColorValue;
use Symfony\Component\HtmlRenderer\Css\CurrentColor;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Css\ValueTokenizer;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Html\EmbeddedDocuments;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;
use Symfony\Component\HtmlRenderer\Layout\ScrollbarGeometry;
use Symfony\Component\HtmlRenderer\Text\CaretGeometry;

/**
 * @internal
 */
final class Painter
{
    private readonly TextMeasurerInterface $textMeasurer;

    public function __construct(?TextMeasurerInterface $textMeasurer = null, private readonly ?\Closure $embedded = null, private readonly ?LayoutMetricsInterface $layoutMetrics = null)
    {
        $this->textMeasurer = $textMeasurer ?? new MonospaceTextMeasurer();
    }

    public function paint(Fragment $fragment): DisplayList
    {
        $operations = $activeClips = $filters = [];
        foreach (PaintOrder::build($fragment) as $step) {
            $this->clips($activeClips, $step['clips'], $operations);
            $current = $step['fragment'];
            $opacity = max(0.0, min(1.0, (float) ($current->style['opacity'] ?? 1)));
            $filter = null;
            if (\in_array($step['type'], ['context-enter', 'context-exit'], true) && 'none' !== ($value = $current->style['filter'] ?? 'none')) {
                if (!\array_key_exists($value, $filters)) {
                    [$colors] = Filter::split($value);
                    $filters[$value] = null !== $colors ? Filter::parse($colors) : null;
                }
                $filter = $filters[$value];
            }
            if ('context-enter' === $step['type']) {
                if (null !== $current->transform) {
                    $operations[] = ['type' => 'push-transform', 'matrix' => $current->transform];
                }
                if ($opacity < 1) {
                    $operations[] = ['type' => 'push-opacity', 'opacity' => $opacity];
                }
                if (null !== $filter) {
                    $operations[] = ['type' => 'push-filter', 'functions' => $filter->functions, 'x' => $current->bounds->x, 'y' => $current->bounds->y, 'width' => $current->bounds->width, 'height' => $current->bounds->height];
                }
            } elseif ('context-exit' === $step['type']) {
                if (null !== $filter) {
                    $operations[] = ['type' => 'pop-filter'];
                }
                if ($opacity < 1) {
                    $operations[] = ['type' => 'pop-opacity'];
                }
                if (null !== $current->transform) {
                    $operations[] = ['type' => 'pop-transform'];
                }
            } elseif ('hidden' !== ($current->style['visibility'] ?? 'visible')) {
                if ('background' === $step['type']) {
                    $this->background($current, $operations);
                } elseif ('outline' === $step['type']) {
                    $this->outline($current, $operations);
                } elseif ('scrollbar' === $step['type']) {
                    $this->scrollbars($current, $operations);
                } else {
                    $this->content($current, $operations, $step['vertical-align'], $step['baseline-shift']);
                }
            }
        }
        $this->clips($activeClips, [], $operations);

        return new DisplayList($operations);
    }

    private function outline(Fragment $fragment, array &$operations): void
    {
        $style = $fragment->style;
        $x = (float) ($style['-renderer-outline-width-x'] ?? 0);
        $y = (float) ($style['-renderer-outline-width-y'] ?? 0);
        $offsetX = (float) ($style['-renderer-outline-offset-x'] ?? 0);
        $offsetY = (float) ($style['-renderer-outline-offset-y'] ?? 0);
        $bounds = OutlineGeometry::outer($fragment->bounds, $x, $y, $offsetX, $offsetY);
        if (null === $bounds) {
            return;
        }
        $color = $this->color($style['outline-color'] ?? 'currentcolor', $style['color'] ?? 'black');
        $lineStyle = 'auto' === ($style['outline-style'] ?? '') ? 'solid' : $style['outline-style'];
        $operation = ['type' => 'border', 'x' => $bounds->x, 'y' => $bounds->y, 'width' => $bounds->width, 'height' => $bounds->height, 'color' => $color, 'thickness' => max($x, $y), 'style' => $lineStyle];
        if (\in_array(strtolower($style['outline-width'] ?? ''), ['thin', 'medium', 'thick'], true)) {
            $operation['width-keyword'] = strtolower($style['outline-width']);
        }
        if ($x !== $y) {
            $operation['sides'] = [];
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $operation['sides'][$side] = ['thickness' => \in_array($side, ['left', 'right'], true) ? $x : $y, 'color' => $color, 'style' => $lineStyle];
            }
        }
        $operations[] = $operation;
    }

    private function scrollbars(Fragment $fragment, array &$operations): void
    {
        $scroll = $fragment->scroll;
        if (null === $scroll) {
            return;
        }
        $regions = ScrollbarGeometry::regions($fragment);
        $port = $scroll->port;
        $colors = [null, null];
        $tokens = ValueTokenizer::split($fragment->style['scrollbar-color'] ?? 'auto');
        if (2 === \count($tokens)) {
            foreach ($tokens as $index => $token) {
                $color = ColorValue::parse(CurrentColor::replace($token, $fragment->style['color'] ?? 'black'));
                $colors[$index] = \is_array($color) ? $color : null;
            }
        }
        $thin = 'thin' === ($fragment->style['scrollbar-width'] ?? 'auto');
        foreach ([false, true] as $vertical) {
            if ($vertical ? !$scroll->verticalBar : !$scroll->horizontalBar) {
                continue;
            }
            if (!isset($regions[$vertical ? 'y' : 'x'])) {
                continue;
            }
            $track = $regions[$vertical ? 'y' : 'x'];
            $extent = $vertical ? $track->height : $track->width;
            $viewport = $vertical ? $port->height : $port->width;
            $content = $viewport + ($vertical ? $scroll->maxY : $scroll->maxX);
            $length = ScrollbarGeometry::thumbLength($extent, $viewport, $content);
            $length = max(0.0, min($extent, $this->layoutMetrics?->constrainLength($length, 'scrollbar-thumb-length', $vertical) ?? $length));
            $offset = ScrollbarGeometry::thumbOffset($extent, $length, $viewport, $content, $vertical ? $scroll->offsetY : $scroll->offsetX);
            $offset = max(0.0, min($extent - $length, $this->layoutMetrics?->constrainLength($offset, 'scrollbar-thumb-offset', $vertical) ?? $offset));
            $thumb = $vertical ? new Rect($track->x, $track->y + $offset, $track->width, $length) : new Rect($track->x + $offset, $track->y, $length, $track->height);
            $rect = static fn (Rect $rect): array => ['x' => $rect->x, 'y' => $rect->y, 'width' => $rect->width, 'height' => $rect->height];
            $operations[] = ['type' => 'scrollbar', 'axis' => $vertical ? 'y' : 'x', 'track' => $rect($track), 'thumb' => $rect($thumb), 'thin' => $thin, 'thumb-color' => $colors[0], 'track-color' => $colors[1]];
        }
    }

    private function clips(array &$active, array $next, array &$operations): void
    {
        $common = 0;
        for ($count = min(\count($active), \count($next)); $common < $count && $active[$common]['owner'] === $next[$common]['owner']; ++$common) {
        }
        for ($index = \count($active) - 1; $index >= $common; --$index) {
            $operations[] = ['type' => 'pop-clip'];
        }
        for ($index = $common, $count = \count($next); $index < $count; ++$index) {
            $rect = $next[$index]['rect'];
            $operations[] = ['type' => 'push-clip', 'x' => $rect->x, 'y' => $rect->y, 'width' => $rect->width, 'height' => $rect->height, 'clip-x' => $next[$index]['clip-x'], 'clip-y' => $next[$index]['clip-y']];
        }
        $active = $next;
    }

    private function background(Fragment $fragment, array &$operations): void
    {
        $rect = $fragment->bounds;
        if ('table-border' === $fragment->pseudoElement) {
            $color = ColorValue::parse($fragment->style['color'] ?? 'black');
            $operations[] = ['type' => 'line', 'x1' => $rect->x, 'y1' => $rect->y, 'x2' => $rect->x + $rect->width, 'y2' => $rect->y + $rect->height, 'width' => (float) $fragment->style['-renderer-line-width'], 'style' => $fragment->style['border-style'], 'color' => \is_array($color) ? $color : [[0.0, 0.0, 0.0], 1.0], 'start' => array_map(static fn (string $bit): bool => '1' === $bit, str_split($fragment->style['-renderer-line-start'])), 'end' => array_map(static fn (string $bit): bool => '1' === $bit, str_split($fragment->style['-renderer-line-end']))];

            return;
        }
        $geometry = ['x' => $rect->x, 'y' => $rect->y, 'width' => $rect->width, 'height' => $rect->height];
        $style = $fragment->style;
        $color = $this->color($style['color'] ?? 'black', 'black');
        $clips = Selector::splitTopLevel($style['background-clip'] ?? 'border-box') ?: ['border-box'];
        $textMask = \in_array('text', array_map(strtolower(...), $clips), true) ? $this->textMask($fragment) : null;
        $layerCount = max(1, \count(Selector::splitTopLevel($style['background-image'] ?? $style['background'] ?? 'none')));
        $backgroundStart = \count($operations);
        $isolated = false;
        if ([] !== $fragment->backgrounds && isset($style['background-blend-mode']) && 'normal' !== strtolower(trim($style['background-blend-mode']))) {
            $modes = BlendMode::fromList($style['background-blend-mode']) ?? [BlendMode::Normal];
            $isolated = array_any($modes, static fn (BlendMode $mode): bool => BlendMode::Normal !== $mode);
        }
        $background = $this->color($style['background-color'] ?? $style['background'] ?? 'transparent', $color);
        if ('transparent' !== $background && 'none' !== $background && '' !== $background && !str_contains(strtolower($background), 'gradient(')) {
            $backgroundRect = $this->backgroundBox($fragment, $clips[($layerCount - 1) % \count($clips)]);
            $operations[] = ['type' => 'rectangle', 'x' => $backgroundRect->x, 'y' => $backgroundRect->y, 'width' => $backgroundRect->width, 'height' => $backgroundRect->height, 'color' => $background] + ('text' === strtolower(trim($clips[($layerCount - 1) % \count($clips)])) ? ['text-mask' => $textMask] : []);
        }
        foreach (array_reverse($fragment->backgrounds) as $layer) {
            $clip = $layer['clip'] ?? $clips[($layer['layer-index'] ?? 0) % \count($clips)];
            $clipRect = $this->backgroundBox($fragment, $clip);
            $clipped = $clipRect !== $fragment->bounds;
            if ($clipped) {
                $operations[] = ['type' => 'push-clip', 'x' => $clipRect->x, 'y' => $clipRect->y, 'width' => $clipRect->width, 'height' => $clipRect->height, 'clip-x' => true, 'clip-y' => true];
            }
            if ('image' === $layer['kind']) {
                $operations[] = ['type' => 'background-image', ...$geometry, 'data' => $layer['image']->data, 'mime-type' => $layer['image']->mimeType, 'pattern' => $layer['pattern'], 'color' => 'black', 'image-rendering' => $style['image-rendering'] ?? 'auto', 'image-orientation' => $style['image-orientation'] ?? 'from-image'];
            } else {
                $operations[] = ['type' => 'gradient', ...$geometry, 'gradient' => $layer['gradient'], 'pattern' => $layer['pattern']];
            }
            if ($isolated) {
                $operations[array_key_last($operations)]['blend-mode'] = $modes[($layer['layer-index'] ?? 0) % \count($modes)]->value;
            }
            if ('text' === strtolower(trim($clip))) {
                $operations[array_key_last($operations)]['text-mask'] = $textMask;
            }
            if ($clipped) {
                $operations[] = ['type' => 'pop-clip'];
            }
        }
        if ($isolated && \count($operations) > $backgroundStart) {
            $layers = array_splice($operations, $backgroundStart);
            $operations[] = ['type' => 'background-group', ...$geometry, 'layers' => $layers];
        }
        $sides = [];
        foreach (['top', 'right', 'bottom', 'left'] as $edge) {
            $borderColor = $this->color($style['border-'.$edge.'-color'] ?? $style['border-color'] ?? $color, $color);
            $borderStyle = $style['border-'.$edge.'-style'] ?? $style['border-style'] ?? 'solid';
            $thickness = \in_array($borderStyle, ['none', 'hidden'], true) ? 0.0 : max(0.0, (float) ($style['border-'.$edge.'-width'] ?? $style['border-width'] ?? 0));
            $sides[$edge] = ['thickness' => $thickness, 'color' => $borderColor, 'style' => $borderStyle];
        }
        if ('1' !== ($style['-renderer-collapsed-border'] ?? '0') && max(array_column($sides, 'thickness')) > 0) {
            $operation = ['type' => 'border', ...$geometry, 'color' => $sides['top']['color'], 'thickness' => max(array_column($sides, 'thickness')), 'style' => $sides['top']['style']];
            if (\count(array_unique(array_map(serialize(...), $sides))) > 1) {
                $operation['sides'] = $sides;
            }
            $radii = [];
            foreach (['top-left', 'top-right', 'bottom-right', 'bottom-left'] as $corner) {
                $pair = explode(' ', $style['border-'.$corner.'-radius'] ?? '0 0');
                $radii[] = [(float) $pair[0], (float) ($pair[1] ?? $pair[0])];
            }
            if (array_any($radii, static fn (array $radius): bool => $radius[0] > 0 && $radius[1] > 0)) {
                $operation['radii'] = $radii;
            }
            if (null !== $source = $fragment->borderImage?->source) {
                $operation['source'] = \is_array($source) ? ['type' => 'gradient', 'gradient' => $source] : ['type' => 'image/svg+xml' === $source->mimeType ? 'svg' : 'image', 'data' => $source->data, 'mime-type' => $source->mimeType, 'color' => $color];
                $operation['source-bounds'] = $geometry;
            }
            $operations[] = $operation;
        }
    }

    private function textMask(Fragment $fragment): array
    {
        $identity = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $root = $fragment->transform ?? $identity;
        $origin = Transform::inversePoint($root, 0.0, 0.0);
        $horizontal = Transform::inversePoint($root, 1.0, 0.0);
        $vertical = Transform::inversePoint($root, 0.0, 1.0);
        if (null === $origin || null === $horizontal || null === $vertical) {
            return [];
        }
        $inverse = [$horizontal[0] - $origin[0], $horizontal[1] - $origin[1], $vertical[0] - $origin[0], $vertical[1] - $origin[1], $origin[0], $origin[1]];
        $excluded = new \SplObjectStorage();
        $visit = static function (Fragment $current, bool $outside = false) use (&$visit, $excluded, $fragment): void {
            $outside = $outside || ($current !== $fragment && null !== $current->topLayer);
            if ($outside) {
                $excluded[$current] = true;
            }
            foreach ($current->children as $child) {
                $visit($child, $outside);
            }
        };
        $visit($fragment);
        $mask = [];
        foreach (PaintOrder::build($fragment) as $step) {
            $current = $step['fragment'];
            if ('content' !== $step['type'] || null === $current->text || isset($excluded[$current]) || 'hidden' === ($current->style['visibility'] ?? 'visible')) {
                continue;
            }
            $draw = [];
            $this->content($current, $draw, $step['vertical-align'], $step['baseline-shift']);
            foreach ($draw as $operation) {
                if ('text' !== $operation['type']) {
                    continue;
                }
                $operation['matrix'] = \Symfony\Component\HtmlRenderer\Vector\Geometry::multiply($inverse, $step['matrix']);
                $operation['clips'] = [];
                foreach ($step['clips'] as $clip) {
                    $rect = $clip['rect'];
                    $operation['clips'][] = ['x' => $rect->x, 'y' => $rect->y, 'width' => $rect->width, 'height' => $rect->height, 'clip-x' => $clip['clip-x'], 'clip-y' => $clip['clip-y'], 'matrix' => \Symfony\Component\HtmlRenderer\Vector\Geometry::multiply($inverse, $clip['matrix'])];
                }
                $mask[] = $operation;
            }
        }

        return $mask;
    }

    private function backgroundBox(Fragment $fragment, string $clip): Rect
    {
        return match (strtolower(trim($clip))) {
            'padding-box' => $fragment->getPaddingBox(),
            'content-box' => $fragment->getContentBox(),
            default => $fragment->bounds,
        };
    }

    private function content(Fragment $fragment, array &$operations, ?string $verticalAlign = null, float $baselineShift = 0.0): void
    {
        if (null !== $fragment->content) {
            array_push($operations, ...EmbeddedDocuments::place($fragment->content->displayList, $fragment->getContentBox(), $fragment->content->clipToBounds));
        }
        if (null === $fragment->pseudoElement && $fragment->node instanceof \Dom\Element && 'iframe' === strtolower($fragment->node->localName) && null !== $this->embedded && null !== $display = ($this->embedded)($fragment)) {
            array_push($operations, ...EmbeddedDocuments::place($display, $fragment->getContentBox()));
        }
        $rect = $fragment->bounds;
        $geometry = ['x' => $rect->x, 'y' => $rect->y, 'width' => $rect->width, 'height' => $rect->height];
        $style = $fragment->style;
        $color = $this->color($style['color'] ?? 'black', 'black');
        if (null !== $fragment->image) {
            $operations[] = ['type' => 'image/svg+xml' === $fragment->image->mimeType ? 'svg' : 'image', ...$geometry, 'data' => $fragment->image->data, 'mime-type' => $fragment->image->mimeType, 'color' => $color];
        }
        if (null !== $fragment->text) {
            $fill = $this->color($style['-webkit-text-fill-color'] ?? 'currentColor', $color);
            $thickness = $style['text-decoration-thickness'] ?? 'auto';
            $thickness = \in_array($thickness, ['auto', 'from-font'], true) ? $thickness : max(0.0, (float) $thickness);
            $operation = ['type' => 'text', ...$geometry, 'text' => $fragment->text, 'color' => $fill, 'font-family' => $style['font-family'] ?? 'sans-serif', 'font-size' => (float) ($style['font-size'] ?? 16), 'font-weight' => (int) ($style['font-weight'] ?? 400), 'font-style' => $style['font-style'] ?? 'normal', 'text-decoration-line' => $style['text-decoration-line'] ?? $style['text-decoration'] ?? 'none', 'text-decoration-style' => $style['text-decoration-style'] ?? 'solid', 'text-decoration-color' => $this->color($style['text-decoration-color'] ?? 'currentColor', $color), 'text-decoration-thickness' => $thickness, 'direction' => $style['direction'] ?? 'ltr', 'vertical-align' => $verticalAlign ?? 'baseline', 'baseline-shift' => $baselineShift, 'baseline' => $rect->y + (float) ($style['-renderer-baseline'] ?? (float) ($style['font-size'] ?? 16) * 0.8)];
            foreach (['letter-spacing', 'word-spacing'] as $property) {
                if (0.0 !== ($spacing = (float) ($style[$property] ?? 0))) {
                    $operation[$property] = $spacing;
                }
            }
            $this->text($fragment, $operation, $operations);
        }
    }

    private function text(Fragment $fragment, array $operation, array &$operations): void
    {
        $run = $fragment->run;
        $selection = $run?->selection;
        if (null === $selection) {
            if ('' !== $operation['text']) {
                $operations[] = $operation;
            }

            return;
        }
        preg_match_all('/\X/u', $operation['text'], $matches);
        $graphemes = $matches[0];
        $count = \count($graphemes);
        $font = new FontStyle($operation['font-family'], $operation['font-size'], $operation['height'], $operation['font-weight'], $operation['direction'], $operation['font-style'], $operation['letter-spacing'] ?? 0.0, $operation['word-spacing'] ?? 0.0);
        $prefixes = $run->caretPositions ?? [0 => 0.0, $count => $operation['width']];
        $at = function (int $offset) use (&$prefixes, $graphemes, $font): float {
            return $prefixes[$offset] ??= $this->textMeasurer->measure(implode('', \array_slice($graphemes, 0, $offset)), $font)->width;
        };
        $first = max(0, min($count, $run->toDisplay(min($selection->anchor, $selection->focus))));
        $last = max(0, min($count, $run->toDisplay(max($selection->anchor, $selection->focus))));
        $scheme = str_contains($fragment->style['color-scheme'] ?? '', 'dark') ? 'dark' : 'light';
        $computedColor = $this->color($fragment->style['color'] ?? $operation['color'], $operation['color']);
        $foreground = $this->selectionColor($selection->foreground, $scheme, $computedColor);
        $background = $this->selectionColor($selection->background, $scheme, $computedColor);
        if ($first < $last) {
            $operations[] = ['type' => 'rectangle', 'x' => $operation['x'] + min($at($first), $at($last)), 'y' => $operation['y'], 'width' => abs($at($last) - $at($first)), 'height' => $operation['height'], 'color' => $background];
            foreach ([[0, $first, false], [$first, $last, true], [$last, $count, false]] as [$from, $to, $selected]) {
                if ($from < $to) {
                    $operations[] = array_replace($operation, ['x' => $operation['x'] + min($at($from), $at($to)), 'width' => abs($at($to) - $at($from)), 'text' => implode('', \array_slice($graphemes, $from, $to - $from)), 'color' => $selected ? $foreground : $operation['color']]);
                }
            }
        } elseif ('' !== $operation['text']) {
            $operations[] = $operation;
        }
        if (null !== $caret = CaretGeometry::bounds($fragment, $this->textMeasurer, $this->layoutMetrics)) {
            $color = $fragment->style['caret-color'] ?? $computedColor;
            $color = 'auto' === $color ? $computedColor : $this->selectionColor($color, $scheme, $computedColor);
            $operations[] = ['type' => 'caret', 'x' => $caret->x, 'y' => $caret->y, 'width' => $caret->width, 'height' => $caret->height, 'color' => $color];
        }
    }

    private function selectionColor(string $value, string $scheme, string $current): string
    {
        $resolved = ColorValue::parse($value, $scheme, static fn (string $color) => 'currentcolor' === $color ? ColorValue::parse($current, $scheme) : null);

        return \is_array($resolved) ? ColorValue::stringify($resolved) : $current;
    }

    private function color(string $color, string $current): string
    {
        return 'currentcolor' === strtolower($color) ? $current : $color;
    }
}
