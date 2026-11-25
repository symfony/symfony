<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Vector;

use Symfony\Component\HtmlRenderer\Css\Declaration;
use Symfony\Component\HtmlRenderer\Css\DeclarationParser;

/** @internal */
final class SvgStyle
{
    private static array $serializedProperties;

    public const INITIAL = ['fill' => 'black', 'stroke' => 'none', 'stroke-width' => '1', 'fill-opacity' => '1', 'stroke-opacity' => '1', 'fill-rule' => 'nonzero', 'visibility' => 'visible', 'color' => 'currentcolor', 'opacity' => '1', 'stroke-dasharray' => 'none', 'stroke-dashoffset' => '0', 'stroke-linecap' => 'butt', 'stroke-linejoin' => 'miter', 'stroke-miterlimit' => '4', 'paint-order' => 'normal'];
    public const GEOMETRY = ['rect' => ['x', 'y', 'width', 'height', 'rx', 'ry'], 'circle' => ['cx', 'cy', 'r'], 'ellipse' => ['cx', 'cy', 'rx', 'ry'], 'path' => ['d'], 'text' => ['x', 'y']];

    public const INHERITED = ['fill', 'stroke', 'stroke-width', 'fill-opacity', 'stroke-opacity', 'fill-rule', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'marker-start', 'marker-mid', 'marker-end', 'paint-order', 'clip-rule', 'text-anchor'];

    public static function computedValues(array $computed): array
    {
        return array_intersect_key($computed, self::$serializedProperties ??= array_flip([...SvgDocument::STYLE_PROPERTIES, ...SvgDocument::GEOMETRY_PROPERTIES]));
    }

    /** The original inline declarations, overridden by resolved values for SVG. */
    public static function serializedStyle(\Dom\Element $element, array $computed): array
    {
        $values = self::computedValues($computed);
        foreach ($values as $property => $value) {
            if (!\is_string($value)) {
                continue;
            }
            if (preg_match('/^-?[a-z_][a-z0-9_-]*$/iD', $property) && !preg_match('/[^\x20-\x7e]/', $value) && false === strpbrk($value, ";!\"'\\/{}[]()")) {
                $values[$property] = trim($value);
                continue;
            }
            $declarations = DeclarationParser::parse($property.':'.$value.';');
            if (1 !== \count($declarations) || $declarations[0]->property !== $property || $declarations[0]->important) {
                unset($values[$property]);
            } else {
                $values[$property] = $declarations[0]->value;
            }
        }
        if (!$values) {
            return [];
        }
        $inline = [];
        foreach (DeclarationParser::parse($element->getAttribute('style') ?? '') as $declaration) {
            $inline[$declaration->property] = $declaration->value;
        }

        return array_replace($inline, $values);
    }

    /**
     * SVG painting defaults, leaving ordinary CSS defaults to the CSS resolver.
     */
    public static function initial(string $property): ?string
    {
        if (\in_array($property, ['color', 'font-size', 'display'], true)) {
            return null;
        }

        return self::INITIAL[$property] ?? ['text-anchor' => 'start', 'marker' => 'none', 'marker-start' => 'none', 'marker-mid' => 'none', 'marker-end' => 'none', 'stop-color' => 'black', 'stop-opacity' => '1', 'flood-color' => 'black', 'flood-opacity' => '1', 'clip-path' => 'none', 'clip-rule' => 'nonzero', 'mask' => 'none', 'mask-type' => 'luminance', 'filter' => 'none'][$property] ?? null;
    }

    /**
     * Presentation attributes enter the author cascade with zero specificity.
     * https://www.w3.org/TR/SVG2/styling.html#PresentationAttributes.
     *
     * @return list<Declaration>
     */
    public static function presentation(\Dom\Element $element): array
    {
        if ('http://www.w3.org/2000/svg' !== $element->namespaceURI) {
            return [];
        }
        $declarations = [];
        foreach ([...SvgDocument::STYLE_PROPERTIES, ...SvgDocument::GEOMETRY_PROPERTIES] as $property) {
            if ($element->hasAttribute($property)) {
                $value = $element->getAttribute($property);
                if ('svg' === $element->localName && \in_array($property, ['width', 'height'], true) && is_numeric(trim($value))) {
                    $value = trim($value).'px';
                }
                $declarations[] = new Declaration($property, $value, false);
            }
        }

        return $declarations;
    }

    /**
     * The style inherited while drawing a group, with its compositing opacity.
     *
     * @param array<string, string> $own
     * @param array<string, string> $parent
     *
     * @return array<string, string>
     */
    public static function inherit(array $own, array $parent): array
    {
        $properties = ['opacity' => (string) ((float) ($parent['opacity'] ?? 1) * self::fraction($own['opacity'] ?? '1'))] + $own + $parent;
        unset($properties['clip-path'], $properties['mask'], $properties['filter']);

        return $properties;
    }

