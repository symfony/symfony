<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Css;

use Symfony\Bridge\TuiHtml\Html\NativeStyleProjection;
use Symfony\Bridge\TuiHtml\Render\TerminalColorPalette;
use Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\CascadePriority;
use Symfony\Component\HtmlRenderer\Css\ColorScheme as NativeColorScheme;
use Symfony\Component\HtmlRenderer\Css\CssRule;
use Symfony\Component\HtmlRenderer\Css\Declaration;
use Symfony\Component\HtmlRenderer\Css\ParsedStylesheet;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

/** @internal */
final class CascadeResolver
{
    private StyleResolver $resolver;
    private ?Timeline $timeline = null;
    private ?MediaContext $timelineMedia = null;
    private ?\Dom\Document $preparedDocument = null;
    private ?string $preparedMediaKey = null;
    private ?\SplObjectStorage $preparedStyleParents = null;
    private ?string $mediaKey = null;
    private ?\Dom\Element $implicitColorRoot = null;
    public const PROPERTIES = ['font-weight', 'font-style', 'font-family', 'color', 'background-color'];
    private const ORIGIN_PROPERTIES = ['color' => 1, 'font-weight' => 2, 'font-style' => 4, 'font-family' => 8, 'font-size' => 16, 'line-height' => 32, 'direction' => 64, 'display' => 128, 'list-style-type' => 256, 'list-style-position' => 512];

    private function configure(MediaContext $media, ?\Closure $valueResolver): void
    {
        if ($this->mediaKey !== $media->getKey()) {
            $this->resolver = new StyleResolver(new ResourceLoader(), new FontStyle('monospace', 16, 16), new TerminalTextMeasurer(), ['prefers-color-scheme' => ColorScheme::Dark === $media->colorScheme ? 'dark' : 'light'], '', TerminalColorPalette::get(), static fn (string $name, string $scheme): ?array => TerminalColorPalette::lookup($name, $scheme), $valueResolver, new TerminalLayoutMetrics());
            $this->mediaKey = $media->getKey();
        }
    }

