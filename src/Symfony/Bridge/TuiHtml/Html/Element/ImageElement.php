<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Html\Element;

use Symfony\Bridge\TuiHtml\Html\ElementFactoryInterface;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Style\RasterImage;
use Symfony\Bridge\TuiHtml\Style\VectorImage;
use Symfony\Bridge\TuiHtml\Widget\ContainerWidget;
use Symfony\Bridge\TuiHtml\Widget\FrameWidget;
use Symfony\Bridge\TuiHtml\Widget\ImageAlternativeWidget;
use Symfony\Bridge\TuiHtml\Widget\ImageMapWidget;
use Symfony\Bridge\TuiHtml\Widget\ImageWidget;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;
use Symfony\Bridge\TuiHtml\Widget\SvgWidget;
use Symfony\Bridge\TuiHtml\Widget\Util\UrlResolver;
use Symfony\Component\HtmlRenderer\Html\EmbeddedContent;
use Symfony\Component\HtmlRenderer\Html\EmbeddedDocument;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/**
 * <img>, and the embedded content a terminal cannot draw (<svg>, <video>...).
 *
 * An image whose source can be read is drawn with half blocks: a data: URL
 * always, other sources through the loader, which is given the src as
 * written and returns the encoded image. The width and height attributes
 * are CSS pixels, 8 per column and 16 per row. Without a loader, or when the
 * image cannot be read or decoded, the text alternative is shown in
 * brackets: the alt text of an image, the aria-label or title of the
 * others. An image with an empty alt that cannot be drawn is decorative and
 * shows nothing. An <iframe> draws the document of its srcdoc, or of its src,
 * read like the source of an image.
 *
 * @experimental
 */
final class ImageElement implements ElementFactoryInterface
{
    /**
     * The largest image read, in bytes.
     */
    public const MAX_BYTES = 8388608;
    /**
     * @param (\Closure(string): string)|null $loader Reads an image from its src, like the load() method of a stylesheet loader; it throws when it cannot
     */
    /** @var \WeakMap<\Dom\Element, array{string, ?string}>|null The src of each <img> read, and its data when it can be drawn */
    private ?\WeakMap $drawables = null;
    /** @var \WeakMap<\Dom\Document, array<string, ?string>>|null The data of the images of a document read, by address, null for those that cannot be drawn */
    private ?\WeakMap $sources = null;
    /** @var \WeakMap<\Dom\Document, array{array<string, RasterImage>, int}>|null The images of a document drawn in its text, one per address, and the pixels they take together */
    private ?\WeakMap $inlineImages = null;
    /** The most pixels the images drawn in the text of a document take together, each decoded once */
    private const MAX_INLINE_PIXELS = 4 * RasterImage::MAX_PIXELS;
    /** @var \WeakMap<\Dom\Element, array{string, ?string}>|null The src of each <iframe> read, and its document when it can be */
    private ?\WeakMap $documents = null;
    /** @var \WeakMap<\Dom\Element, array<int, VectorImage|false>>|null The shapes of each <svg> read, false when it draws none */
    private ?\WeakMap $vectors = null;

    public function __construct(private readonly ?\Closure $loader = null)
    {
    }

    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        // What browsers that cannot draw the content show instead, like the text in a <canvas>; the poster of a video,
        // or the image an <object> holds, is drawn over it
        if (self::hasFallback($element) && null === $this->drawable($element)) {
            $container = new ContainerWidget();
            $builder->buildChildren($element, $container);

            return $container;
        }
        if (null !== $frame = $this->frame($element, $builder)) {
            return $frame;
        }
        // An <img srcset> of several candidates: the one for the density of the terminal, like a <picture>
        if ('img' === strtolower($element->localName) && $element->hasAttribute('srcset') && 'picture' !== strtolower($element->parentElement->localName ?? '') && null !== $picture = PictureElement::fromSrcset($element, $builder)) {
            return $picture;
        }
        // A candidate of a srcset not drawn yet, like the 2x image on a terminal of 1dppx: read when it is first drawn
        if ('img' === strtolower($element->localName) && $element->hasAttribute(PictureElement::LAZY_ATTRIBUTE)) {
            $src = trim($element->getAttribute('src') ?? '');

            return new ImageWidget(fn (): ?string => $this->read($src), self::alternative($element, $builder), self::cells($element->getAttribute('width'), 8), self::cells($element->getAttribute('height'), 16));
        }
        if (null !== $data = $this->drawable($element)) {
            // An image map: its areas are links, and it takes the focus
            $areas = self::mapAreas($element, $builder);
            $image = [] !== $areas ? new ImageMapWidget($data, self::alternative($element, $builder), self::cells($element->getAttribute('width'), 8), self::cells($element->getAttribute('height'), 16)) : new ImageWidget($data, self::alternative($element, $builder), self::cells($element->getAttribute('width'), 8), self::cells($element->getAttribute('height'), 16));
            if ([] !== $areas) {
                $width = self::pixels($element->getAttribute('width'));
                $height = self::pixels($element->getAttribute('height'));
                $image->setAreas($areas, null !== $width && null !== $height ? [$width, $height] : null);
            }

            return $image;
        }
        if (null !== $vector = $this->vector($element, $builder, false)) {
            $styleSheet = $builder->getStyleSheet();

            $widget = new SvgWidget($vector, self::alternative($element, $builder), static fn (): ?VectorImage => VectorImage::fromElement($element, $styleSheet->svgSnapshot($element)));
            $styleSheet->registerSvg($widget, $element);

            return $widget;
        }
        $alternative = self::alternative($element, $builder);
        if ('' === $alternative) {
            return new ContainerWidget();
        }

