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

/** @internal */
final class SvgDocument
{
    public const STYLE_PROPERTIES = ['fill', 'stroke', 'stroke-width', 'fill-opacity', 'stroke-opacity', 'fill-rule', 'opacity', 'display', 'visibility', 'color', 'stop-color', 'stop-opacity', 'font-size', 'text-anchor', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'marker', 'marker-start', 'marker-mid', 'marker-end', 'paint-order', 'clip-path', 'clip-rule', 'mask', 'mask-type', 'filter', 'flood-color', 'flood-opacity'];
    public const GEOMETRY_PROPERTIES = ['x', 'y', 'width', 'height', 'cx', 'cy', 'r', 'rx', 'ry', 'd'];

    private const MAX_LENGTH = 100000.0;
    private const UNITS = ['px' => 1.0, 'em' => 16.0, 'rem' => 16.0, 'pt' => 4 / 3, 'pc' => 16.0, 'in' => 96.0, 'cm' => 96 / 2.54, 'mm' => 96 / 25.4];

    public static function parse(string $data): ?\Dom\Element
    {
        if (!preg_match('/^\s*(?:<\?xml[^>]*>\s*)?(?:<!--.*?-->\s*|<!DOCTYPE[^>]*>\s*)*<svg\b/is', $data)) {
            return null;
        }
        try {
            $document = \Dom\XMLDocument::createFromString($data, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING);
        } catch (\Throwable) {
            return null;
        }
        if (null === ($root = $document->documentElement) || 'svg' !== strtolower($root->localName) || '' !== trim($document->doctype->internalSubset ?? '')) {
            return null;
        }

        return $root;
    }

    /** @return array{array{float, float, float, float}, float, float}|null */
    public static function viewport(\Dom\Element $svg): ?array
    {
        SvgNames::normalize($svg);
        $box = Geometry::numbers($svg->getAttribute('viewBox') ?? '');
        $box = 4 === \count($box) && $box[2] > 0 && $box[3] > 0 ? $box : null;
        $width = self::size($svg->getAttribute('width'));
        $height = self::size($svg->getAttribute('height'));
        if (null !== $box) {
            $width ??= null !== $height ? $height * $box[2] / $box[3] : $box[2];
            $height ??= $width * $box[3] / $box[2];
        }
        $width ??= 300.0;
        $height ??= 150.0;
        if (!($width > 0) || !($height > 0)) {
            return null;
        }
        $width = max(0.01, min(self::MAX_LENGTH, $width));
        $height = max(0.01, min(self::MAX_LENGTH, $height));

        return [$box ?? [0.0, 0.0, $width, $height], $width, $height];
    }

    private static function size(?string $value): ?float
    {
        if (null === $value || !preg_match('/^\s*(\d*\.?\d+)\s*(px|em|rem|pt|pc|in|cm|mm)?\s*$/i', $value, $match)) {
            return null;
        }
        $unit = strtolower($match[2] ?? 'px') ?: 'px';
        $factor = self::UNITS[$unit] ?? null;
        if (null === $factor) {
            return null;
        }
        $size = (float) $match[1] * $factor;

        return $size > 0 && $size <= self::MAX_LENGTH ? $size : null;
    }
}