    /**
     * @param \SplObjectStorage<\Dom\Element, array<string, string>> $styles
     *
     * @return array{\SplObjectStorage<\Dom\Element, array<string, string>>, \SplObjectStorage<\Dom\Element, array<string, string>>, \SplObjectStorage<\Dom\Element, array<string, true>>, \SplObjectStorage<\Dom\Element, int>, \WeakMap<\Dom\Element, string>}
     */
    private function managedProjection(StyleResolver $resolver, \SplObjectStorage $styles, MediaContext $media, ?\SplObjectStorage $styleParents = null): array
    {
        /** @var \SplObjectStorage<\Dom\Element, array<string, true>> $coverage */
        $coverage = new \SplObjectStorage();
        /** @var \SplObjectStorage<\Dom\Element, array<string, string>> $rawStyles */
        $rawStyles = new \SplObjectStorage();
        /** @var \SplObjectStorage<\Dom\Element, int> $origins */
        $origins = new \SplObjectStorage();
        /** @var \WeakMap<\Dom\Element, string> $lineOrigins */
        $lineOrigins = new \WeakMap();
        foreach ($styles as $element) {
            $parentElement = $styleParents[$element] ?? $element->parentElement;
            $record = $resolver->getCascadeRecord($element);
            $color = $record['declarations']['color'] ?? null;
            if ($element === $this->implicitColorRoot && !isset($record['animated']['color']) && 'user-agent' === ($color['origin'] ?? null) && ($color['priority'] ?? []) === CascadePriority::of(new Declaration('color', ''), \PHP_INT_MAX - 1, \PHP_INT_MAX, CssRule::UNLAYERED, true)) {
                // The embedding foreground seeds CSS values without becoming a native widget's own paint.
                unset($record['declarations']['color'], $record['specified']['color'], $record['cascaded']['color']);
            }
            $origin = null !== $parentElement ? ($origins[$parentElement] ?? 0) & ~self::ORIGIN_PROPERTIES['display'] : 0;
            $lineOrigin = null !== $parentElement ? ($lineOrigins[$parentElement] ?? null) : null;
            foreach ($record['declarations'] as $property => $declaration) {
                if ($declaration['valid'] && isset(self::ORIGIN_PROPERTIES[$property])) {
                    $origin |= self::ORIGIN_PROPERTIES[$property];
                    if ('line-height' === $property) {
                        $lineOrigin = is_numeric($declaration['value']) ? $declaration['value'] : null;
                    }
                }
            }
            if (!($origin & self::ORIGIN_PROPERTIES['direction']) && $element->hasAttribute('dir')) {
                $origin |= self::ORIGIN_PROPERTIES['direction'];
            }
            $origins[$element] = $origin;
            if (null !== $lineOrigin) {
                $lineOrigins[$element] = $lineOrigin;
            }
            $specified = isset($record['animated']) ? $record['specified'] + $record['animated'] : $record['specified'];
            $coverage[$element] = array_intersect_key($specified, array_flip(self::PROPERTIES)) + (null !== $parentElement ? array_diff_key($coverage[$parentElement] ?? [], ['background-color' => true]) : []);
            $parent = null !== $parentElement ? array_diff_key($rawStyles[$parentElement] ?? [], ['background-color' => true]) : [];
            $raw = $parent;
            foreach (array_intersect_key($record['cascaded'], array_flip(self::PROPERTIES)) as $property => $value) {
                $raw[$property] = match (true) {
                    'color' === $property && 'default' === ($record['resolved']['color'] ?? null) => 'default',
                    \in_array($value, ['inherit', 'unset'], true) => $parent[$property] ?? 'initial',
                    'font-weight' === $property && \in_array(strtolower(trim($value)), ['bolder', 'lighter'], true) => $styles[$element][$property],
                    default => $value,
                };
            }
            $rawStyles[$element] = $raw;
        }
        /** @var \SplObjectStorage<\Dom\Element, array<string, string>> $fonts */
        $fonts = new \SplObjectStorage();
        /** @var \SplObjectStorage<\Dom\Element, array<string, string>> $rawFonts */
        $rawFonts = new \SplObjectStorage();
        foreach ($styles as $element) {
            if (isset($styles[$element])) {
                $record = $resolver->getCascadeRecord($element);
                $semantic = $record['resolved'];
                $raw = $rawStyles[$element] ?? [];
                if (!empty($record['animated'])) {
                    $animated = array_intersect_key($styles[$element], $record['animated']);
                    $semantic = array_replace($semantic, $animated);
                    $raw = array_replace($raw, $animated);
                }
                $fonts[$element] = $this->managedValues($styles[$element], $semantic, $raw, $coverage[$element] ?? [], $media);
                $rawFonts[$element] = $rawStyles[$element] ?? [];
            }
        }

        return [$fonts, $rawFonts, $coverage, $origins, $lineOrigins];
    }

    /**
     * @param array<string, string> $style
     * @param array<string, string> $semantic
     * @param array<string, string> $raw
     * @param array<string, true>   $coverage
     *
     * @return array<string, string>
     */
    private function managedValues(array $style, array $semantic, array $raw, array $coverage, MediaContext $media): array
    {
        $specified = $coverage + array_filter($raw, static fn (string $value) => 'initial' === $value);
        $values = array_intersect_key($style + ['font-style' => 'normal'], $specified, array_flip(self::PROPERTIES));
        $rawColor = $raw['color'] ?? null;
        $unknown = null === $rawColor || preg_match('/\\bcurrentcolor\\b/i', $rawColor);
        if (null !== $rawColor && preg_match('/\\bcurrentcolor\\b/i', $rawColor)) {
            unset($values['color']);
        }
        if ($unknown && preg_match('/\\bcurrentcolor\\b/i', $semantic['background-color'] ?? '')) {
            unset($values['background-color']);
        }
        $scheme = NativeColorScheme::forced($style['color-scheme'] ?? '');
        $nodeMedia = null === $scheme ? $media : $media->withColorScheme('dark' === $scheme ? ColorScheme::Dark : ColorScheme::Light);
        foreach (['color', 'background-color'] as $property) {
            $token = $semantic[$property] ?? '';
            if (isset($values[$property]) && ($device = Value::opaqueColor($token, $nodeMedia)) instanceof Color && ($device->isThemed() || preg_match('/^\\d+$/', trim($token)) || str_starts_with(strtolower(trim($token)), 'light-dark('))) {
                $values[$property] = $token;
            }
            if ('default' === strtolower(trim($semantic[$property] ?? ''))) {
                $values[$property] = 'default';
            }
        }
        if (\in_array($raw['color'] ?? null, ['initial', 'default'], true)) {
            $values['color'] = $raw['color'];
        }

        return $values;
    }

