<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Render;

use Symfony\Bridge\TuiHtml\Content\FrameContentAdapter;
use Symfony\Bridge\TuiHtml\Content\NativeContentAdapter;
use Symfony\Bridge\TuiHtml\Content\TabsContentAdapter;
use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Css\Value;
use Symfony\Bridge\TuiHtml\Html\MirrorPresentation;
use Symfony\Bridge\TuiHtml\Internal\ContainerState;
use Symfony\Bridge\TuiHtml\Internal\FontFamilies;
use Symfony\Bridge\TuiHtml\Internal\StyleBridge;
use Symfony\Bridge\TuiHtml\Style\CellSize;
use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\PercentLength;
use Symfony\Bridge\TuiHtml\Style\Position;
use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Bridge\TuiHtml\Widget\CollapsibleWidget;
use Symfony\Bridge\TuiHtml\Widget\ContainerWidget;
use Symfony\Bridge\TuiHtml\Widget\FrameWidget;
use Symfony\Bridge\TuiHtml\Widget\HtmlRootWidget;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;
use Symfony\Bridge\TuiHtml\Widget\Text\InlineContentInterface;
use Symfony\Component\HtmlRenderer\Html\BoxBuilder;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\Paint\PaintOrder;
use Symfony\Component\HtmlRenderer\RenderingResult;
use Symfony\Component\HtmlRenderer\Resource\ResourceCollection;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\Tui\Widget\TabsWidget;

/** @internal Bounded opt-in facade migration; no legacy HTML layout is called. */
final class FacadePresentation
{
    private ?\Symfony\Bridge\TuiHtml\Internal\BackgroundImageCache $backgroundImages = null;

    private MirrorPresentation $projector;
    private ResourceCollection $resources;
    private ?RenderingResult $result = null;
    private \SplObjectStorage $rects;
    private ?array $projection = null;
    private array $subLayouts = [];
    private array $tabAdapters = [];
    private TerminalTextMeasurer $metrics;
    private array $lines = [];
    private array $viewport = [0, 0];
    private ?array $key = null;
    private \WeakMap $providers;
    private \WeakMap $inlineImageProviders;
    private \SplObjectStorage $preparedInlineImages;
    private \WeakMap $implicitAnchors;
    private string $defaultForeground = 'default';
    private ?\Closure $nativeStyleResolver = null;
    private \SplObjectStorage $usedStyles;
    private readonly \Closure $nativePolicies;
    private \SplObjectStorage $nativeProperties;
    private \WeakMap $detachedContents;
    private MediaContext $colorMedia;

    public function __construct(private readonly CssStyleSheet $sheet)
    {
        $this->projector = new MirrorPresentation();
        $this->resources = new ResourceCollection(new ResourceLoader($sheet->getImageSourceData(...)));
        $this->rects = new \SplObjectStorage();
        $this->providers = new \WeakMap();
        $this->inlineImageProviders = new \WeakMap();
        $this->preparedInlineImages = new \SplObjectStorage();
        $this->implicitAnchors = new \WeakMap();
        $this->usedStyles = new \SplObjectStorage();
        $this->nativeProperties = new \SplObjectStorage();
        $this->detachedContents = new \WeakMap();
        $this->nativePolicies = $this->prepareNativePolicies(...);
        $this->colorMedia = new MediaContext();
        $this->metrics = new TerminalTextMeasurer();
    }

    public function setImplicitAnchor(AbstractWidget $widget, ?AbstractWidget $anchor): void
    {
        if (null === $anchor) {
            unset($this->implicitAnchors[$widget]);
        } else {
            $this->implicitAnchors[$widget] = \WeakReference::create($anchor);
        }
        $this->key = null;
    }

    public function setDefaultForeground(string $color): void
    {
        if ($color !== $this->defaultForeground) {
            $this->defaultForeground = $color;
            $this->key = null;
        }
    }

    public function setNativeStyleResolver(\Closure $resolver): void
    {
        if ($resolver !== $this->nativeStyleResolver) {
            $this->nativeStyleResolver = $resolver;
            $this->key = null;
        }
    }

