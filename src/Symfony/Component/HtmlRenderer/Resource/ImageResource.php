<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Resource;

use Symfony\Component\HtmlRenderer\Css\GeneratedContent;
use Symfony\Component\HtmlRenderer\Vector\SvgDocument;
use Symfony\Component\HtmlRenderer\Vector\SvgStyle;

final readonly class ImageResource
{
    public const MAX_PIXELS = 4194304;

    public function __construct(public string $data, public string $mimeType, public float $width, public float $height)
    {
        if (!is_finite($width) || !is_finite($height) || $width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Image dimensions must be finite and positive.');
        }
    }

    public static function fromString(string $data): ?self
    {
        if ('' === $data || \strlen($data) > ResourceLoader::MAX_BYTES) {
            return null;
        }
        if (null !== $svg = SvgDocument::parse($data)) {
            return self::fromSvg($svg);
        }
        $size = @getimagesizefromstring($data);
        if (false === $size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_PIXELS || !\in_array($size[2], [\IMAGETYPE_GIF, \IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_WEBP, \IMAGETYPE_BMP, \IMAGETYPE_AVIF], true)) {
            return null;
        }

        $rotated = ImageMetadata::orientation($data) >= 5;

        return new self($data, $size['mime'], (float) $size[$rotated ? 1 : 0], (float) $size[$rotated ? 0 : 1]);
    }

    public static function fromSvg(\Dom\Element $element, ?\Closure $cascade = null): ?self
    {
        if ('svg' !== strtolower($element->localName)) {
            return null;
        }
        $owner = $element->ownerDocument;
        if (!$owner instanceof \Dom\HTMLDocument && !$owner instanceof \Dom\XMLDocument) {
            return null;
        }
        $source = $owner instanceof \Dom\HTMLDocument ? $owner->saveHtml($element) : $owner->saveXml($element);
        $bytes = \strlen($source);
        if ($bytes > ResourceLoader::MAX_BYTES) {
            return null;
        }
        $document = \Dom\XMLDocument::createEmpty();
        $svg = $document->importNode($element, true);
        if (!$svg instanceof \Dom\Element) {
            return null;
        }
        $document->appendChild($svg);
        if (null !== $cascade) {
            $originals = [$element, ...iterator_to_array($element->getElementsByTagName('*'), false)];
            $copies = [$svg, ...iterator_to_array($svg->getElementsByTagName('*'), false)];
            foreach ($originals as $index => $original) {
                $computed = SvgStyle::computedValues($cascade($original));
                $added = 0;
                foreach ($computed as $name => $value) {
                    if (!\is_string($value) || \strlen($value) > ResourceLoader::MAX_BYTES - $bytes) {
                        return null;
                    }
                    $added += \strlen($name) + 2 + self::attributeBytes($value);
                    if ($added > ResourceLoader::MAX_BYTES - $bytes) {
                        return null;
                    }
                }
                $values = SvgStyle::serializedStyle($original, $computed);
                if (!$values) {
                    continue;
                }
                $style = '';
                foreach ($values as $name => $value) {
                    if (!\is_string($value) || \strlen($value) > ResourceLoader::MAX_BYTES - $bytes) {
                        return null;
                    }
                    $bytes += \strlen($name) + 2 + self::attributeBytes($value);
                    if ($bytes > ResourceLoader::MAX_BYTES) {
                        return null;
                    }
                    $style .= $name.':'.$value.';';
                }
                $copies[$index]->setAttribute('style', $style);
            }
        }
        if (null === $viewport = SvgDocument::viewport($svg)) {
            return null;
        }
        foreach ([$svg, ...iterator_to_array($svg->getElementsByTagName('*'), false)] as $node) {
            if (\in_array(strtolower($node->localName), ['script', 'style', 'foreignobject', 'image', 'feimage', 'animate', 'set', 'animatemotion', 'animatetransform', 'discard'])) {
                $node->remove();
                continue;
            }
            foreach (iterator_to_array($node->attributes, false) as $attribute) {
                $name = strtolower($attribute->localName);
                if ('base' === $name || str_starts_with($name, 'on') || 'href' === $name && !str_starts_with(trim($attribute->value), '#') || self::hasExternalReference($attribute->value)) {
                    $node->removeAttributeNode($attribute);
                }
            }
        }
        $data = $document->saveXml($svg);
        if (\strlen($data) > ResourceLoader::MAX_BYTES) {
            return null;
        }

        return new self($data, 'image/svg+xml', $viewport[1], $viewport[2]);
    }

    private static function attributeBytes(string $value): int
    {
        return \strlen($value) + 4 * substr_count($value, '&') + 3 * (substr_count($value, '<') + substr_count($value, '>')) + 5 * (substr_count($value, '"') + substr_count($value, "'")) + 4 * (substr_count($value, "\r") + substr_count($value, "\n")) + 3 * substr_count($value, "\t");
    }

    private static function hasExternalReference(string $value): bool
    {
        preg_match_all('/url\(([^)]*)\)/i', GeneratedContent::unescape($value), $matches);
        foreach ($matches[1] as $reference) {
            if (!str_starts_with(trim($reference, " \t\r\n\"'"), '#')) {
                return true;
            }
        }

        return false;
    }
}