    /**
     * Evaluates the existing compatibility widget mirror, preserving its distinct control parts.
     * Native document rendering sessions do not use this compatibility projection.
     *
     * @return \SplObjectStorage<\Dom\Element, array{computed: array<string, string>, raw: array<string, string>, pseudos: array<string, array{computed: array<string, string>, own: array<string, string>}>, matched-rules: list<int>, starting-style?: array<string, string>, scroll-driven?: true}>
     */
    public function cascadeMirror(\Dom\HTMLDocument $document, array $elements, ParsedStylesheet $stylesheet, MediaContext $media, array $containers, array $presentations, array $controlStates = [], ?\Closure $valueResolver = null, ?\Closure $parseInline = null, ?\SplObjectStorage $styleParents = null, ?\Closure $capture = null, ?string $defaultColor = null, ?\SplObjectStorage $nodeColors = null, ?float $time = null): \SplObjectStorage
    {
        $this->preparedDocument = null;
        $this->preparedMediaKey = null;
        $this->preparedStyleParents = null;
        $rootPolicy = $nodeColors[$document->documentElement] ?? null;
        $this->implicitColorRoot = null !== $defaultColor && !\is_string($rootPolicy) && !isset($rootPolicy['']['color']) ? $document->documentElement : null;
        $this->configure($media, $valueResolver);
        $resolver = $this->resolver;
        $states = [];
        $targets = iterator_to_array($document->querySelectorAll('*'));
        foreach ($styleParents ?? [] as $element) {
            $targets[] = $element;
        }
        foreach ($targets as $element) {
            $id = (int) ($element->getAttribute('data-tui-oid') ?? 0);
            $state = preg_split('/\\s+/', trim($element->getAttribute(Selector::STATE_ATTRIBUTE) ?? ''), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
            $state = [...$state, ...($controlStates[$id] ?? [])];
            if ('input' === $element->localName && \in_array(strtolower($element->getAttribute('type') ?? ''), ['checkbox', 'radio'], true)) {
                $state['checked'] = \in_array('checked', $state, true);
            }
            $hints = [];
            foreach (\Symfony\Component\HtmlRenderer\Css\DeclarationParser::parse($presentations[$id] ?? '') as $hint) {
                if (str_starts_with($hint->property, '--tui-cell-') || 'color-scheme' === $hint->property) {
                    $hints[] = $hint->property.':'.$hint->value;
                }
            }
            $state['presentation-style'] = implode(';', $hints);
            if (null !== $defaultColor && $element === $document->documentElement) {
                $state['user-agent-styles']['']['color'] = $defaultColor;
            }
            if (isset($nodeColors[$element])) {
                if (\is_string($nodeColors[$element])) {
                    $state['user-agent-styles']['']['color'] = $nodeColors[$element];
                } else {
                    foreach ($nodeColors[$element] as $role => $declarations) {
                        $state['user-agent-styles'][$role] = array_replace($state['user-agent-styles'][$role] ?? [], $declarations);
                    }
                }
            }
            if (null !== $parseInline) {
                $state['inline-declarations'] = $parseInline($element->getAttribute('style') ?? '');
            }
            $states[] = [$element, $state];
        }
        $rules = $stylesheet->rules;
        $registered = $stylesheet->registered;
        $matched = [];
        foreach ($rules as $index => $rule) {
            foreach ($rule->media as $query) {
                if (!MediaQuery::matchesQuery($query, $media)) {
                    continue 2;
                }
            }
            $matched[$index] = new CssRule($rule->selector->forNativeTree(), $rule->declarations, [], $rule->order, $rule->layer, $rule->userAgent, array_map(static fn ($query) => ContainerQuery::forPixels($query, 8, 16), $rule->containers), $rule->scoped, $rule->starting, $rule->context);
        }
        $stylesheet = new ParsedStylesheet($matched, $registered, $stylesheet->keyframes, $stylesheet->counterStyles, $stylesheet->functions, $stylesheet->positionTries);
        $trial = $baseTimeline = null;
        if (null !== $time) {
            $this->timelineMedia = $media;
            $this->timeline ??= new Timeline(Animation::listFromDeclarations(...), fn (string $from, string $to, float $t, string $property): string => Animation::between($from, $to, $t, $this->timelineMedia, $property), fn (string $from, string $to): string => Animation::add($from, $to, $this->timelineMedia), fn (string $from, string $to): bool => Animation::isInterpolable($from, $to, $this->timelineMedia), Animation::ease(...), static fn (float $t): float => round($t * 256) / 256);
            $active = new \SplObjectStorage();
            foreach ($targets as $element) {
                $active[$element] = true;
            }
            $this->timeline->setActiveElements($active);
            $baseTimeline = clone $this->timeline;
            $trial = clone $baseTimeline;
        }
        $width = max(1, $media->columns) * 8;
        $height = max(1, $media->rows) * 16;
        $metrics = null;
        if (null !== $time && array_any($containers, static fn (array $entry): bool => isset($entry['intrinsic-height']))) {
            $metrics = new \SplObjectStorage();
            foreach ($containers as $id => $entry) {
                if (isset($elements[$id])) {
                    $metrics[$elements[$id]] = $entry;
                }
            }
        }
        $styles = $resolver->resolve($document, $width, $height, $states, $trial, $time ?? 0, $metrics, $stylesheet, null, [], $styleParents, null !== $time);
        if (array_any($matched, static fn (CssRule $rule) => [] !== $rule->containers) || ($trial?->hasScrollAnimations() ?? false)) {
            $metrics = new \SplObjectStorage();
            foreach ($styles as $element) {
                $metrics[$element] = ['style' => $styles[$element]];
            }
            foreach ($containers as $id => $entry) {
                if (isset($elements[$id])) {
                    $metrics[$elements[$id]] = ['style' => $styles[$elements[$id]], ...$entry];
                }
            }
            $trial = null !== $this->timeline && null !== $time ? clone $this->timeline : null;
            $styles = $resolver->resolve($document, $width, $height, $states, $trial, $time ?? 0, $metrics, $stylesheet, null, [], $styleParents);
        }
        $layoutTrial = null;
        if (null !== $capture && $resolver->hasScrollControls()) {
            $frameResolver = clone $resolver;
            $owner = \WeakReference::create($this);
            $layoutTrial = new LayoutStyleTrial(
                ['styles' => $styles, 'resolver' => clone $resolver, 'timeline' => $trial],
                static function (\SplObjectStorage $metrics) use ($frameResolver, $baseTimeline, $document, $width, $height, $states, $time, $stylesheet, $styleParents): array {
                    $candidateResolver = clone $frameResolver;
                    $candidateTimeline = null !== $baseTimeline ? clone $baseTimeline : null;
                    $candidateStyles = $candidateResolver->resolve($document, $width, $height, $states, $candidateTimeline, $time ?? 0, $metrics, $stylesheet, null, [], $styleParents);

                    return ['styles' => $candidateStyles, 'resolver' => $candidateResolver, 'timeline' => $candidateTimeline];
                },
                static function (array $candidate) use ($owner, $capture): void {
                    $resolver = $owner->get() ?? throw new \LogicException('The layout style owner has been disposed.');
                    if (null !== ($candidate['timeline'] ?? null)) {
                        $resolver->timeline->adopt($candidate['timeline']);
                    }
                    $capture($candidate['styles'], $candidate['resolver'], null, true);
                },
            );
        }
        $deferred = false;
        $projected = $this->project($styles, $media, $styleParents, $capture, $layoutTrial, $deferred);
        if (!$deferred) {
            $layoutTrial?->dispose();
        }
        if (null !== $trial && !$deferred) {
            $this->timeline->adopt($trial);
            if (null === $metrics && !$resolver->hasContainerQueries() && !$resolver->hasScrollControls() && !$this->timeline->hasScrollAnimations() && !$this->timeline->hasLayoutTransitions()) {
                $this->preparedDocument = $document;
                $this->preparedMediaKey = $media->getKey();
                $this->preparedStyleParents = $styleParents;
            }
        }

        return $projected;
    }

    public function canAdvanceMirror(): bool
    {
        return null !== $this->preparedDocument;
    }

    public function hasPendingFrames(float $time): bool
    {
        return $this->timeline?->hasPendingFrames($time) ?? false;
    }

    /**
     * The caller must have verified that only time changed in its native mirror.
     *
     * @return \SplObjectStorage<\Dom\Element, array{computed: array<string, string>, raw: array<string, string>, pseudos: array<string, array{computed: array<string, string>, own: array<string, string>}>, matched-rules: list<int>, starting-style?: array<string, string>, scroll-driven?: true}>
     */
    public function advanceMirror(\Dom\Document $document, float $time, MediaContext $media, ?\Closure $capture = null): \SplObjectStorage
    {
        if ($document !== $this->preparedDocument || $media->getKey() !== $this->preparedMediaKey || null === $this->timeline) {
            throw new \Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException('The native cascade must be prepared again after its source or context changes.');
        }
        $trial = clone $this->timeline;
        $styles = $this->resolver->resolvePrepared($trial, $time);
        $projected = $this->project($styles, $media, $this->preparedStyleParents, $capture);
        $this->timeline->adopt($trial);

        return $projected;
    }

    /**
     * @param \SplObjectStorage<\Dom\Element, array<string, string>> $styles
     *
     * @return \SplObjectStorage<\Dom\Element, array{computed: array<string, string>, raw: array<string, string>, pseudos: array<string, array{computed: array<string, string>, own: array<string, string>}>, matched-rules: list<int>, starting-style?: array<string, string>, scroll-driven?: true}>
     */
    private function project(\SplObjectStorage $styles, MediaContext $media, ?\SplObjectStorage $styleParents, ?\Closure $capture, ?LayoutStyleTrial $layoutTrial = null, ?bool &$deferred = null): \SplObjectStorage
    {
        $resolver = $this->resolver;
        try {
            if (null !== $capture) {
                $deferred = true === $capture($styles, clone $resolver, $layoutTrial) && null !== $layoutTrial;
            }
            [$managed, $raw, $coverage, $origins, $lineOrigins] = $this->managedProjection($resolver, $styles, $media, $styleParents);
            /** @var \SplObjectStorage<\Dom\Element, array{computed: array<string, string>, raw: array<string, string>, pseudos: array<string, array{computed: array<string, string>, own: array<string, string>}>, matched-rules: list<int>, starting-style?: array<string, string>, scroll-driven?: true}> $projected */
            $projected = new \SplObjectStorage();
            $sharedRecords = [];
            $lastProjectedRow = null;
            foreach ($styles as $element) {
                if (!isset($styles[$element])) {
                    continue;
                }
                $record = $resolver->getCascadeRecord($element);
                // Capture may update animated used values without changing the resolver's records.
                $rowInput = !isset($record['starting-style']) && !$record['declarations'] ? [$record, $raw[$element], $coverage[$element], $managed[$element], $origins[$element] ?? 0, $lineOrigins[$element] ?? null, $styles[$element]] : null;
                if (null !== $rowInput && null !== $lastProjectedRow && $lastProjectedRow[0] === $rowInput) {
                    $projected[$element] = $lastProjectedRow[1];
                    continue;
                }

                $pseudoRecords = [];
                foreach ($record['pseudos'] ?? [] as $role => $pseudo) {
                    $pseudoRecords[$role] = $this->projectVariant($resolver, $element, $pseudo, $styles, $origins, $lineOrigins, array_diff_key($raw[$element], ['background-color' => true]), array_diff_key($coverage[$element], ['background-color' => true]), $media, $styleParents, $role);
                }
                $starting = null;
                if (isset($record['starting-style'])) {
                    $parent = $styleParents[$element] ?? $element->parentElement;
                    $starting = $this->projectVariant($resolver, $element, $record['starting-style'], $styles, $origins, $lineOrigins, null !== $parent ? array_diff_key($raw[$parent] ?? [], ['background-color' => true]) : [], null !== $parent ? array_diff_key($coverage[$parent] ?? [], ['background-color' => true]) : [], $media, $styleParents)['computed'];
                }
                $values = $this->projectRecord($resolver, $element, $record, $styles, $origins, $lineOrigins);
                if (isset($record['animated'])) {
                    $values = array_replace($values, array_intersect_key($styles[$element], $record['animated']));
                }
                $matched = $record['matched-rules'] ?? [];
                if (isset($record['starting-rules'])) {
                    $matched = [...$matched, ...$record['starting-rules']];
                }
                $rawValues = $raw[$element];
                if (!empty($record['animated'])) {
                    $rawValues = array_replace($rawValues, array_intersect_key($styles[$element], $record['animated']));
                }
                $scrollDriven = $record['scroll-driven'] ?? false;
                $record = ['computed' => array_replace(array_diff_key($values, array_flip(self::PROPERTIES)), $managed[$element]), 'raw' => $rawValues, 'pseudos' => $pseudoRecords, 'matched-rules' => $matched];
                if ($scrollDriven) {
                    $record['scroll-driven'] = true;
                }
                if (null !== $starting) {
                    $record['starting-style'] = $starting;
                }
                $key = serialize($record);
                if (\strlen($key) <= 8192 && \count($sharedRecords) < 1024) {
                    $record = $sharedRecords[$key] ??= $record;
                }
                $projected[$element] = $record;
                if (null !== $rowInput) {
                    $lastProjectedRow = [$rowInput, $record];
                }
            }

            return $projected;
        } finally {
            $resolver->releaseComputedStyles();
        }
    }

    /**
     * @param array{computed: array<string, string>, resolved: array<string, string>, cascaded: array<string, string>, specified: array<string, true>, declarations: array<string, array{value: string, source: string, source-value: string, expansion: list<string>, priority: array, order: array{int, int}, valid: bool, initial: bool, implicit: bool, explicit-candidate: array{0: string, ...}|null, ...}>, animated?: array<string, true>, ...} $record
     * @param \SplObjectStorage<\Dom\Element, array<string, string>>                                                                                                                                                                                                                                                                                                                                                                                    $styles
     * @param \SplObjectStorage<\Dom\Element, int>                                                                                                                                                                                                                                                                                                                                                                                                      $origins
     * @param \WeakMap<\Dom\Element, string>                                                                                                                                                                                                                                                                                                                                                                                                            $lineOrigins
     *
     * @return array{computed: array<string, string>, own: array<string, string>}
     */
    private function projectVariant(StyleResolver $resolver, \Dom\Element $element, array $record, \SplObjectStorage $styles, \SplObjectStorage $origins, \WeakMap $lineOrigins, array $parentRaw, array $parentCoverage, MediaContext $media, ?\SplObjectStorage $styleParents, ?string $role = null): array
    {
        $raw = $parentRaw;
        foreach (array_intersect_key($record['cascaded'], array_flip(self::PROPERTIES)) as $property => $value) {
            $raw[$property] = match (true) {
                \in_array($value, ['inherit', 'unset'], true) => $parentRaw[$property] ?? 'initial',
                'font-weight' === $property && \in_array(strtolower(trim($value)), ['bolder', 'lighter'], true) => $record['computed'][$property],
                default => $value,
            };
        }
        $coverage = array_intersect_key($record['specified'], array_flip(self::PROPERTIES)) + $parentCoverage;
        $semantic = $record['resolved'];
        $animated = array_intersect_key($record['computed'], $record['animated'] ?? []);
        if ($animated) {
            $semantic = array_replace($semantic, $animated);
            $raw = array_replace($raw, $animated);
            $coverage += array_fill_keys(array_keys($animated), true);
        }
        $managed = $this->managedValues($record['computed'], $semantic, $raw, $coverage, $media);
        $values = $this->projectRecord($resolver, $element, $record, $styles, $origins, $lineOrigins, $role, true);
        $values = array_replace(array_diff_key($values, array_flip(self::PROPERTIES)), $managed, $animated);

        return ['computed' => $values, 'own' => array_intersect_key($values, $record['declarations'])];
    }

    /**
     * @param array{computed: array<string, string>, resolved: array<string, string>, cascaded: array<string, string>, specified: array<string, true>, declarations: array<string, array{value: string, source: string, source-value: string, expansion: list<string>, priority: array, order: array{int, int}, valid: bool, initial: bool, implicit: bool, explicit-candidate: array{0: string, ...}|null, ...}>, animated?: array<string, true>, ...} $record
     * @param \SplObjectStorage<\Dom\Element, array<string, string>>                                                                                                                                                                                                                                                                                                                                                                                    $styles
     * @param \SplObjectStorage<\Dom\Element, int>                                                                                                                                                                                                                                                                                                                                                                                                      $origins
     * @param \WeakMap<\Dom\Element, string>                                                                                                                                                                                                                                                                                                                                                                                                            $lineOrigins
     *
     * @return array<string, string>
     */
    private function projectRecord(StyleResolver $resolver, \Dom\Element $element, array $record, \SplObjectStorage $styles, \SplObjectStorage $origins, \WeakMap $lineOrigins, ?string $role = null, bool $variant = false): array
    {
        $values = NativeStyleProjection::project($record);
        $origin = $origins[$element] ?? 0;
        if (!$variant && !$origin) {
            $values = array_diff_key($values, self::ORIGIN_PROPERTIES);
        } else {
            foreach (self::ORIGIN_PROPERTIES as $property => $bit) {
                $declared = (bool) ($origin & $bit);
                $lineOrigin = 'line-height' === $property ? ($lineOrigins[$element] ?? null) : null;
                if ($variant) {
                    $own = $record['declarations'][$property] ?? null;
                    if ($own['valid'] ?? false) {
                        $declared = true;
                        $lineOrigin = 'line-height' === $property && is_numeric($own['value']) ? $own['value'] : null;
                    } elseif ('display' === $property && null !== $role) {
                        $declared = false;
                    }
                }
                if (!$declared) {
                    unset($values[$property]);
                } elseif (null !== $lineOrigin) {
                    $values[$property] = $lineOrigin;
                }
            }
        }
        foreach ($record['resolved'] as $property => $value) {
            if (isset($values[$property]) && ('color' === $property || str_ends_with($property, '-color')) && !(($record['declarations'][$property]['implicit'] ?? false) && null !== ($record['declarations'][$property]['explicit-candidate'] ?? null))) {
                $values[$property] = $value;
            }
        }
        if (isset($values['font-size'], $record['resolved']['font-size'])) {
            $values['font-size'] = $record['resolved']['font-size'];
        }

        return $values;
    }
}