    public function render(HtmlRootWidget $root, RenderContext $context, MediaContext $media): array
    {
        $columns = $context->getColumns();
        $rows = $context->getRows();
        $this->viewport = [$columns, $rows];
        if ($columns < 1 || $rows < 1) {
            return [];
        }
        $cell = $media->cellSize ?? CellSize::default();
        $this->metrics = new TerminalTextMeasurer($cell->width, $cell->height, $media->textSizing);
        $constraints = new TerminalLayoutMetrics($this->metrics);
        $this->colorMedia = $media;
        $contextColor = $context->getStyle()->getColor();
        $defaultColor = null !== $contextColor && $contextColor->toForegroundCode() !== Color::named('default')->toForegroundCode() ? $contextColor->toHex() : $this->defaultForeground;
        if (null === $contextColor && null !== $this->nativeStyleResolver) {
            for ($parent = $root->getParent(); null !== $parent && !$parent instanceof FrameWidget; $parent = $parent->getParent()) {
                $parentColor = ($this->nativeStyleResolver)($parent, $media, true)->getColor();
                if (null !== $parentColor) {
                    $defaultColor = $parentColor->toHex();
                    break;
                }
            }
        }
        $this->prepareWidgets($root, true);
        $snapshot = $this->sheet->prepareProjection($root, $media, function (array $snapshot) use ($root): void {
            $this->preparedInlineImages = $this->projector->project($snapshot, $root)['inline-images'];
        }, $defaultColor, $this->nativePolicies);
        $key = [$root->getRenderRevision(), $media->getKey(), $snapshot['styles'], $context->getFontRegistry()];
        if ($key === $this->key) {
            return $this->lines;
        }
        $seedUsedStyles = clone $this->usedStyles;
        $previousLayouts = array_column($this->subLayouts, 'result');
        if (null !== $this->result) {
            $previousLayouts[] = $this->result;
        }
        $build = function () use (&$snapshot, $seedUsedStyles, $previousLayouts, $root, $context, $media, $constraints, $cell, $columns, $rows, $defaultColor): array {
            $this->usedStyles = clone $seedUsedStyles;
            foreach ($this->tabAdapters as $adapter) {
                $adapter->dispose();
            }
            $this->tabAdapters = [];
            foreach ($snapshot['nodes'] as $widget) {
                $node = $snapshot['nodes'][$widget];
                $style = null === $this->nativeStyleResolver ? (StyleBridge::get($widget) ?? new Style()) : ($this->nativeStyleResolver)($widget, $media, true);
                $properties = $this->nativeProperties[$node] ?? [];
                $used = $this->usedStyles[$node] ?? [];
                if (($properties['color'] ?? false) && null !== $color = $style->getColor()) {
                    $used['']['color'] = $this->colorToken($color);
                }
                if (($properties['hidden'] ?? false) && null !== $hidden = $style->getHidden()) {
                    $display = $snapshot['styles'][$node]['display'] ?? 'block';
                    $used['']['display'] = $hidden ? 'none' : ('none' === $display ? 'block' : $display);
                }
                if (($properties['maxColumns'] ?? false) && null !== $maximum = $style->getMaxColumns()) {
                    if ('content-box' === ($snapshot['styles'][$node]['box-sizing'] ?? 'content-box')) {
                        foreach ([$style->getPadding(), $style->getBorder()] as $chrome) {
                            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                                if (0 !== ($chrome->{$side} ?? 0)) {
                                    throw new \LogicException('Native outer width caps on authored content-box chrome require used-size projection.');
                                }
                            }
                        }
                    }
                    // ch is the terminal device column contract, independent of font size and pixel density.
                    $used['']['max-width'] = max(0, $maximum).'ch';
                }
                if (($properties['position'] ?? false) && null !== $position = $style->getPosition()) {
                    $used[''] = array_replace($used[''] ?? [], $this->positionStyle($position));
                }
                if ($used) {
                    $this->usedStyles[$node] = $used;
                }
            }
            foreach ($snapshot['nodes'] as $widget) {
                $node = $snapshot['nodes'][$widget];
                $used = $this->usedStyles[$node] ?? [];
                foreach ($snapshot['resolver']->getCascadeRecord($node)['pseudos'] ?? [] as $role => $pseudo) {
                    if ($pseudo['declarations']['color']['initial'] ?? false) {
                        $used[$role] = ['color' => 'default' === $this->defaultForeground ? Color::named('default')->toHex() : $this->defaultForeground];
                    }
                }
                $marker = $snapshot['resolver']->getCascadeRecord($node, 'scroll-marker');
                if (!\array_key_exists('color', $marker['declarations'] ?? []) && !isset($marker['animated']['color']) && null !== $parent = $node->parentElement) {
                    $parentStyle = $snapshot['styles'][$parent] ?? [];
                    if (\in_array($parentStyle['scroll-marker-group'] ?? 'none', ['before', 'after'], true) && null !== $color = $snapshot['resolver']->getPseudoStyle($parent, 'scroll-marker-group')['color'] ?? null) {
                        $used['scroll-marker']['color'] = $color;
                    }
                }
                if ($used) {
                    $this->usedStyles[$node] = $used;
                }
            }
            $this->projection = $projection = $this->projector->project($snapshot, $root, $this->usedStyles);
            $this->detachedContents = new \WeakMap();
            foreach ($projection['owners'] as $owner) {
                $widget = $projection['owners'][$owner];
                if ($widget instanceof CollapsibleWidget && !$widget->isExpanded()) {
                    $this->detachedContents[$widget] = $widget->getContent()->getRenderRevision();
                }
            }
            $styles = $snapshot['styles'];
            $providers = new \SplObjectStorage();
            /** @var \SplObjectStorage<\Dom\Element, array<array-key, mixed>> $states */
            $states = new \SplObjectStorage();
            foreach ($projection['owners'] as $element) {
                if (!$element instanceof \Dom\Element || !$projection['owners'][$element] instanceof InlineContentInterface) {
                    continue;
                }
                if (null !== FontFamilies::first($context->getFontRegistry(), ($styles[$element] ?? [])['font-family'] ?? 'monospace')) {
                    $projection['replacements'][$element] = $projection['owners'][$element];
                }
            }
            foreach ($projection['replacements'] as $element) {
                $widget = $projection['replacements'][$element];
                if ($widget instanceof FrameWidget) {
                    $child = $widget->all()[0] ?? null;
                    if (!$child instanceof HtmlRootWidget) {
                        throw new \LogicException('An opt-in frame requires an independent HTML root.');
                    }
                    $providers[$element] = new FrameContentAdapter($child, $this->metrics, $media, $context->getFontRegistry());
                    continue;
                }
                $styleNode = $widget instanceof CollapsibleWidget ? $snapshot['nodes'][$widget] : $element;
                $style = array_replace($styles[$styleNode] ?? [], ($this->usedStyles[$styleNode] ?? [])[''] ?? []);
                if ($widget instanceof RichTextWidget && isset($projection['inline-images'][$element])) {
                    $image = $projection['inline-images'][$element];
                    $tokens = $image['tokens'];
                    $nativeColor = $snapshot['inline-computed'][spl_object_id($widget)][$image['inline-index']]['color'] ?? ($snapshot['computed'][$widget]['color'] ?? null);
                    if ('default' === $defaultColor && (null === $nativeColor || \in_array(strtolower(trim($nativeColor)), ['default', 'initial'], true))) {
                        $style['color'] = 'default';
                    }
                    $link = null === $image['href'] ? null : RichTextWidget::linkOpening($image['href'], RichTextWidget::linkId($widget, $image['link-index'] ?? 0));
                    $key = [$widget->getRenderRevision(), $tokens, $style, $media->getKey(), $link, $context->getFontRegistry()];
                    $cached = $this->inlineImageProviders[$element] ?? null;
                    $providers[$element] = null !== $cached && $cached[0] === $key ? $cached[1] : new NativeContentAdapter(\WeakReference::create($widget), $widget->getRenderRevision(), $style, $this->metrics, $context->getFontRegistry(), $media, static function (RichTextWidget $source, $inner) use ($tokens, $media, $link): array {
                        $prefix = ($link ?? '').$inner->getStyle()->getAnsiRestore();
                        $lines = array_map(static fn (string $part): string => $prefix.$part, mb_str_split($tokens, max(1, $inner->getColumns())));
                        $lines = $source->renderInlineImages($lines, $media->colorScheme);
                        if (null !== $link) {
                            $lines = array_map(static fn (string $line): string => $line."\x1b]8;;\x07", $lines);
                        }

                        return $lines;
                    }, $this->metrics->cellWidth);
                    $this->inlineImageProviders[$element] = [$key, $providers[$element]];
                    continue;
                }
                if ($widget instanceof TabsWidget) {
                    $providers[$element] = $this->tabAdapters[] = new TabsContentAdapter($widget, $style, $this->metrics, $context->getFontRegistry(), $media, function (AbstractWidget $body, $inner) use ($snapshot, $projection, $providers, &$states, $constraints, $media, $cell, $rows): array {
                        $node = $snapshot['nodes'][$body];
                        $box = new BoxBuilder($snapshot['styles'], $snapshot['resolver'], $this->resources, $states, static fn (\Dom\Element $node) => $providers[$node] ?? null, $projection['groups'], $this->usedStyles)->build($node);
                        $fragment = new LayoutEngine($this->metrics, $constraints, TerminalColorPalette::get(), $media->colorScheme->value, null, TerminalColorPalette::lookupStandardName(...), $this->resources)->layout($box, $inner->getColumns() * $cell->width, $inner->getRows() * $cell->height);
                        $display = new Painter($this->metrics, null, $constraints)->paint($fragment);
                        $result = new RenderingResult($snapshot['document'], $snapshot['styles'], $fragment, $display, $this->metrics, null, $constraints);
                        $paintRows = $this->paintRows($fragment, $inner->getRows(), $rows);
                        $lines = new DisplayListRenderer($this->metrics, $media, $this->backgroundImages ??= new \Symfony\Bridge\TuiHtml\Internal\BackgroundImageCache())->render($display, $inner->getColumns(), $paintRows);

                        return ['result' => $result, 'lines' => $lines, 'natural-rows' => min($inner->getRows(), max(0, (int) ceil($fragment->bounds->height / $cell->height)))];
                    });
                    continue;
                }
                $surfaceBackground = self::isNativePopup($widget) && 'absolute' === ($style['position'] ?? null) ? (Value::color($style['background-color'] ?? 'transparent', $media, $this->sheet->getBackdropColor($widget)) ?: $this->sheet->getBackdropColor($widget) ?: Color::named('default')) : null;
                $providerKey = [$surfaceBackground?->toBackgroundCode(), $widget instanceof CollapsibleWidget ? 'summary' : '', $widget->getRenderRevision(), $style, $media->getKey(), $context->getFontRegistry()];
                $cached = $this->providers[$widget] ?? null;
                $adapter = null !== $cached && $cached[0] === $providerKey ? $cached[1] : new NativeContentAdapter(\WeakReference::create($widget), $widget->getRenderRevision(), $style, $this->metrics, $context->getFontRegistry(), $media, $widget instanceof CollapsibleWidget ? static fn (CollapsibleWidget $source, $context): array => $source->renderHeader($context) : null, $widget instanceof CollapsibleWidget ? 0.0 : null, $surfaceBackground);
                $this->providers[$widget] = [$providerKey, $adapter];
                $providers[$element] = $adapter;
            }
            foreach ($snapshot['nodes'] as $widget) {
                if ($widget instanceof ContainerWidget) {
                    $node = $snapshot['nodes'][$widget];
                    $state = ['scroll-x' => ContainerState::of($widget)->getScrollLeft() * $cell->width, 'scroll-y' => $widget->getScrollTop() * $cell->height];
                    foreach ($previousLayouts as $previousLayout) {
                        if (null === $previousScroll = $previousLayout->getScrollGeometry($node)) {
                            continue;
                        }
                        $snapType = $previousLayout->getComputedStyle($node)['scroll-snap-type'] ?? 'none';
                        if ($previousScroll->maxX > 0 && \Symfony\Component\HtmlRenderer\Layout\ScrollSnapGeometry::mode($snapType, false)) {
                            $state['scroll-snap-x'] = $previousScroll->offsetX;
                        }
                        if ($previousScroll->maxY > 0 && \Symfony\Component\HtmlRenderer\Layout\ScrollSnapGeometry::mode($snapType, true)) {
                            $state['scroll-snap-y'] = $previousScroll->offsetY;
                        }
                        break;
                    }
                    $states[$node] = $state;
                }
                $anchor = ($this->implicitAnchors[$widget] ?? null)?->get();
                if (null !== $anchor && isset($snapshot['nodes'][$anchor])) {
                    $node = $snapshot['nodes'][$widget];
                    $states[$node] = array_replace($states[$node] ?? [], ['anchor' => $snapshot['nodes'][$anchor]]);
                }
            }
            $box = new BoxBuilder($styles, $snapshot['resolver'], $this->resources, $states, static fn (\Dom\Element $element) => $providers[$element] ?? null, $projection['groups'], $this->usedStyles)->build($projection['root']);
            $fragment = new LayoutEngine($this->metrics, $constraints, TerminalColorPalette::get(), $media->colorScheme->value, null, TerminalColorPalette::lookupStandardName(...), $this->resources)->layout($box, $columns * $cell->width, $rows * $cell->height);
            $display = new Painter($this->metrics, null, $constraints)->paint($fragment);
            $this->result = new RenderingResult($snapshot['document'], $styles, $fragment, $display, $this->metrics, null, $constraints);
            $this->projectRects($root, $rows, $columns, $providers);

            return [$styles, $fragment, $providers, $states, $display];
        };
        $trial = $snapshot['layout-trial'] ?? null;
        $initial = $trial?->getInitial();
        if (null !== $initial) {
            $snapshot['styles'] = $initial['styles'];
            $snapshot['resolver'] = $initial['resolver'];
        }
        [$styles, $fragment, $providers, $states, $display] = $build();
        if (null !== $trial) {
            $metrics = $this->metricsForLayout($styles, $fragment, $states);
            $seen = [];
            $stable = false;
            for ($pass = 0; $pass < 8; ++$pass) {
                $fingerprint = \Symfony\Component\HtmlRenderer\Layout\ContainerMetrics::fingerprint($metrics);
                if (isset($seen[$fingerprint])) {
                    break;
                }
                $seen[$fingerprint] = true;
                $candidate = $trial->resolve($metrics);
                $snapshot['styles'] = $candidate['styles'];
                $snapshot['resolver'] = $candidate['resolver'];
                [$styles, $fragment, $providers, $states, $display] = $build();
                $metrics = $this->metricsForLayout($styles, $fragment, $states);
                if ($fingerprint === \Symfony\Component\HtmlRenderer\Layout\ContainerMetrics::fingerprint($metrics)) {
                    $stable = true;
                    break;
                }
            }
            if (!$stable) {
                $candidate = $initial;
                $snapshot['styles'] = $initial['styles'];
                $snapshot['resolver'] = $initial['resolver'];
                [$styles, $fragment, $providers, $states, $display] = $build();
            }
            $trial->commit($candidate);
        }
        $height = $context->shouldFillRows() ? $rows : min($rows, max(0, (int) ceil($fragment->bounds->height / $cell->height)));
        $this->lines = new DisplayListRenderer($this->metrics, $media, $this->backgroundImages ??= new \Symfony\Bridge\TuiHtml\Internal\BackgroundImageCache())->render($display, $columns, $rows);
        $height = max($height, self::paintedHeight($this->lines));
        $this->lines = array_map(static fn (string $line): string => rtrim($line, ' '), \array_slice($this->lines, 0, $height));
        $this->key = [$root->getRenderRevision(), $media->getKey(), $snapshot['styles'], $context->getFontRegistry()];

