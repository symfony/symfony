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

use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Html\BoxBuilder;
use Symfony\Component\HtmlRenderer\Html\IdAttributes;
use Symfony\Component\HtmlRenderer\Html\ShadowRendering;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\Resource\ResourceCollection;

final class HtmlRenderer
{
    public function __construct(private readonly int $maxBytes = 4194304)
    {
        if ($maxBytes <= 0) {
            throw new \InvalidArgumentException('The document size limit must be positive.');
        }
    }

    public function render(string|\Dom\HTMLDocument $document, RenderingContext $context): RenderingResult
    {
        return $this->createSession($document, $context)->render();
    }

    public function createSession(string|\Dom\HTMLDocument $document, RenderingContext $context): RenderSession
    {
        $serializedLimit = $this->maxBytes;
        if (\is_string($document)) {
            $sourceBytes = \strlen($document);
            if ($sourceBytes > $this->maxBytes) {
                throw new \LengthException('The document exceeds the configured size limit.');
            }
            $document = \Dom\HTMLDocument::createFromString($document, \LIBXML_NOERROR);
            $overhead = max(0, \strlen($document->saveHtml()) - $sourceBytes);
            $serializedLimit = $this->maxBytes > \PHP_INT_MAX - $overhead ? \PHP_INT_MAX : $this->maxBytes + $overhead;
        } elseif (\strlen($document->saveHtml()) > $serializedLimit) {
            throw new \LengthException('The document exceeds the configured size limit.');
        }

        if (null !== $root = $document->documentElement) {
            IdAttributes::mark($root);
        }

        return new RenderSession($document, $context, $serializedLimit, $this->maxBytes);
    }

    /** @internal */
    public static function compute(\Dom\HTMLDocument $document, RenderingContext $context, array $states, ?Timeline $timeline = null, float $time = 0, ?ResourceCollection $resources = null, ?StyleResolver $resolver = null, ?\Closure $embedded = null, ?array $view = null): RenderingResult
    {
        $resources ??= new ResourceCollection($context->resourceLoader);
        $resources->beginFrame();
        $resolver ??= new StyleResolver($resources, $context->defaultFont, $context->textMeasurer, $context->mediaFeatures, $context->userAgentStylesheet, $context->colors, $context->colorLookup, null, $context->layoutMetrics);
        $visualDocument = $view['document'] ?? $document;
        if (null !== $view) {
            $mappedStates = [];
            foreach ($states as [$element, $state]) {
                if (!isset($view['mirrors'][$element])) {
                    continue;
                }
                if (isset($state['anchor'])) {
                    $state['anchor'] = $view['mirrors'][$state['anchor']] ?? null;
                }
                $mappedStates[] = [$view['mirrors'][$element], $state];
            }
            $states = $mappedStates;
        }
        $timeline?->setActiveElements($view['mirrors'] ?? null);
        $modal = null;
        $modalOrder = -1;
        /** @var \SplObjectStorage<\Dom\Element, array<array-key, mixed>> $stateMap */
        $stateMap = new \SplObjectStorage();
        foreach ($states as [$element, $state]) {
            $stateMap[$element] = $state;
            if (\in_array('modal', $state, true) && ($state['top-layer'] ?? -1) > $modalOrder) {
                $modal = $element;
                $modalOrder = $state['top-layer'];
            }
        }
        $trial = null === $timeline ? null : clone $timeline;
        [$styles, $fragment] = self::frame($visualDocument, $context, $states, $trial, $time, $resources, $resolver, $stateMap, null, $view);
        if ($resolver->hasContainerQueries() || $resolver->hasScrollControls() || ($trial?->hasScrollAnimations() ?? false) || ($trial?->hasLayoutTransitions() ?? false)) {
            $base = [$styles, $fragment, $trial];
            $containers = Layout\ContainerMetrics::collect($styles, $fragment, $stateMap);
            $seen = [];
            $stable = false;
            for ($pass = 0; $pass < 8; ++$pass) {
                $fingerprint = Layout\ContainerMetrics::fingerprint($containers);
                if (isset($seen[$fingerprint])) {
                    break;
                }
                $seen[$fingerprint] = true;
                $trial = null === $timeline ? null : clone $timeline;
                [$styles, $fragment] = self::frame($visualDocument, $context, $states, $trial, $time, $resources, $resolver, $stateMap, $containers, $view);
                $containers = Layout\ContainerMetrics::collect($styles, $fragment, $stateMap);
                if ($fingerprint === Layout\ContainerMetrics::fingerprint($containers)) {
                    $stable = true;
                    break;
                }
            }
            if (!$stable) {
                [$styles, $fragment, $trial] = $base;
            }
        }
        if (null !== $timeline) {
            $timeline->adopt($trial);
        }

        if (null !== $view) {
            $styles = ShadowRendering::styles($styles, $view['owners']);
            $fragment = ShadowRendering::fragment($fragment, $view['owners']);
            $modal = null === $modal ? null : $view['owners'][$modal];
        }

        return new RenderingResult($document, $styles, $fragment, (new Painter($context->textMeasurer, $embedded, $context->layoutMetrics))->paint($fragment), $context->textMeasurer, $modal, $context->layoutMetrics, $view['parents'] ?? null);
    }

    /**
     * @param \SplObjectStorage<\Dom\Element, array<array-key, mixed>> $stateMap
     */
    private static function frame(\Dom\HTMLDocument $document, RenderingContext $context, array $states, ?Timeline $timeline, float $time, ResourceCollection $resources, StyleResolver $resolver, \SplObjectStorage $stateMap, ?\SplObjectStorage $containers = null, ?array $view = null): array
    {
        $styles = $resolver->resolve($document, $context->width, $context->height, $states, $timeline, $time, $containers, null, $view['owners'] ?? null, $view['depths'] ?? []);
        $replacedContentResolver = $context->replacedContentResolver;
        if (null !== $replacedContentResolver && null !== $view) {
            $sourceResolver = $replacedContentResolver;
            $replacedContentResolver = static fn (\Dom\Element $element, array $style) => $sourceResolver($view['owners'][$element], $style);
        }
        $root = (new BoxBuilder($styles, $resolver, $resources, $stateMap, $replacedContentResolver))->build($document->documentElement);
        $fragment = (new LayoutEngine($context->textMeasurer, $context->layoutMetrics, $context->colors, 'dark' === ($context->mediaFeatures['prefers-color-scheme'] ?? 'light') ? 'dark' : 'light', $context->hyphenator, $context->colorLookup, $resources))->layout($root, $context->width, $context->height);

        return [$styles, $fragment];
    }
}
