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
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Symfony\Component\TuiHtml\Html\ElementFactoryInterface;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Style\RasterImage;
use Symfony\Component\TuiHtml\Style\VectorImage;
use Symfony\Component\TuiHtml\Widget\ButtonWidget;
use Symfony\Component\TuiHtml\Widget\Text\InlineImages;

/**
 * <button>: its text content is the label.
 *
 * @experimental
 */
final class ButtonElement implements ElementFactoryInterface
{
    /** @var \WeakMap<\Dom\Element, array{0: RasterImage|VectorImage, 1: int}|null>|null The icons drawn, made once for each of their elements */
    private static ?\WeakMap $icons = null;

    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        // The text drawn leaves out what is hidden, visually hidden included, like .sr-only, which the name keeps
        // Its icons drawn, the characters standing for their cells in the text
        $images = [];
        $text = self::text($element, $builder, true, $images);
        $name = self::text($element, $builder, false);
        $button = new ButtonWidget($text);
        if ($images) {
            $button->setInlineImages($images);
        }
        // The text is the accessible name, unless aria-label or title says otherwise
        if ('' !== $name) {
            $button->setLabel($name);
        }
        // What some rule hides in some media or state only, like a label displayed from a width, the search button of
        // Bootstrap or the hidden lg:inline of Tailwind: the text drawn follows the cascade, computed again when which
        // of them are displayed changes
        if ($conditional = self::conditional($element, $builder)) {
            $drawnImages = array_map(static fn (array $image) => $image[0], $images);
            $builder->getStyleSheet()->followDisplay($button, $element, $conditional, static function (AbstractWidget $button, array $displayed) use ($element, $builder, &$drawnImages): void {
                \assert($button instanceof ButtonWidget);
                $images = [];
                $text = self::text($element, $builder, true, $images, $displayed);
                $button->setText($text);
                // Its accessible name too, unless the page names it
                $none = null;
                if (!$element->hasAttribute('aria-label') && !$element->hasAttribute('aria-labelledby') && !$element->hasAttribute('title') && '' !== $name = self::text($element, $builder, false, $none, $displayed)) {
                    $button->setLabel($name);
                }
                // Other icons only
                $sources = array_map(static fn (array $image) => $image[0], $images);
                if (array_map(spl_object_id(...), $sources) !== array_map(spl_object_id(...), $drawnImages)) {
                    $drawnImages = $sources;
                    $button->setInlineImages($images);
                }
            });
        }

        return $button;
    }

    /**
     * The image an icon of the label is drawn as, made once for its element.
     *
     * @return array{0: RasterImage|VectorImage, 1: int}|null
     */
    private static function icon(\Dom\Element $element, ImageElement $factory, HtmlBuilder $builder): ?array
    {
        self::$icons ??= new \WeakMap();

        return self::$icons[$element] ??= $factory->inlineImage($element, $builder);
    }

    /**
     * The descendants of the label whose display some rule may change, those of icons left out.
     *
     * @return list<\Dom\Element>
     */
    private static function conditional(\Dom\Element $element, HtmlBuilder $builder): array
    {
        $conditional = [];
        for ($child = $element->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
            if ($builder->getStyleSheet()->mayChangeDisplay($child)) {
                $conditional[] = $child;
            }
            if (!$builder->getRegistry()->get(strtolower($child->localName)) instanceof ImageElement) {
                array_push($conditional, ...self::conditional($child, $builder));
            }
        }

        return $conditional;
    }

    /**
     * @param list<array{0: RasterImage|VectorImage, 1: int, 2: string}>|null $images    The icons drawn, when the text is drawn
     * @param array<int, bool>|null                                           $displayed Which elements whose display the cascade decides are displayed, by object id, see CssStyleSheet::followDisplay()
     */
    private static function text(\Dom\Element $element, HtmlBuilder $builder, bool $drawn, ?array &$images = null, ?array $displayed = null): string
    {
        $text = '';
        foreach ($element->childNodes as $child) {
            if ($child instanceof \Dom\Element) {
                // An icon, like <svg aria-label="Close">: its text alternative, the button drawing its own brackets
                $factory = $builder->getRegistry()->get(strtolower($child->localName));
                if ($factory instanceof ImageElement) {
                    $hidden = self::hidden($child, $builder, $drawn, $displayed);
                    $alternative = $hidden ? '' : $factory->textAlternative($child, $builder, true) ?? '';
                    // Drawn, like an <svg> icon: in the line of the text, a row tall
                    if (null !== $images && !$hidden && \count($images) < InlineImages::MAX && null !== $image = self::icon($child, $factory, $builder)) {
                        $images[] = [...$image, $alternative];
                        $text .= ' '.InlineImages::text(\count($images) - 1, $image[1]).' ';
                        continue;
                    }
                    $text .= ' '.('' === $alternative ? '' : substr($alternative, 1, -1)).' ';
                } elseif (!self::hidden($child, $builder, $drawn, $displayed)) {
                    $text .= ' '.self::text($child, $builder, $drawn, $images, $displayed).' ';
                }
            } elseif ($child instanceof \Dom\Text) {
                // The characters that stand for the cells of icons are not text
                $text .= preg_replace('/[\x{100000}-\x{10FFFF}]/u', '', $child->data) ?? $child->data;
            }
        }

        return trim(preg_replace('/\s+/', ' ', StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($text))) ?? $text);
    }

    /**
     * @param array<int, bool>|null $displayed
     */
    private static function hidden(\Dom\Element $element, HtmlBuilder $builder, bool $drawn, ?array $displayed): bool
    {
        // As the last cascade displays it, clipped to nothing too, like .sr-only
        if (null !== $display = $displayed[spl_object_id($element)] ?? null) {
            return !$display || $drawn && $builder->getStyleSheet()->mayBeVisuallyHidden($element);
        }

        return $builder->getStyleSheet()->mayBeHidden($element, $drawn);
    }
}
