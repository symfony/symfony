<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Html\Element;

use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Html\ElementFactoryInterface;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\TuiHtml\Widget\ImageWidget;

/**
 * <picture>: the image of the first <source> whose media query matches, else its <img>.
 *
 * Each source that can be drawn is built like an <img> with its srcset (the
 * first candidate), through the factory of <img> and its loader; media
 * queries choose between them as they change, like the color scheme or the
 * width of the terminal. A source of a type that cannot be decoded is skipped.
 *
 * @experimental
 */
final class PictureElement implements ElementFactoryInterface
{
    /** On the <img> of a candidate read when it is first drawn, see create() */
    public const LAZY_ATTRIBUTE = 'data-tui-lazy';
    /** On the <img> of a candidate: the sources it is one of, and its rank among them, that the rules choosing it match */
    private const SOURCE_ATTRIBUTE = 'data-tui-source';

    /**
     * An <img srcset> drawn like a <picture> of one source: the candidate of its srcset for the density of the
     * terminal, chosen again as it changes; null when its srcset has fewer than two candidates.
     *
     * @internal
     */
    public static function fromSrcset(\Dom\Element $img, HtmlBuilder $builder): ?AbstractWidget
    {
        if (\count(self::srcsetCandidates($img->getAttribute('srcset') ?? '', $img->getAttribute('sizes') ?? '', $img->getAttribute('width'))) < 2) {
            return null;
        }
        $document = $img->ownerDocument;
        $picture = $document->createElement('picture');
        $source = $document->createElement('source');
        $picture->appendChild($source);
        $source->setAttribute('srcset', (string) $img->getAttribute('srcset'));
        $fallback = $document->createElement('img');
        $picture->appendChild($fallback);
        foreach ($img->attributes as $attribute) {
            if ('srcset' !== $attribute->name) {
                try {
                    $fallback->setAttribute($attribute->name, $attribute->value);
                } catch (\DOMException) {
                    // A name the HTML parser accepts but the DOM does not, like a"b: nothing reads it
                }
            }
        }

        return (new self())->create($picture, $builder);
    }