        return new ImageAlternativeWidget('['.$alternative.']');
    }

    /**
     * Returns what is drawn in the line of the text around the element, like browsers do with an image that cannot be
     * drawn: its text alternative in brackets, '' for none; null when its fallback content is drawn, or when the image
     * is drawn, unless $inline, as an image inside an inline element is not.
     */
    public function textAlternative(\Dom\Element $element, HtmlBuilder $builder, bool $inline = false): ?string
    {
        if (self::hasFallback($element) && null === $this->drawable($element) || !$inline && (null !== $this->drawable($element) || null !== $this->vector($element, $builder, false) || null !== $this->frameDocument($element))) {
            return null;
        }
        $alternative = self::alternative($element, $builder);
        // Without one, but of a width and of less than a row, like the spacer images of Hacker News indenting the
        // replies: its width, blank, like browsers, its box no row
        if ('' === $alternative && null !== ($height = self::pixels($element->getAttribute('height'))) && $height < 12 && preg_match('/^\s*(\d{1,5})(?:\.\d+)?\s*(?:px)?\s*$/i', $element->getAttribute('width') ?? '', $width)) {
            return str_repeat("\u{a0}", min(1000, (int) round((int) $width[1] / 8)));
        }

        return '' === $alternative ? '' : '['.$alternative.']';
    }

    /**
     * The image of an <img>, or the shapes of an <svg>, drawn in the line of the text around it, inside an inline
     * element like a link: a row tall, as many columns as its proportions say (its width and height attributes, else
     * its pixels, or the view box of an <svg>), from 1 to 16; null for another element, or one that cannot be drawn.
     *
     * @return array{RasterImage|VectorImage, int}|null
     */
    public function inlineImage(\Dom\Element $element, ?HtmlBuilder $builder = null): ?array
    {
        // An icon, like those in links and buttons: drawn in the color of the text where it is, see RichTextWidget
        if (null !== $builder && null !== $vector = $this->vector($element, $builder)) {
            $columns = max(1, min(16, (int) round($vector->width / $vector->height * 2)));
            // Too thin to show in so few cells, like the strokes of an outline icon: its text alternative
            $png = $vector->toPng($columns * 4, 8, [255, 255, 255]);
            $cells = null !== $png ? (new RasterImage($png))->cells($columns, 1)[0] ?? [] : [];
            if (!array_any($cells, static fn (array $cell): bool => null !== $cell[0] || null !== $cell[1])) {
                return null;
            }

            return [$vector, $columns];
        }
        if (!\in_array(strtolower($element->localName), ['img', 'input'], true) || null === ($document = $element->ownerDocument) || null === $data = $this->drawable($element)) {
            return null;
        }
        // One image for an address, decoded once however often it is drawn; past the pixels a document may decode, the
        // text alternative
        $src = trim($element->getAttribute('src') ?? '');
        $this->inlineImages ??= new \WeakMap();
        [$images, $pixels] = $this->inlineImages[$document] ?? [[], 0];
        if (!isset($images[$src])) {
            $size = @getimagesizefromstring($data);
            if (false === $size || $pixels + $size[0] * $size[1] > self::MAX_INLINE_PIXELS) {
                return null;
            }
            $images[$src] = new RasterImage($data);
            $this->inlineImages[$document] = [$images, $pixels + $size[0] * $size[1]];
        }
        $image = $images[$src];
        $width = self::pixels($element->getAttribute('width'));
        $height = self::pixels($element->getAttribute('height'));
        $ratio = match (true) {
            null !== $width && null !== $height && $height > 0 => $width / $height,
            default => $image->getWidth() / max(1, $image->getHeight()),
        };

        // A row is 16 CSS pixels tall, a column 8 wide
        return [$image, max(1, min(16, (int) round($ratio * 2)))];
    }

    /**
     * The links of the image map of an <img usemap="#name">: the <area> elements with an href of the <map> of that
     * name, their coordinates in CSS pixels of the image, as drawn (its width and height attributes, else its pixels).
     */
    /**
     * @return list<array{'rect'|'circle'|'poly'|'default', list<float>, string, string}>
     */
    private static function mapAreas(\Dom\Element $element, HtmlBuilder $builder): array
    {
        $name = trim($element->getAttribute('usemap') ?? '');
        if (!str_starts_with($name, '#') || '' === $name = substr($name, 1)) {
            return [];
        }
        $map = null;
        foreach ($element->ownerDocument?->getElementsByTagName('map') ?? [] as $candidate) {
            if ($name === $candidate->getAttribute('name') || $candidate->hasAttribute('id') && $name === $candidate->getAttribute('id')) {
                $map = $candidate;
                break;
            }
        }
        if (null === $map) {
            return [];
        }
        $areas = [];
        foreach (iterator_to_array($map->getElementsByTagName('area'), false) as $index => $area) {
            $href = trim($area->getAttribute('href') ?? '');
            if ('' === $href) {
                continue;
            }
            $href = null !== ($base = $builder->getBaseUrl()) && !str_starts_with($href, '#') ? UrlResolver::resolve($href, $base) : $href;
            if (null === $link = RichTextWidget::linkOpening($href, 'tui-area-'.spl_object_id($element).'-'.$index)) {
                continue;
            }
            $shape = match (strtolower(trim($area->getAttribute('shape') ?? 'rect'))) {
                'circle', 'circ' => 'circle',
                'poly', 'polygon' => 'poly',
                'default' => 'default',
                default => 'rect',
            };
            $coords = array_map('floatval', preg_split('/[\s,]+/', trim($area->getAttribute('coords') ?? ''), -1, \PREG_SPLIT_NO_EMPTY) ?: []);
            $areas[] = [$shape, $coords, $link, $href];
        }

        return $areas;
    }

    private static function pixels(?string $value): ?float
    {
        return null !== $value && preg_match('/^\s*(\d{1,5}(?:\.\d+)?)\s*(?:px)?\s*$/i', $value, $m) ? max(1.0, (float) $m[1]) : null;
    }

    /**
     * The text alternative: the alt of an <img>; the aria-label, the title or the <title> of embedded content.
     */
    private static function alternative(\Dom\Element $element, HtmlBuilder $builder): string
    {
        $alternative = EmbeddedContent::alternative($element, static fn (string $name): ?string => $builder->getAttributeText($element, $name));

        // Data, drawn as is: no control bytes, like those of escape sequences, nor invalid UTF-8
        // Data, no characters standing for the cells of images either
        return trim(preg_replace(['/\s+/', '/[\x{100000}-\x{10FFFF}]/u'], [' ', ''], StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($alternative))) ?? '');
    }

    /**
     * Whether embedded content that cannot be drawn has content for when it is not, like the text in a <canvas> or
     * an <object>, which is drawn instead.
     */
    private static function hasFallback(\Dom\Element $element): bool
    {
        if (!\in_array(strtolower($element->localName), ['canvas', 'object', 'video', 'audio'], true)) {
            return false;
        }
        foreach ($element->childNodes as $child) {
            if ($child instanceof \Dom\Text && '' !== trim($child->data) || $child instanceof \Dom\Element && !\in_array(strtolower($child->localName), ['source', 'track', 'param'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The data of the image that can be drawn of an <img>, of the poster of a <video>, or of the image an <object>
     * or an <embed> holds, read once for its address; null for another element, or one that cannot be drawn.
     */
    private function drawable(\Dom\Element $element): ?string
    {
        $attribute = match (strtolower($element->localName)) {
            'img', 'embed' => 'src',
            'input' => 'image' === strtolower(trim($element->getAttribute('type') ?? '')) ? 'src' : null,
            'video' => 'poster',
            'object' => 'data',
            default => null,
        };
        if (null === $attribute || !$element->hasAttribute($attribute)) {
            return null;
        }
        // The type an <object> or an <embed> says, when it says one, is an image
        $type = strtolower(trim($element->getAttribute('type') ?? ''));
        if ('' !== $type && !\in_array(strtolower($element->localName), ['img', 'input'], true) && !str_starts_with($type, 'image/')) {
            return null;
        }
        $src = trim($element->getAttribute($attribute) ?? '');
        $this->drawables ??= new \WeakMap();
        if (($this->drawables[$element][0] ?? null) !== $src) {
            // Read once for an address of the document, for the copies of an element too, like those of the text around it
            $this->sources ??= new \WeakMap();
            $document = $element->ownerDocument;
            $read = null !== $document ? $this->sources[$document] ?? [] : [];
            if (!\array_key_exists($src, $read)) {
                $data = $this->read($src);
                // An SVG, like the logo of a site: drawn once at its size, then like the other images
                if (null !== $data && !(new ImageWidget($data, ''))->isDrawable() && \extension_loaded('gd') && null !== $vector = VectorImage::fromString($data)) {
                    $data = $vector->toPng((int) ceil($vector->width), (int) ceil($vector->height), [0, 0, 0]);
                }
                $read[$src] = null !== $data && (new ImageWidget($data, ''))->isDrawable() ? $data : null;
                if (null !== $document) {
                    $this->sources[$document] = $read;
                }
            }
            $this->drawables[$element] = [$src, $read[$src]];
        }

        return $this->drawables[$element][1];
    }

    /**
     * The nested document of an <iframe>, read once: its srcdoc, else the document of its src, read like the source of
     * an image (a data: URL, or through the loader), as UTF-8; null for another element, or one that cannot be read.
     */
    private function frameDocument(\Dom\Element $element): ?string
    {
        if ('iframe' !== strtolower($element->localName)) {
            return null;
        }
        if ($element->hasAttribute('srcdoc')) {
            return EmbeddedDocument::read($element, $this->read(...));
        }
        $src = trim($element->getAttribute('src') ?? '');
        $this->documents ??= new \WeakMap();
        if (($this->documents[$element][0] ?? null) !== $src) {
            $this->documents[$element] = [$src, EmbeddedDocument::read($element, $this->read(...))];
        }

        return $this->documents[$element][1];
    }

    /**
     * The nested document of an <iframe>, in a box of its width and height (300 by 150 CSS pixels by default), styled
     * by its own stylesheet; null for another element, one whose document cannot be read, or too deep in other frames.
     */
    private function frame(\Dom\Element $element, HtmlBuilder $builder): ?FrameWidget
    {
        if (null === ($html = $this->frameDocument($element)) || null === $view = $builder->buildFrame($html)) {
            return null;
        }
        // Its viewport, for the media queries and the viewport units of the document: its box inside its border, of
        // its width and height attributes (a size from CSS is not seen)
        $view->getStyleSheet()->setScope($view->getRoot(), [max(1, (self::cells($element->getAttribute('width'), 8) ?? 38) - 2), max(1, (self::cells($element->getAttribute('height'), 16) ?? 9) - 2)]);
        $frame = new FrameWidget();
        $frame->add($view->getRoot());
        if (null !== $title = $builder->getAttributeText($element, 'title')) {
            $frame->setLabel($title);
        }

        return $frame;
    }

    /**
     * The shapes of an <svg> that draws some, read once; null for another element, or without the GD extension.
     */
    private function vector(\Dom\Element $element, HtmlBuilder $builder, bool $styled = true): ?VectorImage
    {
        if ('svg' !== strtolower($element->localName) || !\extension_loaded('gd')) {
            return null;
        }
        $this->vectors ??= new \WeakMap();

        $images = $this->vectors[$element] ?? [];
        if (!\array_key_exists((int) $styled, $images)) {
            $images[(int) $styled] = VectorImage::fromElement($element, $styled ? $builder->getStyleSheet()->svgSnapshot($element) : null) ?? false;
            $this->vectors[$element] = $images;
        }

        return $images[(int) $styled] ?: null;
    }

    private function read(string $src): ?string
    {
        return (new ResourceLoader($this->loader, self::MAX_BYTES))->load($src);
    }

    /**
     * Converts a width or height attribute, in CSS pixels, to cells.
     */
    private static function cells(?string $pixels, int $perCell): ?int
    {
        if (null === $pixels || !preg_match('/^\s*(\d{1,5})(?:\.\d+)?\s*(?:px)?\s*$/i', $pixels, $m)) {
            return null;
        }

        return max(1, (int) round((int) $m[1] / $perCell));
    }
}
