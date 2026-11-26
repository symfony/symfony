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
use Symfony\Component\HtmlRenderer\Hyphenation\HyphenatorInterface;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

final class RenderingContext
{
    public readonly TextMeasurerInterface $textMeasurer;
    public readonly ResourceLoader $resourceLoader;
    public readonly FontStyle $defaultFont;

    /**
     * @param (\Closure(\Dom\Element, array<string, string>): ?Content\ReplacedContentInterface)|null $replacedContentResolver
     * @param (\Closure(string, string): ?array{array{float, float, float}, float})|null              $colorLookup             A stable name lookup returning normalized sRGBA, or null to use CSS defaults
     */
    public function __construct(public readonly float $width, public readonly float $height, ?TextMeasurerInterface $textMeasurer = null, ?ResourceLoader $resourceLoader = null, public readonly ?LayoutMetricsInterface $layoutMetrics = null, ?FontStyle $defaultFont = null, public readonly array $mediaFeatures = [], public readonly string $userAgentStylesheet = '', public readonly array $colors = [], public readonly ?HyphenatorInterface $hyphenator = null, public readonly ?\Closure $replacedContentResolver = null, public readonly ?\Closure $colorLookup = null)
    {
        if (!is_finite($width) || !is_finite($height) || $width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Viewport dimensions must be finite and positive.');
        }
        $this->textMeasurer = $textMeasurer ?? new MonospaceTextMeasurer();
        $this->resourceLoader = $resourceLoader ?? new ResourceLoader();
        $this->defaultFont = $defaultFont ?? new FontStyle();
    }

    public function withViewport(float $width, float $height, ?TextMeasurerInterface $textMeasurer = null): self
    {
        return new self($width, $height, $textMeasurer ?? $this->textMeasurer, $this->resourceLoader, $this->layoutMetrics, $this->defaultFont, $this->mediaFeatures, $this->userAgentStylesheet, $this->colors, $this->hyphenator, $this->replacedContentResolver, $this->colorLookup);
    }
}