    /**
     * The inheritable paint of the ancestors of a marker or a referenced shape.
     *
     * @param array<string, string> $own
     * @param array<string, string> $parent
     *
     * @return array<string, string>
     */
    public static function inheritedPaint(array $own, array $parent): array
    {
        return array_diff_key($own, ['opacity' => 0, 'display' => 0, 'clip-path' => 0, 'mask' => 0, 'filter' => 0]) + $parent;
    }

    /** @return array<string, string> */
    public static function properties(\Dom\Element $element, ?\Closure $cascade = null): array
    {
        $properties = self::declared($element, ['fill', 'stroke', 'stroke-width', 'fill-opacity', 'stroke-opacity', 'fill-rule', 'opacity', 'display', 'visibility', 'color', 'font-size', 'text-anchor', 'dominant-baseline', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'marker', 'marker-start', 'marker-mid', 'marker-end', 'paint-order', 'clip-path', 'clip-rule', 'mask', 'mask-type', 'filter', 'flood-color', 'flood-opacity'], $cascade);
        // The shorthand sets the markers the element does not set one by one
        if (isset($properties['marker'])) {
            foreach (['marker-start', 'marker-mid', 'marker-end'] as $name) {
                $properties[$name] ??= $properties['marker'];
            }
            unset($properties['marker']);
        }

        return $properties;
    }

    /**
     * The values of some properties an element sets: from the cascade of the page, else from its presentation
     * attributes, then the declarations of its style attribute, which win.
     *
     * @param list<string> $names
     *
     * @return array<string, string>
     */
    public static function declared(\Dom\Element $element, array $names, ?\Closure $cascade = null): array
    {
        if (null !== $cascade) {
            $properties = [];
            foreach (($cascade)($element, $names) as $name => $value) {
                $value = trim((string) preg_replace('/\s*!important\s*$/i', '', $value));
                // initial is the value of SVG, unset and inherit the value around it but for opacity and display, which are not inherited
                $keyword = strtolower($value);
                if ('initial' === $keyword || 'unset' === $keyword && \in_array($name, ['opacity', 'display', 'stop-color', 'stop-opacity'], true)) {
                    $value = self::INITIAL[$name] ?? ['display' => 'inline', 'stop-color' => 'black', 'stop-opacity' => '1', 'font-size' => '16', 'text-anchor' => 'start'][$name] ?? '';
                } elseif (\in_array($keyword, ['unset', 'revert', 'revert-layer'], true)) {
                    continue;
                }
                $properties[$name] = $value;
            }

            return array_filter($properties, static fn (string $value) => '' !== $value && 'inherit' !== strtolower($value));
        }
        $properties = [];
        foreach ($names as $name) {
            if (null !== $value = $element->getAttribute($name)) {
                $properties[$name] = trim($value);
            }
        }
        $wanted = array_flip($names);
        foreach (DeclarationParser::parse($element->getAttribute('style') ?? '') as $declaration) {
            if (isset($wanted[$declaration->property])) {
                $properties[$declaration->property] = $declaration->value;
            }
        }

        // inherit takes the value around it, like no value
        return array_filter($properties, static fn (string $value) => '' !== $value && 'inherit' !== strtolower($value));
    }

    public static function geometryDeclared(\Dom\Element $element, string $tag, ?\Closure $cascade = null): array
    {
        if (!isset(self::GEOMETRY[$tag]) || null === $cascade && !$element->hasAttribute('style')) {
            return [];
        }
        $declared = [];
        foreach (self::GEOMETRY[$tag] as $name) {
            // Without a cascade, declared() reads the attributes too: only those of the style attribute differ
            if (null === $cascade && !preg_match('/(?:^|;)\s*'.$name.'\s*:/i', $element->getAttribute('style') ?? '')) {
                continue;
            }
            if (null === $value = self::declared($element, [$name], $cascade)[$name] ?? null) {
                continue;
            }
            if ('d' === $name) {
                if ('none' === strtolower($value)) {
                    $declared['d'] = '';
                } elseif (preg_match('/^path\(\s*(["\'])([^"\']*)\1\s*\)$/i', $value, $m)) {
                    $declared['d'] = $m[2];
                }
            } elseif (preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?(?:%|px|em|rem|pt|pc|in|cm|mm)?$/i', $value) || \in_array($name, ['rx', 'ry'], true) && 'auto' === strtolower($value)) {
                $declared[$name] = $value;
            }
        }

        return $declared;
    }

    public static function fraction(string $value): float
    {
        $value = trim($value);
        if (!is_numeric($number = rtrim($value, '%'))) {
            return 1.0;
        }

        return max(0.0, min(1.0, (float) $number / (str_ends_with($value, '%') ? 100 : 1)));
    }
}
