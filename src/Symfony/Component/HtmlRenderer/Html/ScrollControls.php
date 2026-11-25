<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Html;

use Symfony\Component\HtmlRenderer\Layout\Box;

/** @internal Anonymous formatting boxes for controls around a scrolling viewport. */
final class ScrollControls
{
    /** @param array<string, string> $style */
    public static function hostsControls(array $style): bool
    {
        return !\in_array($style['display'] ?? 'block', ['none', 'inline', 'contents', 'ruby', 'ruby-base', 'ruby-text', 'ruby-pair'], true)
            && (\in_array($style['overflow-x'] ?? $style['overflow'] ?? 'visible', ['auto', 'scroll'], true) || \in_array($style['overflow-y'] ?? $style['overflow'] ?? 'visible', ['auto', 'scroll'], true));
    }

    /** @param array<string, string> $style */
    public static function hostsMarkerGroup(array $style): bool
    {
        return self::hostsControls($style) && \in_array($style['scroll-marker-group'] ?? 'none', ['before', 'after'], true);
    }

    /** @param array<string, Box> $buttons */
    public static function compose(Box $box, array $buttons, ?Box $group = null, string $position = 'after'): Box
    {
        if (!$buttons && null === $group) {
            return $box;
        }
        $inner = $box->style;
        foreach (array_keys($inner) as $property) {
            if (preg_match('/^(?:margin|padding|border|background|outline)(?:-|$)/', $property) || \in_array($property, ['width', 'height', 'min-width', 'min-height', 'max-width', 'max-height', 'position', 'inset', 'top', 'right', 'bottom', 'left', 'z-index', 'transform', 'translate', 'rotate', 'scale', 'opacity', 'filter', 'mix-blend-mode', 'clip-path', 'contain', 'container-type', 'container-name', 'float', 'clear', 'order', 'align-self', 'flex', 'flex-grow', 'flex-shrink', 'flex-basis'], true) || str_starts_with($property, 'grid-column') || str_starts_with($property, 'grid-row')) {
                unset($inner[$property]);
            }
        }
        $inner = array_replace($inner, ['width' => 'auto', 'height' => 'auto', 'min-width' => '0', 'min-height' => '0', 'flex-grow' => '1', 'flex-shrink' => '1', 'flex-basis' => 'auto', 'position' => 'static']);
        $content = new Box($box->node, $box->display, $inner, $box->children, $box->text, $box->image, $box->selection);
        $children = [];
        if (isset($buttons['up'])) {
            $children[] = self::button($buttons['up'], ['align-self' => 'flex-start']);
        }
        if (null !== $group && 'before' === $position) {
            $children[] = self::button($group, ['display' => $group->display]);
        }
        $children[] = $content;
        if (null !== $group && 'after' === $position) {
            $children[] = self::button($group, ['display' => $group->display]);
        }
        $footer = [];
        foreach (['down', 'left', 'right'] as $direction) {
            if (isset($buttons[$direction])) {
                $footer[] = self::button($buttons[$direction], 'right' === $direction ? ['margin-left' => 'auto'] : []);
            }
        }
        if ($footer) {
            $children[] = new Box(null, 'flex', ['display' => 'flex', 'flex-direction' => 'row', 'column-gap' => '1ch', 'flex-grow' => '0', 'flex-shrink' => '0'], $footer);
        }
        $display = str_starts_with($box->display, 'inline') ? 'inline-flex' : 'flex';
        $outer = array_replace($box->style, ['display' => $display, 'overflow' => 'visible', 'overflow-x' => 'visible', 'overflow-y' => 'visible', 'flex-direction' => 'column', 'flex-wrap' => 'nowrap', 'justify-content' => 'flex-start', 'align-items' => 'stretch', 'row-gap' => '0', 'column-gap' => '0', 'gap' => '0']);

        return new Box($box->node, $display, $outer, $children, null, null, null, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries);
    }

    public static function pageSize(float $extent): float
    {
        return max(0.0, $extent) * .85;
    }

    public static function action(?string $role): ?string
    {
        return match ($role) {
            'scroll-button(up)' => 'up', 'scroll-button(down)' => 'down',
            'scroll-button(left)' => 'left', 'scroll-button(right)' => 'right',
            default => null,
        };
    }

    public static function available(?\Symfony\Component\HtmlRenderer\Layout\ScrollGeometry $geometry, string $direction): bool
    {
        return null !== $geometry && match ($direction) {
            'up' => $geometry->offsetY > 0,
            'down' => $geometry->offsetY < $geometry->maxY,
            'left' => $geometry->offsetX > 0,
            'right' => $geometry->offsetX < $geometry->maxX,
            default => false,
        };
    }

    private static function button(Box $box, array $extra): Box
    {
        $style = array_replace($box->style, ['display' => 'block', 'flex-grow' => '0', 'flex-shrink' => '0'], $extra);

        return new Box($box->node, $style['display'], $style, $box->children, $box->text, $box->image, $box->selection, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries);
    }
}
