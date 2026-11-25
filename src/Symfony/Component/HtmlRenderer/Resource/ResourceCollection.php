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

use Symfony\Component\HtmlRenderer\Vector\SvgStyle;

/** @internal */
final class ResourceCollection
{
    private array $sources = [];
    private \SplObjectStorage $svgViews;
    private array $images = [];
    private int $bytes = 0;
    private float $pixels = 0.0;

    public function __construct(private readonly ResourceLoader $loader, private readonly int $maxSources = 256, private readonly int $maxBytes = 67108864, private readonly int $maxPixels = 16777216)
    {
        $this->svgViews = new \SplObjectStorage();
        if ($maxSources < 1 || $maxBytes < 1 || $maxPixels < 1) {
            throw new \InvalidArgumentException('Resource budgets must be positive.');
        }
    }

    public function load(string $source): ?string
    {
        $key = hash('xxh128', $source);
        if (\array_key_exists($key, $this->sources)) {
            return $this->sources[$key];
        }
        if (\count($this->sources) + \count($this->svgViews) >= $this->maxSources) {
            return null;
        }
        $this->sources[$key] = null;
        $data = $this->loader->load($source);
        if (null === $data || \strlen($data) > $this->maxBytes - $this->bytes) {
            return null;
        }
        $this->bytes += \strlen($data);

        return $this->sources[$key] = $data;
    }

    public function image(string $source): ?ImageResource
    {
        $data = $this->load($source);
        if (null === $data) {
            return null;
        }
        $key = hash('xxh128', $data);
        if (!\array_key_exists($key, $this->images)) {
            $this->images[$key] = $this->reserve(ImageResource::fromString($data));
        }

        return $this->images[$key];
    }

    public function beginFrame(): void
    {
        $removed = [];
        foreach ($this->svgViews as $element) {
            if (!$element->isConnected) {
                $removed[] = $element;
            }
        }
        foreach ($removed as $element) {
            $this->bytes -= $this->svgViews[$element]['bytes'];
            unset($this->svgViews[$element]);
        }
    }

    public function releaseDocument(\Dom\Document $document): void
    {
        $removed = [];
        foreach ($this->svgViews as $element) {
            if ($element->ownerDocument === $document) {
                $removed[] = $element;
            }
        }
        foreach ($removed as $element) {
            $this->bytes -= $this->svgViews[$element]['bytes'];
            unset($this->svgViews[$element]);
        }
    }

    public function svg(\Dom\Element $element, ?\Closure $cascade = null): ?ImageResource
    {
        if ($element->getElementsByTagName('*')->length > 20000) {
            return null;
        }
        $owner = $element->ownerDocument;
        if (!$owner instanceof \Dom\HTMLDocument && !$owner instanceof \Dom\XMLDocument) {
            return null;
        }
        $source = $owner->saveXml($element);
        $signatureBytes = \strlen($source);
        if ($signatureBytes > ResourceLoader::MAX_BYTES) {
            return null;
        }
        $signature = hash_init('sha256');
        hash_update($signature, $source);
        if (null !== $cascade) {
            foreach ([$element, ...iterator_to_array($element->getElementsByTagName('*'), false)] as $node) {
                foreach (SvgStyle::computedValues($cascade($node)) as $name => $value) {
                    if (!\is_string($value)) {
                        continue;
                    }
                    $signatureBytes += \strlen($name) + \strlen($value) + 2;
                    if ($signatureBytes > ResourceLoader::MAX_BYTES) {
                        return null;
                    }
                    hash_update($signature, $name."\0".$value."\0");
                }
                hash_update($signature, "\0");
            }
        }
        $key = hash_final($signature);
        $previous = $this->svgViews[$element] ?? null;
        if (null !== $previous && $previous['key'] === $key) {
            return $previous['image'];
        }
        if (null === $previous && \count($this->sources) + \count($this->svgViews) >= $this->maxSources) {
            return null;
        }
        $image = ImageResource::fromSvg($element, $cascade);
        $bytes = null !== $image ? \strlen($image->data) : 0;
        if ($bytes > $this->maxBytes - $this->bytes + ($previous['bytes'] ?? 0)) {
            return null;
        }
        $this->bytes += $bytes - ($previous['bytes'] ?? 0);
        $this->svgViews[$element] = ['key' => $key, 'bytes' => $bytes, 'image' => $image];

        return $image;
    }

    private function reserve(?ImageResource $image): ?ImageResource
    {
        if (null === $image) {
            return null;
        }
        if ('image/svg+xml' !== $image->mimeType) {
            $pixels = $image->width * $image->height;
            if ($pixels > $this->maxPixels - $this->pixels) {
                return null;
            }
            $this->pixels += $pixels;
        }

        return $image;
    }
}