        return $this->lines;
    }

    public function synchronizeDetachedContent(HtmlRootWidget $root): void
    {
        foreach ($this->detachedContents as $widget => $revision) {
            if (!$widget->isExpanded() && $widget->getContent()->getRenderRevision() !== $revision) {
                $this->sheet->releaseProjection($root);
                $root->invalidate();
                $this->detachedContents = new \WeakMap();

                return;
            }
        }
    }

    public function release(HtmlRootWidget $root): void
    {
        $this->sheet->releaseProjection($root);
        $this->result = null;
        $this->projection = null;
        $this->subLayouts = [];
        foreach ($this->tabAdapters as $adapter) {
            $adapter->dispose();
        }
        $this->tabAdapters = [];
        $this->key = null;
        $this->lines = [];
        $this->rects = new \SplObjectStorage();
        $this->providers = new \WeakMap();
        $this->inlineImageProviders = new \WeakMap();
        $this->preparedInlineImages = new \SplObjectStorage();
        $this->usedStyles = new \SplObjectStorage();
        $this->nativeProperties = new \SplObjectStorage();
        $this->detachedContents = new \WeakMap();
    }

    public function getWidgetRect(AbstractWidget $widget): ?WidgetRect
    {
        return $this->rects[$widget] ?? null;
    }

    public function getEmbeddedRootRect(HtmlRootWidget $root): ?WidgetRect
    {
        if (null === $this->projection || null === $this->result) {
            return null;
        }
        foreach ($this->projection['replacements'] as $node) {
            $widget = $this->projection['replacements'][$node];
            if (!$widget instanceof FrameWidget || ($widget->all()[0] ?? null) !== $root) {
                continue;
            }
            foreach ([['result' => $this->result, 'x' => 0.0, 'y' => 0.0, 'clip' => null], ...$this->subLayouts] as $body) {
                foreach (PaintOrder::build($body['result']->getLayout()) as $step) {
                    $fragment = $step['fragment'];
                    if ('content' !== $step['type'] || $fragment->node !== $node || null === $fragment->content) {
                        continue;
                    }
                    $bounds = $fragment->bounds;
                    $rect = new WidgetRect((int) floor($bounds->y / $this->metrics->cellHeight), (int) floor($bounds->x / $this->metrics->cellWidth), (int) floor($bounds->width / $this->metrics->cellWidth), (int) ceil($bounds->height / $this->metrics->cellHeight));
                    foreach ($step['clips'] as $clip) {
                        $bounds = $clip['rect'];
                        $rect = $rect->withClip([$clip['clip-y'] ? (int) floor($bounds->y / $this->metrics->cellHeight) : \PHP_INT_MIN, $clip['clip-x'] ? (int) floor($bounds->x / $this->metrics->cellWidth) : \PHP_INT_MIN, $clip['clip-y'] ? (int) ceil(($bounds->y + $bounds->height) / $this->metrics->cellHeight) : \PHP_INT_MAX, $clip['clip-x'] ? (int) ceil(($bounds->x + $bounds->width) / $this->metrics->cellWidth) : \PHP_INT_MAX]);
                    }

                    $rect = $rect->withOffset((int) floor($body['y'] / $this->metrics->cellHeight), (int) floor($body['x'] / $this->metrics->cellWidth));

                    return null === $body['clip'] ? $rect : $rect->withClip($body['clip']);
                }
            }
        }

        return null;
    }

    public function revealEmbedded(HtmlRootWidget $root, WidgetRect $target): ?WidgetRect
    {
        if (null === $this->result || null === $this->projection || null === $origin = $this->getEmbeddedRootRect($root)) {
            return null;
        }
        foreach ($this->projection['replacements'] as $node) {
            $frame = $this->projection['replacements'][$node];
            if (!$frame instanceof FrameWidget || ($frame->all()[0] ?? null) !== $root) {
                continue;
            }
            $mapped = $target->withOffset($origin->row, $origin->col);
            $bounds = new \Symfony\Component\HtmlRenderer\Geometry\Rect($mapped->col * $this->metrics->cellWidth, $mapped->row * $this->metrics->cellHeight, $mapped->columns * $this->metrics->cellWidth, $mapped->rows * $this->metrics->cellHeight);
            if (null !== $offset = $this->result->getScrollOffsetToRevealBounds($bounds, $node)) {
                $geometry = $this->result->getScrollGeometry($node);
                $mapped = $mapped->withOffset((int) round(($geometry->offsetY - $offset[1]) / $this->metrics->cellHeight), (int) round(($geometry->offsetX - $offset[0]) / $this->metrics->cellWidth));
                $frame->scrollTo((int) round($offset[1] / $this->metrics->cellHeight));
                ContainerState::of($frame)->scrollToColumn((int) round($offset[0] / $this->metrics->cellWidth));
            }
            $this->reveal($frame);

            return $mapped;
        }

        return null;
    }

    public function getResult(): ?RenderingResult
    {
        return $this->result;
    }

    public function reveal(AbstractWidget $widget): void
    {
        if (null === $this->projection || null === $this->result) {
            return;
        }
        $element = null;
        foreach ($this->projection['owners'] as $node) {
            if ($node instanceof \Dom\Element && $this->projection['owners'][$node] === $widget) {
                $element = $node;
                break;
            }
        }
        if (null === $element) {
            return;
        }
        for ($parent = $widget->getParent(); null !== $parent && !$parent instanceof FrameWidget; $parent = $parent->getParent()) {
            if (!$parent instanceof ContainerWidget) {
                continue;
            }
            foreach ($this->projection['owners'] as $node) {
                if (!$node instanceof \Dom\Element || $this->projection['owners'][$node] !== $parent || null === $offset = $this->result->getScrollOffsetToReveal($element, $node, false, ['nearest', 'start'])) {
                    continue;
                }
                $parent->scrollTo((int) round($offset[1] / $this->metrics->cellHeight));
                ContainerState::of($parent)->scrollToColumn((int) round($offset[0] / $this->metrics->cellWidth));
            }
        }
    }

    public function getLinkAt(int $row, int $column): ?string
    {
        $hit = $this->hitWitness($row, $column);
        $element = $this->linkElementAt($row, $column, $hit);
        $image = $hit['element'] ?? null;
        if (null === ($hit['axis'] ?? null) && null === ($hit['control'] ?? null) && null !== $image && isset($this->projection['inline-images'][$image])) {
            $descriptor = $this->projection['inline-images'][$image];
            $owner = $this->projection['owners'][$image];
            if (null !== $descriptor['href'] && null !== $descriptor['link-index']) {
                return RichTextWidget::linkId($owner, $descriptor['link-index']);
            }
        }
        for (; null !== $element; $element = $element->parentElement) {
            if ('a' !== $element->localName || '' === trim($element->getAttribute('href') ?? '')) {
                continue;
            }
            $widget = $this->projection['owners'][$element] ?? null;
            if (!$widget instanceof RichTextWidget) {
                return null;
            }
            $index = 0;
            foreach ($this->projection['owners'] as $node) {
                if (!$node instanceof \Dom\Element || 'a' !== $node->localName || '' === trim($node->getAttribute('href') ?? '') || $this->projection['owners'][$node] !== $widget) {
                    continue;
                }
                if ($node === $element) {
                    return 'tui-link-'.spl_object_id($widget).'-'.$index;
                }
                ++$index;
            }
        }

        return null;
    }

    public function getScrollControlAt(int $row, int $column): ?array
    {
        if (null === $this->result || null === $this->projection) {
            return null;
        }
        $control = $this->hitWitness($row, $column)['control'] ?? null;
        if (null === $control || !($widget = $this->projection['owners'][$control['element']] ?? null) instanceof \Symfony\Component\Tui\Widget\ContainerWidget) {
            return null;
        }

        if ('marker' === $control['action']) {
            $target = $this->projection['owners'][$control['target']] ?? null;
            foreach (ContainerState::of($widget)->getScrollMarkers()[1] as $index => [$markerTarget]) {
                if ($markerTarget === $target) {
                    return [$widget, 'marker:'.$index];
                }
            }

            return null;
        }

        return [$widget, $control['action']];
    }

    public function getWidgetAt(int $row, int $column): ?AbstractWidget
    {
        if ($row < 0 || $column < 0 || $row >= $this->viewport[1] || $column >= $this->viewport[0]) {
            return null;
        }
        $element = $this->hitWitness($row, $column)['element'] ?? null;
        for (; null !== $element; $element = $element->parentElement) {
            if (null !== $widget = $this->projection['owners'][$element] ?? null) {
                return $widget;
            }
        }

        return null;
    }

    public function getFragmentLinkAt(int $row, int $column): ?string
    {
        $element = $this->linkElementAt($row, $column);

        return null === $element ? null : \Symfony\Bridge\TuiHtml\Internal\FragmentLink::target($element->getAttribute('href') ?? '');
    }

    private function linkElementAt(int $row, int $column, ?array $hit = null): ?\Dom\Element
    {
        $hit ??= $this->hitWitness($row, $column);
        if (null !== ($hit['axis'] ?? null) || null !== ($hit['control'] ?? null)) {
            return null;
        }
        $element = $hit['element'] ?? null;
        for (; null !== $element; $element = $element->parentElement) {
            if ('a' === $element->localName && '' !== trim($element->getAttribute('href') ?? '')) {
                return $element;
            }
        }

        return null;
    }

    private function hitWitness(int $row, int $column): ?array
    {
        if (null === $this->result || null === $this->projection) {
            return null;
        }
        $hit = $this->result->getHitWitness($column * $this->metrics->cellWidth, $row * $this->metrics->cellHeight);
        foreach (array_reverse($this->subLayouts) as $body) {
            [$top, $left, $bottom, $right] = $body['clip'];
            if ($row >= $top && $row < $bottom && $column >= $left && $column < $right && $body['host-result']->isContentPointVisible($body['host'], $column * $this->metrics->cellWidth - $body['parent-x'], $row * $this->metrics->cellHeight - $body['parent-y'])) {
                $hit = $body['result']->getHitWitness($column * $this->metrics->cellWidth - $body['x'], $row * $this->metrics->cellHeight - $body['y']) ?? $hit;
                break;
            }
        }

        return $hit;
    }

    /**
     * @param \SplObjectStorage<\Dom\Element, array<string, string>>   $styles
     * @param \SplObjectStorage<\Dom\Element, array<array-key, mixed>> $states
     */
    private function metricsForLayout(\SplObjectStorage $styles, Fragment $fragment, \SplObjectStorage $states): \SplObjectStorage
    {
        $metrics = \Symfony\Component\HtmlRenderer\Layout\ContainerMetrics::collect($styles, $fragment, $states);
        foreach ($this->subLayouts as $body) {
            $source = \Symfony\Component\HtmlRenderer\Layout\ContainerMetrics::collect($styles, $body['result']->getLayout(), $states);
            foreach ($source as $node) {
                $entry = $source[$node];
                if (!isset($entry['width'])) {
                    continue;
                }
                $entry['bounds'][0] += $body['x'];
                $entry['bounds'][1] += $body['y'];
                if (isset($entry['scroll']['port-x'])) {
                    $entry['scroll']['port-x'] += $body['x'];
                    $entry['scroll']['port-y'] += $body['y'];
                }
                $metrics[$node] = $entry;
            }
        }

        return $metrics;
    }

    private static function isNativePopup(AbstractWidget $widget): bool
    {
        return $widget instanceof \Symfony\Bridge\TuiHtml\Widget\SelectListWidget && ($widget->getParent() instanceof \Symfony\Bridge\TuiHtml\Widget\DropdownWidget || $widget->getParent() instanceof \Symfony\Bridge\TuiHtml\Widget\ComboboxWidget);
    }

    private function prepareNativePolicies(array $snapshot): \SplObjectStorage
    {
        $colors = new \SplObjectStorage();
        $this->usedStyles = new \SplObjectStorage();
        $this->nativeProperties = new \SplObjectStorage();
        foreach ($snapshot['nodes'] as $widget) {
            $node = $snapshot['nodes'][$widget];
            $own = StyleBridge::get($widget);
            foreach (['column' => $own?->getColumnSpan(), 'row' => $own?->getRowSpan()] as $axis => $span) {
                $attribute = 'column' === $axis ? (\in_array($node->localName, ['col', 'colgroup'], true) ? 'span' : 'colspan') : 'rowspan';
                if (null !== $span && (int) ($node->getAttribute($attribute) ?? '1') !== $span) {
                    throw new \LogicException('Native span overrides that differ from the semantic mirror require used-span projection.');
                }
            }
            $native = null === $this->nativeStyleResolver ? (StyleBridge::get($widget) ?? new Style()) : ($this->nativeStyleResolver)($widget, $this->colorMedia);
            $color = $native->getColor();
            $this->nativeProperties[$node] = ['color' => null !== $color, 'hidden' => null !== $native->getHidden(), 'maxColumns' => null !== $native->getMaxColumns(), 'position' => null !== $native->getPosition()];
            if (null !== $color) {
                $colors[$node] = ['' => ['box-sizing' => 'border-box', 'overflow-wrap' => 'anywhere', 'color' => $this->colorToken($color)]];
            }
            if (null === $color) {
                $colors[$node] = ['' => ['box-sizing' => 'border-box', 'overflow-wrap' => 'anywhere']];
            }
            if ($widget instanceof TabsWidget || $widget instanceof \Symfony\Bridge\TuiHtml\Widget\SliderWidget || $widget instanceof \Symfony\Component\Tui\Widget\ProgressBarWidget) {
                $policy = $colors[$node];
                $policy['']['width'] = '100%';
                $colors[$node] = $policy;
            }
            if ($widget instanceof FrameWidget) {
                $policy = $colors[$node];
                $policy['']['max-width'] = '100%';
                $colors[$node] = $policy;
            }
            $parent = $widget->getParent();
            if (self::isNativePopup($widget) && \Symfony\Bridge\TuiHtml\Style\PositionMode::Absolute === $native->getPosition()?->mode && null !== $width = $native->getMaxColumns()) {
                $policy = $colors[$node];
                $policy['']['width'] = $width.'ch';
                $colors[$node] = $policy;
            }
            if ($parent instanceof ContainerWidget && ContainerState::of($parent)->isInlineFlow() && CssStyleSheet::LINE_TAG === ($snapshot['descriptions'][$parent][0] ?? null)) {
                $policy = $colors[$node];
                $policy['']['vertical-align'] = 'middle';
                if ($widget instanceof \Symfony\Bridge\TuiHtml\Widget\ComboboxWidget) {
                    $policy['']['display'] = 'inline-block';
                }
                $colors[$node] = $policy;
            }
            if ($widget instanceof CollapsibleWidget && null !== $summary = \Symfony\Component\HtmlRenderer\Html\Details::summary($node)) {
                $colors[$summary] = ['' => ['box-sizing' => 'border-box', 'width' => '100%']];
            }
            if (null !== $own = StyleBridge::get($widget)?->getColor()) {
                $this->usedStyles[$node] = ['' => ['color' => $this->colorToken($own)]];
            }
        }

        foreach ($this->preparedInlineImages as $element) {
            $policy = $colors[$element] ?? [];
            $policy['']['max-width'] = '100%';
            $colors[$element] = $policy;
        }

        return $colors;
    }

    private function colorToken(Color $color): string
    {
        return $color->toForegroundCode() === Color::named('default')->toForegroundCode() ? ('default' === $this->defaultForeground ? 'default' : $this->defaultForeground) : $color->toHex();
    }

    private function supportsOwnStyle(AbstractWidget $widget): bool
    {
        $style = $widget->getStyle();
        if (null === $style) {
            return true;
        }
        foreach ((new \ReflectionMethod($style, '__construct'))->getParameters() as $parameter) {
            if (\in_array($parameter->name, ['color', 'hidden', 'maxColumns'], true)) {
                continue;
            }
            $getter = 'get'.ucfirst($parameter->name);
            if (method_exists($style, $getter) && null !== $style->{$getter}()) {
                return false;
            }
        }

        return true;
    }

    private function prepareWidgets(AbstractWidget $widget, bool $root = false): void
    {
        if (!$root) {
            $widget->beforeRender();
        }
        if ($widget instanceof FrameWidget) {
            return;
        }
        if ($widget instanceof ParentInterface) {
            if (!$widget instanceof ContainerWidget && !$widget instanceof CollapsibleWidget && !$widget instanceof TabsWidget) {
                throw new \LogicException('This facade prototype does not yet project self-drawing native parents.');
            }
            if (!$this->supportsOwnStyle($widget)) {
                throw new \LogicException('Native container style overrides require presentation projection before this facade can render them.');
            }
            foreach ($widget->all() as $child) {
                $this->prepareWidgets($child);
            }
        } elseif (!$this->supportsOwnStyle($widget)) {
            throw new \LogicException('Native widget style overrides require presentation projection before this facade can render them.');
        }
    }

    private function projectRects(AbstractWidget $root, int $rows, int $columns, ?\SplObjectStorage $providers = null): void
    {
        if ($root->getParent() instanceof FrameWidget) {
            $rows = max($rows, (int) ceil($this->result->getLayout()->bounds->height / $this->metrics->cellHeight));
        }
        $bounds = new \SplObjectStorage();
        foreach ($this->projection['owners'] as $node) {
            $widget = $this->projection['owners'][$node];
            $found = $bounds[$widget] ?? [];
            array_push($found, ...$this->result->getBounds($node));
            $bounds[$widget] = $found;
        }
        $this->subLayouts = [];
        $clips = new \SplObjectStorage();
        $collect = function ($fragment, float $x = 0.0, float $y = 0.0, ?array $ancestorClip = null, ?RenderingResult $parentResult = null) use (&$collect, $providers, $bounds, $clips): void {
            if (null !== $fragment->content && null !== $fragment->node && null !== $providers && ($adapter = $providers[$fragment->node] ?? null) instanceof TabsContentAdapter && null !== $body = $adapter->getProjection($fragment->content)) {
                $left = $x + $fragment->bounds->x + $body['column'] * $this->metrics->cellWidth;
                $top = $y + $fragment->bounds->y + $body['row'] * $this->metrics->cellHeight;
                $clip = [(int) floor($top / $this->metrics->cellHeight), (int) floor($left / $this->metrics->cellWidth), (int) floor($top / $this->metrics->cellHeight) + $body['rows'], (int) floor($left / $this->metrics->cellWidth) + $body['columns']];
                if (null !== $ancestorClip) {
                    $clip = [max($clip[0], $ancestorClip[0]), max($clip[1], $ancestorClip[1]), min($clip[2], $ancestorClip[2]), min($clip[3], $ancestorClip[3])];
                }
                $this->subLayouts[] = ['result' => $body['result'], 'x' => $left, 'y' => $top, 'clip' => $clip, 'host' => $fragment->node, 'host-result' => $parentResult ?? $this->result, 'parent-x' => $x, 'parent-y' => $y];
                foreach ($this->projection['owners'] as $node) {
                    $widget = $this->projection['owners'][$node];
                    $pieces = $body['result']->getBounds($node);
                    if (!$pieces) {
                        continue;
                    }
                    $found = $bounds[$widget] ?? [];
                    foreach ($pieces as $piece) {
                        $found[] = new \Symfony\Component\HtmlRenderer\Geometry\Rect($piece->x + $left, $piece->y + $top, $piece->width, $piece->height);
                    }
                    $bounds[$widget] = $found;
                    $clips[$widget] = $clip;
                }
                $collect($body['result']->getLayout(), $left, $top, $clip, $body['result']);
            }
            foreach ($fragment->children as $child) {
                $collect($child, $x, $y, $ancestorClip, $parentResult);
            }
        };
        $collect($this->result->getLayout());
        foreach ($this->projection['owners'] as $node) {
            $widget = $this->projection['owners'][$node];
            if (!$node instanceof \Dom\Element || !$widget instanceof \Symfony\Component\Tui\Widget\ContainerWidget) {
                continue;
            }
            $geometry = $this->result->getScrollGeometry($node);
            foreach ($this->subLayouts as $body) {
                $geometry = $body['result']->getScrollGeometry($node) ?? $geometry;
            }
            $state = ContainerState::of($widget);
            if (null === $geometry) {
                $state->setScrollMetrics(0, 0, 0);
                $state->setHorizontalScrollMetrics(0, 0, 0);
                continue;
            }
            $state->setScrollMetrics((int) round($geometry->offsetY / $this->metrics->cellHeight), (int) ceil(($geometry->port->height + $geometry->maxY) / $this->metrics->cellHeight), (int) floor($geometry->port->height / $this->metrics->cellHeight));
            $state->setHorizontalScrollMetrics((int) round($geometry->offsetX / $this->metrics->cellWidth), (int) ceil(($geometry->port->width + $geometry->maxX) / $this->metrics->cellWidth), (int) floor($geometry->port->width / $this->metrics->cellWidth));
        }
        $this->rects = new \SplObjectStorage();
        $visit = function (AbstractWidget $widget) use (&$visit, $bounds, $clips, $rows, $columns): ?WidgetRect {
            $pieces = $bounds[$widget] ?? [];
            if (!$pieces && $widget instanceof ParentInterface) {
                foreach ($widget->all() as $child) {
                    if (null !== $rect = $visit($child)) {
                        $pieces[] = new \Symfony\Component\HtmlRenderer\Geometry\Rect($rect->col * $this->metrics->cellWidth, $rect->row * $this->metrics->cellHeight, $rect->columns * $this->metrics->cellWidth, $rect->rows * $this->metrics->cellHeight);
                    }
                }
            } elseif ($widget instanceof ParentInterface) {
                foreach ($widget->all() as $child) {
                    $visit($child);
                }
            }
            if (!$pieces) {
                return null;
            }
            $left = (int) floor(min(array_map(static fn ($r) => $r->x, $pieces)) / $this->metrics->cellWidth);
            $top = (int) floor(min(array_map(static fn ($r) => $r->y, $pieces)) / $this->metrics->cellHeight);
            $right = (int) ceil(max(array_map(static fn ($r) => $r->x + $r->width, $pieces)) / $this->metrics->cellWidth);
            $bottom = (int) ceil(max(array_map(static fn ($r) => $r->y + $r->height, $pieces)) / $this->metrics->cellHeight);

            return $this->rects[$widget] = new WidgetRect($top, $left, max(0, $right - $left), max(0, $bottom - $top), [], $clips[$widget] ?? [0, 0, $rows, $columns]);
        };
        $visit($root);
    }

    private function positionStyle(Position $position): array
    {
        if (null !== $position->anchor || null !== $position->area || $position->anchorEdges || $position->flips || array_filter($position->translate) || $position->anchorSizes || $position->tries || array_filter($position->anchorCenter)) {
            throw new \LogicException('Complex native positioned content requires an explicit presentation projection.');
        }
        $style = ['position' => $position->mode->value, 'z-index' => (string) $position->zIndex];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $unit = \in_array($side, ['top', 'bottom'], true) ? 'lh' : 'ch';
            $offset = $position->{$side};
            $percent = $position->percents[$side] ?? null;
            if (null !== $offset) {
                $style[$side] = $offset.$unit;
            } elseif ($percent instanceof PercentLength) {
                if (1 !== \count($percent->terms)) {
                    throw new \LogicException('Native piecewise percentage insets require an explicit presentation projection.');
                }
                $term = $percent->terms[0];
                if (!\is_array($term) || 2 !== \count($term)) {
                    throw new \LogicException('Native piecewise percentage insets require an explicit presentation projection.');
                }
                if (!is_finite($term[0]) || !is_finite($term[1])) {
                    throw new \LogicException('Native piecewise percentage insets require an explicit presentation projection.');
                }
                $style[$side] = 0.0 === (float) $term[1] ? $term[0].'%' : 'calc('.$term[0].'% '.($term[1] < 0 ? '- ' : '+ ').abs($term[1]).$unit.')';
            } elseif (null === $percent) {
                $style[$side] = 'auto';
            } elseif (\is_int($percent)) {
                $style[$side] = $percent.'%';
            } else {
                throw new \LogicException('Native percentage insets require integer values.');
            }
        }

        return $style;
    }

    private function paintRows(Fragment $root, int $available, int $viewport): int
    {
        $rows = min($available, max(0, (int) ceil($root->bounds->height / $this->metrics->cellHeight)));
        foreach (PaintOrder::build($root) as $step) {
            if ('background' !== $step['type']) {
                continue;
            }
            $fragment = $step['fragment'];
            if (null === $fragment->topLayer && !\in_array($fragment->style['position'] ?? 'static', ['absolute', 'fixed'], true)) {
                continue;
            }
            $bounds = $fragment->bounds;
            $matrix = $step['matrix'];
            $bottom = -\INF;
            foreach ([$bounds->x, $bounds->x + $bounds->width] as $x) {
                foreach ([$bounds->y, $bounds->y + $bounds->height] as $y) {
                    $bottom = max($bottom, $matrix[1] * $x + $matrix[3] * $y + $matrix[5]);
                }
            }
            $extra = is_finite($bottom) ? max(0, min($viewport, (int) ceil($bottom / $this->metrics->cellHeight))) : $viewport;
            $rows = max($rows, $extra);
        }

        return $rows;
    }

    private static function paintedHeight(array $lines): int
    {
        for ($row = \count($lines) - 1; $row >= 0; --$row) {
            $line = preg_replace('/\x1b\[(?:0)?m/', '', $lines[$row]);
            if ('' !== rtrim($line, ' ')) {
                return $row + 1;
            }
        }

        return 0;
    }
}