    /**
     * The candidates of a srcset, each with the condition that picks it, like browsers: the smallest density at least
     * that of the terminal, the densest one past them. A width descriptor (640w) is a density of the size the image is
     * drawn at (the px length of sizes, else its width attribute); without one, only the first candidate is kept.
     *
     * @return list<array{string, string}> The media condition ('' for the last) and the URL of each candidate, by density
     *
     * @internal
     */
    public static function srcsetCandidates(string $srcset, string $sizes = '', ?string $width = null): array
    {
        $parsed = [];
        $length = \strlen($srcset);
        for ($i = 0; $i < $length && \count($parsed) < 20;) {
            // The URL: up to white space, a comma ending it ends the candidate
            $i += strspn($srcset, " \t\n\r\f,", $i);
            $url = substr($srcset, $i, strcspn($srcset, " \t\n\r\f", $i));
            $i += \strlen($url);
            if ('' === $url) {
                break;
            }
            $descriptor = '';
            if (str_ends_with($url, ',')) {
                $url = rtrim($url, ',');
            } else {
                // The descriptors, up to the next comma outside parentheses
                $depth = 0;
                for (; $i < $length && (',' !== $srcset[$i] || $depth > 0); ++$i) {
                    $depth += '(' === $srcset[$i] ? 1 : (')' === $srcset[$i] ? -1 : 0);
                    $descriptor .= $srcset[$i];
                }
            }
            $parsed[] = [$url, strtolower(trim($descriptor))];
        }
        if (!$parsed) {
            return [];
        }
        // The size it is drawn at, in CSS pixels, for the width descriptors
        $slot = null;
        $entries = preg_split('/,(?![^(]*\))/', $sizes) ?: [''];
        $default = trim((string) end($entries));
        if (preg_match('/^(\d+(?:\.\d+)?)px$/i', $default, $m)) {
            $slot = (float) $m[1];
        } elseif (null !== $width && preg_match('/^\s*(\d+(?:\.\d+)?)\s*(?:px)?\s*$/i', $width, $m)) {
            $slot = (float) $m[1];
        }
        $densities = [];
        foreach ($parsed as [$url, $descriptor]) {
            if (preg_match('/^(\d+(?:\.\d+)?)x$/', $descriptor, $m)) {
                $density = (float) $m[1];
            } elseif (preg_match('/^(\d+)w$/', $descriptor, $m)) {
                if (null === $slot || $slot <= 0) {
                    return [['', $parsed[0][0]]];
                }
                $density = (int) $m[1] / $slot;
            } elseif ('' === $descriptor) {
                $density = 1.0;
            } else {
                continue;
            }
            // The first of a density wins
            $densities[(string) round($density, 4)] ??= [$density, $url];
        }
        usort($densities, static fn (array $a, array $b) => $a[0] <=> $b[0]);
        $candidates = [];
        foreach ($densities as $index => [$density, $url]) {
            $candidates[] = [$index < \count($densities) - 1 ? '(max-resolution: '.rtrim(rtrim(\sprintf('%.4F', $density), '0'), '.').'dppx)' : '', $url];
        }

        return $candidates;
    }

    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $picture = new ContainerWidget();
        $img = null;
        $sources = [];
        for ($child = $element->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
            $tagName = strtolower($child->localName);
            if ('img' === $tagName) {
                $img = $child;
                break;
            }
            if ('source' === $tagName) {
                $sources[] = $child;
            }
        }
        // The sources that can be drawn, with the media query that picks each: that of the <source>, and the density
        // of each candidate of its srcset; each marked by an attribute that the rules choosing it match, not a class
        // that the selectors of the page would see, like img:not([class])
        $name = hash('xxh64', serialize(array_map(static fn (\Dom\Element $source) => [$source->getAttribute('media'), $source->getAttribute('srcset')], $sources)));
        $candidates = [];
        foreach ($sources as $source) {
            $sourceMedia = trim($source->getAttribute('media') ?? '');
            // Only what a media query can hold goes into the rules: no braces nor separators
            if (\strlen($sourceMedia) > 500 || preg_match('/[{};@\\\\]/', $sourceMedia)) {
                continue;
            }
            // The first candidate of a source is read, a source that cannot be skipped; the others when they are drawn
            foreach (self::srcsetCandidates($source->getAttribute('srcset') ?? '', $source->getAttribute('sizes') ?? $img?->getAttribute('sizes') ?? '', $source->getAttribute('width') ?? $img?->getAttribute('width')) as $rank => [$condition, $src]) {
                $media = self::both($sourceMedia, $condition);
                $synthetic = $element->ownerDocument->createElement('img');
                foreach (null !== $img ? $img->attributes : [] as $attribute) {
                    if (!\in_array($attribute->name, ['id', 'src', 'srcset'], true)) {
                        try {
                            $synthetic->setAttribute($attribute->name, $attribute->value);
                        } catch (\DOMException) {
                            // A name the HTML parser accepts but the DOM does not, like a"b: nothing reads it
                        }
                    }
                }
                foreach (['width', 'height'] as $size) {
                    if (null !== $value = $source->getAttribute($size)) {
                        $synthetic->setAttribute($size, $value);
                    }
                }
                $synthetic->setAttribute('src', $src);
                if ($rank > 0) {
                    $synthetic->setAttribute(self::LAZY_ATTRIBUTE, '');
                }
                $synthetic->setAttribute(self::SOURCE_ATTRIBUTE, $name.'-'.\count($candidates));
                if (($widget = $builder->buildElement($synthetic)) instanceof ImageWidget) {
                    $candidates[] = [$media, $widget];
                } elseif (0 === $rank) {
                    // A source whose first candidate cannot be read is skipped, the others with it
                    break;
                }
            }
        }
        $fallback = null !== $img ? $builder->buildElement($img) : null;
        if (!$candidates) {
            return $fallback ?? $picture;
        }
        // The first source that matches wins: its rules come last, at the same specificity, hiding those after it and
        // the <img>; none matching, the <img> is shown
        $css = '['.self::SOURCE_ATTRIBUTE.'^="'.$name.'-"] { display: none !important }';
        foreach (array_reverse($candidates, true) as $index => [$media]) {
            $source = '['.self::SOURCE_ATTRIBUTE.'="'.$name.'-'.$index.'"]';
            $rules = $source.' { display: block !important } '.$source.' ~ * { display: none !important }';
            $css .= "\n".('' === $media ? $rules : '@media '.$media.' { '.$rules.' }');
        }
        foreach ($candidates as [, $widget]) {
            $picture->add($widget);
        }
        if (null !== $fallback) {
            $picture->add($fallback);
        }
        $builder->getStyleSheet()->addUserAgentStyles($css);

        return $picture;
    }

    /**
     * Both media queries, each query of the first one with the condition.
     */
    private static function both(string $media, string $condition): string
    {
        if ('' === $condition) {
            return $media;
        }
        if ('' === $media) {
            return $condition;
        }

        return implode(', ', array_map(static fn (string $query) => trim($query).' and '.$condition, preg_split('/,(?![^(]*\))/', $media) ?: [$media]));
    }
}
