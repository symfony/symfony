<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

use Symfony\Component\HtmlRenderer\Animation\Animation;
use Symfony\Component\HtmlRenderer\Animation\ScrollTimeline;
use Symfony\Component\HtmlRenderer\Animation\SizeInterpolation;
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Form\ConstraintValidator;
use Symfony\Component\HtmlRenderer\Form\ControlState;
use Symfony\Component\HtmlRenderer\Html\ScrollControls;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;
use Symfony\Component\HtmlRenderer\Resource\ResourceCollection;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;
use Symfony\Component\HtmlRenderer\Vector\SvgStyle;

/**
 * Associates cascaded CSS values with native document elements.
 *
 * @internal
 */
final class StyleResolver
{
    private \SplObjectStorage $pseudoStyles;
    private \SplObjectStorage $pseudoDeclaredStyles;
    private \SplObjectStorage $cascadeRecords;
    private \WeakMap $userAgentWinners;
    private \WeakMap $explicitWinners;
    private array $expansionDefaults = [];
    private array $expansions = [];
    private array $expansionProperties = [];
    private array $resolvedColors = [];
    private array $initialColors = [];
    private CounterStyles $counterStyles;
    private ?string $stylesheetHash = null;
    private array $rules = [];
    private ?ParsedStylesheet $sourceStylesheet = null;
    private ?ParsedStylesheet $activeStylesheet = null;
    private ?PreparedCascade $preparedCascade = null;
    private array $functions = [];

    private const INHERITED = ['color', 'color-scheme', '-webkit-text-fill-color', 'font', 'font-family', 'font-size', 'font-style', 'font-weight', 'line-height', 'letter-spacing', 'word-spacing', 'text-align', 'text-align-last', 'text-justify', 'word-break', 'overflow-wrap', 'text-transform', 'white-space', 'white-space-collapse', 'text-wrap-mode', 'visibility', 'direction', 'writing-mode', 'list-style-type', 'list-style-position', 'list-style-image', 'quotes', 'ruby-position', 'ruby-align', 'scrollbar-color', 'image-orientation', 'image-rendering', 'text-indent', 'pointer-events', 'cursor', 'caret-color', 'caret-shape', 'caret-animation', 'accent-color', 'font-variant', 'font-variant-caps', 'font-variant-position', 'font-variant-numeric', 'line-break', 'word-wrap', 'hyphens', 'hyphenate-character', 'hyphenate-limit-chars', 'forced-color-adjust', 'text-autospace', 'interpolate-size', 'text-wrap', 'text-wrap-style', 'tab-size', 'empty-cells', 'orphans', 'widows', 'text-emphasis', 'text-emphasis-style', 'text-emphasis-color', 'text-emphasis-position', 'text-shadow', 'interactivity', ...SvgStyle::INHERITED];

    public function __construct(private readonly ResourceLoader|ResourceCollection $resources = new ResourceLoader(), private readonly FontStyle $defaultFont = new FontStyle(), private readonly TextMeasurerInterface $textMeasurer = new MonospaceTextMeasurer(), private readonly array $mediaFeatures = [], private readonly string $userAgentStylesheet = '', private readonly array $colorAliases = [], private readonly ?\Closure $colorLookup = null, private readonly ?\Closure $valueResolver = null, private readonly ?LayoutMetricsInterface $layoutMetrics = null)
    {
    }

    /**
     * @param list<array{\Dom\Element, array}> $states
     *
     * @return \SplObjectStorage<\Dom\Element, array<string, string>>
     */
    public function resolve(\Dom\Document $document, float $width, float $height, array $states = [], ?Timeline $timeline = null, float $time = 0, ?\SplObjectStorage $containerMap = null, ?ParsedStylesheet $parsedStylesheet = null, ?\SplObjectStorage $identities = null, array $treeDepths = [], ?\SplObjectStorage $styleParents = null, bool $prepare = false): \SplObjectStorage
    {
        $this->preparedCascade = null;
        if (null === $parsedStylesheet) {
            $sheets = [];
            $bytes = 0;
            foreach ($document->querySelectorAll('style, link') as $element) {
                if ('style' === $element->localName) {
                    $sheet = $element->textContent;
                } elseif (!$element->hasAttribute('disabled') && \in_array('stylesheet', preg_split('/\s+/', strtolower($element->getAttribute('rel') ?? '')), true)) {
                    $sheet = $this->resources->load($element->getAttribute('href') ?? '') ?? '';
                } else {
                    continue;
                }
                if ($bytes + \strlen($sheet) > 8 * 1024 * 1024) {
                    break;
                }
                if ($element->hasAttribute('media') && '' !== trim($element->getAttribute('media'))) {
                    $sheet = '@media '.$element->getAttribute('media').'{'.$sheet.'}';
                }
                $bytes += \strlen($sheet);
                $scope = null;
                if ('style' === $element->localName && false !== stripos($sheet, '@scope')) {
                    $scope = ':root';
                    $segments = [];
                    for ($ancestor = $element->parentElement; null !== $ancestor?->parentElement; $ancestor = $ancestor->parentElement) {
                        $position = 1;
                        for ($sibling = $ancestor->previousElementSibling; null !== $sibling; $sibling = $sibling->previousElementSibling) {
                            ++$position;
                        }
                        array_unshift($segments, ':nth-child('.$position.')');
                    }
                    if ($segments) {
                        $scope .= ' > '.implode(' > ', $segments);
                    }
                }
                $tree = $treeDepths ? (int) ($element->getAttribute(Selector::TREE_ATTRIBUTE) ?? 0) : null;
                $sheets[] = [$sheet, $scope, $tree, null === $tree ? 0 : ($treeDepths[$tree] ?? 0)];
            }
            $hash = hash('xxh128', $this->userAgentStylesheet."\0".serialize($sheets));
            if ($hash !== $this->stylesheetHash) {
                $loader = new class($this->resources) implements StylesheetLoaderInterface {
                    public function __construct(private readonly ResourceLoader|ResourceCollection $resources)
                    {
                    }

                    public function load(string $href): string
                    {
                        return $this->resources->load($href) ?? throw new InvalidArgumentException('The stylesheet could not be loaded.');
                    }
                };
                $parser = new CssParser($loader);
                $rules = array_map(static fn (CssRule $rule): CssRule => new CssRule($rule->selector, $rule->declarations, $rule->media, $rule->order, $rule->layer, true, $rule->containers, $rule->scoped, $rule->starting, $rule->context), $parser->parse('::scroll-marker-group{display:block}textarea{overflow:auto}iframe{display:inline-block;width:300px;height:150px;border:2px inset}[popover]{display:none}[popover]:popover-open,:modal{display:block;position:fixed;inset:0;margin:auto;width:fit-content;height:fit-content}:modal::backdrop{background-color:rgba(0,0,0,.3)}::placeholder{color:GrayText}select::picker(select){background-color:Canvas;color:CanvasText}option:picker-active{background-color:Highlight;color:HighlightText}'.$this->userAgentStylesheet));
                $registered = $parser->takeRegisteredProperties();
                $keyframes = $parser->takeKeyframes();
                $functions = $parser->takeFunctions();
                $counterStyles = $parser->takeCounterStyles();
                $positionTries = $parser->takePositionTries();
                $parser = new CssParser($loader);
                foreach ($sheets as [$sheet, $scope, $tree, $depth]) {
                    foreach ($parser->parse($sheet, [], $scope) as $rule) {
                        $selector = $rule->selector;
                        if (null !== $tree) {
                            try {
                                $selector = Selector::parse(Selector::inTree($selector->source, $tree));
                            } catch (InvalidArgumentException) {
                                continue;
                            }
                        }
                        $rules[] = new CssRule($selector, $rule->declarations, $rule->media, $rule->order, $rule->layer, $rule->userAgent, $rule->containers, $rule->scoped, $rule->starting, $depth);
                    }
                }
                $registered = array_replace($registered, $parser->takeRegisteredProperties());
                $keyframes = array_replace($keyframes, $parser->takeKeyframes());
                $functions = array_replace($functions, $parser->takeFunctions());
                $counterStyles = array_replace($counterStyles, $parser->takeCounterStyles());
                $positionTries = array_replace($positionTries, $parser->takePositionTries());
                $this->sourceStylesheet = new ParsedStylesheet($rules, $registered, $keyframes, $counterStyles, $functions, $positionTries);
                $this->stylesheetHash = $hash;
            }
            $parsedStylesheet = $this->sourceStylesheet;
        }
        if ($this->activeStylesheet !== $parsedStylesheet) {
            $this->rules = $parsedStylesheet->rules;
            $this->functions = $parsedStylesheet->functions;
            $this->counterStyles = new CounterStyles();
            $this->counterStyles->add($parsedStylesheet->counterStyles);
            $this->activeStylesheet = $parsedStylesheet;
        }
        $rules = $parsedStylesheet->rules;
        $registered = $parsedStylesheet->registered;
        $keyframes = $parsedStylesheet->keyframes;
        $this->userAgentWinners = new \WeakMap();
        $this->explicitWinners = new \WeakMap();
        $this->expansionDefaults = [];
        $this->expansions = [];
        $this->expansionProperties = [];
        $this->resolvedColors = [];
        $this->initialColors = [];
        $winners = new \WeakMap();
        $pseudoWinners = [];
        $matchedRules = new \WeakMap();
        $startingRules = [];
        $startingElements = new \WeakMap();
        $mirror = clone $document;
        $mixedNamespaces = $document instanceof \Dom\HTMLDocument && $document->getElementsByTagNameNS('http://www.w3.org/2000/svg', '*')->length > 0;
        $originals = iterator_to_array($document->querySelectorAll('*'));
        $mirrors = iterator_to_array($mirror->querySelectorAll('*'));
        $owners = new \WeakMap();
        $elementStates = new \WeakMap();
        foreach ($states as [$element, $state]) {
            $elementStates[$element] = $state;
        }
        foreach ($states as [$element, $state]) {
            foreach (['focus' => 'focus-within', 'hover' => 'hover'] as $source => $propagated) {
                if (!\in_array($source, $state, true)) {
                    continue;
                }
                for ($ancestor = $element; null !== $ancestor; $ancestor = $ancestor->parentElement) {
                    $ancestorState = $elementStates[$ancestor] ?? [];
                    $ancestorState[] = $propagated;
                    $elementStates[$ancestor] = $ancestorState;
                }
            }
        }
        $visuals = new \SplObjectStorage();
        if (null !== $identities) {
            foreach ($originals as $element) {
                $visuals[$identities[$element] ?? $element] = $element;
            }
        }
        $nativeRadios = ControlState::radioValidity(array_map(static fn (\Dom\Element $element): \Dom\Element => $identities[$element] ?? $element, iterator_to_array($document->querySelectorAll('input'))), static fn (\Dom\Element $element): bool => ($elementStates[$visuals[$element] ?? $element] ?? [])['checked'] ?? $element->hasAttribute('checked'));
        $radioValidity = new \SplObjectStorage();
        foreach ($nativeRadios as $element) {
            $radioValidity[$visuals[$element] ?? $element] = $nativeRadios[$element];
        }
        $invalidContainers = new \WeakMap();
        $languages = new \WeakMap();
        $hasScrollControls = $this->hasScrollControls();
        foreach ($mirrors as $index => $element) {
            $original = $originals[$index];
            $owners[$element] = $original;
            if ($mixedNamespaces) {
                $element->removeAttribute(Selector::HTML_ATTRIBUTE);
                if ('http://www.w3.org/1999/xhtml' === $element->namespaceURI) {
                    $element->setAttribute(Selector::HTML_ATTRIBUTE, '');
                }
            }
            $attributes = [];
            foreach ($original->attributes as $attribute) {
                $attributes[$attribute->name] = $attribute->value;
            }
            [$parentLanguage, $parentDirection] = null !== $original->parentElement ? ($languages[$original->parentElement] ?? ['', $this->defaultFont->direction]) : ['', $this->defaultFont->direction];
            [$language, $direction] = \Symfony\Component\HtmlRenderer\Html\LanguageDirection::resolve($attributes, $parentLanguage, $parentDirection, 'auto' === strtolower(trim($attributes['dir'] ?? '')) ? (($elementStates[$original] ?? [])['value'] ?? (\in_array($original->localName, ['input', 'textarea'], true) ? \Symfony\Component\HtmlRenderer\Form\ControlValue::initial($original) : $original->textContent)) : null);
            $languages[$original] = [$language, $direction];
            $element->removeAttribute(Selector::LANG_ATTRIBUTE);
            $element->removeAttribute(Selector::DIR_ATTRIBUTE);
            foreach (\Symfony\Component\HtmlRenderer\Html\LanguageDirection::attributes($language, $direction) as $name => $value) {
                $element->setAttribute($name, $value);
            }

            if (isset(($elementStates[$original] ?? [])['class-list'])) {
                $classes = ($elementStates[$original] ?? [])['class-list'];
                if (!$classes && !$original->hasAttribute('class')) {
                    $element->removeAttribute('class');
                } else {
                    $element->setAttribute('class', implode(' ', $classes));
                }
            }
            $state = array_filter($elementStates[$original] ?? [], static fn ($key) => \is_int($key), \ARRAY_FILTER_USE_KEY);
            if (($elementStates[$original] ?? [])['picker-open'] ?? false) {
                $state[] = 'open';
            }
            $disabled = ControlState::isDisabled($original);
            if ('meter' === $original->localName) {
                $state[] = \Symfony\Component\HtmlRenderer\Form\NumericControl::meter($original)['region'];
            } elseif ('progress' === $original->localName && \Symfony\Component\HtmlRenderer\Form\NumericControl::progress($original)['indeterminate']) {
                $state[] = 'indeterminate';
            }

            $state[] = $disabled ? 'disabled' : 'enabled';
            if (null !== $containerMap && isset($containerMap[$original]) && $hasScrollControls) {
                $container = $containerMap[$original];
                $containerStyle = $container['style'] ?? null;
                if (null === $containerStyle || \in_array($containerStyle['overflow-x'] ?? $containerStyle['overflow'] ?? 'visible', ['auto', 'scroll'], true) || \in_array($containerStyle['overflow-y'] ?? $containerStyle['overflow'] ?? 'visible', ['auto', 'scroll'], true)) {
                    foreach (['up' => 'top', 'down' => 'bottom', 'left' => 'left', 'right' => 'right'] as $direction => $side) {
                        if (!\in_array($side, $container['scroll']['scrollable'] ?? [], true)) {
                            $state[] = 'scroll-button-'.$direction.'-disabled';
                        }
                    }
                }
            }
            if (null !== $containerMap && (($containerMap[$original] ?? [])['scroll']['target-current'] ?? false)) {
                $state[] = 'target-current';
            }
            $control = \in_array($element->localName, ['input', 'textarea', 'select'], true);
            $type = strtolower($element->getAttribute('type') ?? 'text');
            $textControl = 'textarea' === $element->localName || 'input' === $element->localName && \in_array($type, ['text', 'search', 'tel', 'url', 'email', 'password', 'number', 'date', 'time', 'datetime-local', 'month', 'week'], true);
            $readOnly = $textControl && $element->hasAttribute('readonly');
            $state[] = $textControl && !$disabled && !$readOnly ? 'read-write' : 'read-only';
            if ($control) {
                $state[] = $element->hasAttribute('required') ? 'required' : 'optional';
                $value = ($elementStates[$original] ?? [])['value'] ?? \Symfony\Component\HtmlRenderer\Form\ControlValue::initial($original);
                if ($element->hasAttribute('placeholder') && '' === $value && \Symfony\Component\HtmlRenderer\Form\ControlValue::supportsPlaceholder($element)) {
                    $state[] = 'placeholder-shown';
                }
                if (!$disabled && !$readOnly && !('input' === $element->localName && \in_array($type, ['hidden', 'button', 'submit', 'reset', 'image'], true))) {
                    [$valid, $inRange] = ConstraintValidator::control($original, $value, ($elementStates[$original] ?? [])['checked'] ?? $original->hasAttribute('checked'), ($elementStates[$original] ?? [])['selected-values'] ?? null, $radioValidity[$original] ?? null);
                    $state[] = $valid ? 'valid' : 'invalid';
                    if (($elementStates[$original] ?? [])['user-interacted'] ?? false) {
                        $state[] = $valid ? 'user-valid' : 'user-invalid';
                    }
                    if (!$valid) {
                        if (null !== $nativeOwner = ControlState::formOwner($identities[$original] ?? $original)) {
                            if (null !== $owner = $visuals[$nativeOwner] ?? (null === $identities ? $nativeOwner : null)) {
                                $invalidContainers[$owner] = true;
                            }
                        }
                        for ($ancestor = ($identities[$original] ?? $original)->parentElement; null !== $ancestor; $ancestor = $ancestor->parentElement) {
                            if ('fieldset' === $ancestor->localName && null !== $visual = $visuals[$ancestor] ?? (null === $identities ? $ancestor : null)) {
                                $invalidContainers[$visual] = true;
                            }
                        }
                    }
                    if (null !== $inRange) {
                        $state[] = $inRange ? 'in-range' : 'out-of-range';
                    }
                }
            }
            if (($elementStates[$original] ?? [])['checked'] ?? ($element->hasAttribute('checked') || $element->hasAttribute('selected'))) {
                $state[] = 'checked';
            }
            $element->setAttribute(Selector::STATE_ATTRIBUTE, implode(' ', $state));
        }
        foreach ($mirrors as $index => $element) {
            if (\in_array($element->localName, ['form', 'fieldset'], true)) {
                $state = $element->getAttribute(Selector::STATE_ATTRIBUTE) ?? '';
                $element->setAttribute(Selector::STATE_ATTRIBUTE, $state.' '.(isset($invalidContainers[$originals[$index]]) ? 'invalid' : 'valid'));
            }
        }
        foreach ($document->querySelectorAll('meta[name]') as $meta) {
            if ('color-scheme' === strtolower($meta->getAttribute('name')) && ColorScheme::valid($content = $meta->getAttribute('content') ?? '')) {
                $this->apply($winners, $document->documentElement, new Declaration('color-scheme', trim($content)), 0, -2);
                break;
            }
        }
        foreach ($originals as $element) {
            $attributes = [];
            foreach ($element->attributes as $attribute) {
                $attributes[$attribute->name] = $attribute->value;
            }
            foreach (SvgStyle::presentation($element) as $index => $declaration) {
                $this->apply($winners, $element, $declaration, 0, -1, CssRule::UNLAYERED, null, false, $index);
            }
            $hints = \Symfony\Component\HtmlRenderer\Html\PresentationalHints::declarations($element->localName, $attributes).';'.(($elementStates[$element] ?? [])['presentation-style'] ?? '');
            foreach (DeclarationParser::parse($hints) as $index => $declaration) {
                $this->apply($winners, $element, $declaration, 0, -1, CssRule::UNLAYERED, null, false, $index);
            }
        }
        $requirements = new SelectorRequirements();
        $documentNames = SelectorRequirements::names($mirror);
        foreach ($rules as $order => $rule) {
            if (!$this->matchesMedia($rule->media, $width, $height)) {
                continue;
            }
            $documentSelector = $rule->selector->forDocument();
            $roles = 'scroll-button(*)' === $documentSelector->pseudoElement ? ['scroll-button(up)', 'scroll-button(down)', 'scroll-button(left)', 'scroll-button(right)'] : [$documentSelector->pseudoElement];
            foreach ($roles as $role) {
                $selector = 'scroll-button(*)' === $documentSelector->pseudoElement ? $documentSelector->forScrollButton(substr($role, 14, -1)) : $documentSelector;
                $nativeSelector = !$document instanceof \Dom\HTMLDocument ? $selector->forSvg() : ($mixedNamespaces ? $selector->forMixedDocument() : $selector->forHtml());
                if (!$requirements->mayMatch($nativeSelector, $documentNames)) {
                    continue;
                }
                try {
                    $elements = iterator_to_array(Selector::queryAll($mirror, $nativeSelector), false);
                } catch (\DOMException) {
                    continue;
                }
                if ($rule->containers) {
                    $elements = array_filter($elements, fn (\Dom\Element $element): bool => $this->matchesContainers($owners[$element], $rule->containers, $containerMap, $width, $height));
                }
                if (!$elements) {
                    continue;
                }
                if ($rule->starting) {
                    if (null === $selector->pseudoElement) {
                        $startingRules[$order] = [$rule, $selector, $elements];
                        foreach ($elements as $element) {
                            $owner = $owners[$element];
                            $startingElements[$owner] ??= [];
                            $startingElements[$owner][$order] = $order;
                        }
                    }
                    continue;
                }
                foreach ($elements as $element) {
                    $owner = $owners[$element];
                    $matchedRules[$owner] ??= [];
                    $matchedRules[$owner][$order] = $order;
                }
                $target = null === $role ? $winners : ($pseudoWinners[$role] ??= new \WeakMap());
                foreach ($rule->declarations as $declarationIndex => $declaration) {
                    foreach ($this->expand($declaration) as $expanded) {
                        foreach ($elements as $element) {
                            $this->apply($target, $owners[$element], $expanded, $selector->specificity, $rule->order, $rule->layer, $declaration->property, $rule->userAgent, $declarationIndex, $rule->scoped, $rule->context, $declaration->value);
                        }
                    }
                }
            }
        }
        foreach ($states as [$element, $state]) {
            foreach ($state['user-agent-styles'] ?? [] as $role => $declarations) {
                $target = '' === $role ? $winners : ($pseudoWinners[$role] ??= new \WeakMap());
                $index = 0;
                foreach ($declarations as $property => $value) {
                    $this->apply($target, $element, new Declaration($property, $value), \PHP_INT_MAX - 1, \PHP_INT_MAX, CssRule::UNLAYERED, null, true, $index++);
                }
            }
        }
        $startingWinners = null;
        if ($startingRules) {
            $startingWinners = clone $winners;
            foreach ([$this->userAgentWinners, $this->explicitWinners] as $metadata) {
                if (isset($metadata[$winners])) {
                    $metadata[$startingWinners] = clone $metadata[$winners];
                }
            }
            foreach ($startingRules as [$rule, $selector, $elements]) {
                foreach ($rule->declarations as $declarationIndex => $declaration) {
                    foreach ($this->expand($declaration) as $expanded) {
                        foreach ($elements as $element) {
                            $this->apply($startingWinners, $owners[$element], $expanded, $selector->specificity, $rule->order, $rule->layer, $declaration->property, $rule->userAgent, $declarationIndex, $rule->scoped, $rule->context, $declaration->value);
                        }
                    }
                }
            }
        }
        $normalWinners = $winners;
        $styleElements = $originals;
        if (null !== $styleParents) {
            foreach ($styleParents as $element) {
                $parent = $styleParents[$element];
                if ($element->ownerDocument !== $document || $element->isConnected || !$parent instanceof \Dom\Element || $parent->ownerDocument !== $document) {
                    throw new InvalidArgumentException('Detached style targets and their parents must belong to the rendered document.');
                }
                $styleElements[] = $element;
            }
        }
        foreach ($styleElements as $element) {
            foreach (($elementStates[$element]['inline-declarations'] ?? DeclarationParser::parse($element->getAttribute('style') ?? '')) as $declarationIndex => $declaration) {
                $this->apply($normalWinners, $element, $declaration, \PHP_INT_MAX, \PHP_INT_MAX, CssRule::UNLAYERED, null, false, $declarationIndex);
                if (isset($startingElements[$element])) {
                    $this->apply($startingWinners, $element, $declaration, \PHP_INT_MAX, \PHP_INT_MAX, CssRule::UNLAYERED, null, false, $declarationIndex);
                }
            }
        }
        $stage = new PreparedCascade($document, $width, $height, $parsedStylesheet, $styleElements, $elementStates, $normalWinners, $startingWinners, $startingElements, $matchedRules, $pseudoWinners, $this->userAgentWinners, $this->explicitWinners, $identities, $styleParents);
        $canPrepare = $prepare && null === $containerMap && !$this->hasContainerQueries() && !$this->hasScrollControls();
        $result = $this->compute($stage, $timeline, $time, $canPrepare, $containerMap);
        if ($canPrepare && !($timeline?->hasScrollAnimations() ?? false) && !($timeline?->hasLayoutTransitions() ?? false)) {
            $this->preparedCascade = $stage;
        }

        return $result;
    }

    /** Reuses matching only when the embedding runtime has verified an unchanged document and context. */
    public function resolvePrepared(Timeline $timeline, float $time): \SplObjectStorage
    {
        if (null === $this->preparedCascade) {
            throw new InvalidArgumentException('No unchanged cascade has been prepared.');
        }
        $stage = $this->preparedCascade;
        $this->releaseComputedStyles();

        return $this->compute($stage, $timeline, $time, true);
    }

    private function compute(PreparedCascade $stage, ?Timeline $timeline, float $time, bool $cloneWinners, ?\SplObjectStorage $containers = null): \SplObjectStorage
    {
        $document = $stage->document;
        $width = $stage->width;
        $height = $stage->height;
        $registered = $stage->stylesheet->registered;
        $keyframes = $stage->stylesheet->keyframes;
        $hasCustomKeyframes = null !== $timeline && array_any($keyframes, static fn (array $frames): bool => array_any($frames, static fn (array $frame): bool => array_any(array_keys($frame[1]), static fn (string $property): bool => str_starts_with($property, '--'))));
        $styleElements = $stage->elements;
        $elementStates = $stage->states;
        $startingElements = $stage->startingElements;
        $matchedRules = $stage->matchedRules;
        $pseudoWinners = $stage->pseudoWinners;
        $identities = $stage->identities;
        $styleParents = $stage->styleParents;
        $normalWinners = $cloneWinners ? clone $stage->winners : $stage->winners;
        $startingWinners = $cloneWinners && null !== $stage->startingWinners ? clone $stage->startingWinners : $stage->startingWinners;
        $this->userAgentWinners = $cloneWinners ? clone $stage->userAgentWinners : $stage->userAgentWinners;
        $this->explicitWinners = $cloneWinners ? clone $stage->explicitWinners : $stage->explicitWinners;
        if ($cloneWinners) {
            foreach ([[$stage->winners, $normalWinners], [$stage->startingWinners, $startingWinners]] as [$source, $target]) {
                if (null === $source) {
                    continue;
                }
                foreach ([$this->userAgentWinners, $this->explicitWinners] as $metadata) {
                    if (isset($metadata[$source])) {
                        $metadata[$target] = $metadata[$source];
                    }
                }
            }
        }
        $this->pseudoStyles = new \SplObjectStorage();
        $this->pseudoDeclaredStyles = new \SplObjectStorage();
        $this->cascadeRecords = new \SplObjectStorage();
        $result = new \SplObjectStorage();
        $lineHeightMultipliers = new \SplObjectStorage();
        $renderedElements = new \SplObjectStorage();
        $siblingPositions = new \SplObjectStorage();
        foreach ($styleElements as $element) {
            $startingRecord = null;
            $isStarting = isset($startingElements[$element]);
            $winners = $isStarting ? $startingWinners : $normalWinners;
            while (true) {
                $parent = $styleParents[$element] ?? $element->parentElement;
                if (isset($styleParents[$element]) && !isset($result[$parent])) {
                    throw new InvalidArgumentException('A detached style target requires its parent to be computed first.');
                }
                $inherited = null !== $parent ? ($result[$parent] ?? []) : [];
                $inheritedOverrides = [];
                if (null !== $parent && 'details' === $parent->localName && $element !== \Symfony\Component\HtmlRenderer\Html\Details::summary($parent)) {
                    $inheritedOverrides = self::inheritedValues($this->getPseudoDeclaredStyle($parent, 'details-content'));
                    $inherited = array_replace($inherited, $inheritedOverrides);
                }
                $values = self::inheritedValues($inherited, $registered);
                $inheritedProperties = null === $timeline ? [] : array_fill_keys(array_keys(self::inheritedValues($inherited)), true);
                if (null !== $timeline) {
                    foreach ($registered as $property => [$inherits]) {
                        if (!$inherits) {
                            unset($inheritedProperties[$property]);
                        }
                    }
                }
                $scheme = 'dark' === ($this->mediaFeatures['prefers-color-scheme'] ?? 'light') ? 'dark' : 'light';
                $values['color'] ??= 'CanvasText';
                $values['direction'] ??= $this->defaultFont->direction;
                [, $values['direction']] = \Symfony\Component\HtmlRenderer\Html\LanguageDirection::resolve(['dir' => $element->getAttribute('dir') ?? ''], '', $values['direction'], 'auto' === strtolower(trim($element->getAttribute('dir') ?? '')) ? (($elementStates[$element] ?? [])['value'] ?? (\in_array($element->localName, ['input', 'textarea'], true) ? \Symfony\Component\HtmlRenderer\Form\ControlValue::initial($element) : $element->textContent)) : null);
                $tag = strtolower($element->localName);
                if ('rt' === $tag) {
                    $values['font-size'] = '50%';
                }
                $values['list-style-type'] ??= 'disc';
                $values['list-style-position'] ??= 'outside';
                if ('ol' === $tag) {
                    $values['list-style-type'] = 'decimal';
                } elseif (\in_array($tag, ['ul', 'menu'], true)) {
                    $level = 0;
                    for ($ancestor = $element->parentElement; null !== $ancestor; $ancestor = $ancestor->parentElement) {
                        if (\in_array($ancestor->localName, ['ol', 'ul', 'menu'], true)) {
                            ++$level;
                        }
                    }
                    $values['list-style-type'] = 0 === $level ? 'disc' : (1 === $level ? 'circle' : 'square');
                } elseif ('summary' === $tag) {
                    $values['list-style-type'] = 'details' === $element->parentElement?->localName && $element->parentElement->hasAttribute('open') ? 'disclosure-open' : 'disclosure-closed';
                }
                $values['display'] = match (true) {
                    'option' === $tag && null !== ($select = $element->closest('select')) && ($select->hasAttribute('multiple') || (int) ($select->getAttribute('size') ?? '0') > 1 || (($elementStates[$select] ?? [])['picker-open'] ?? false)) && !$element->hasAttribute('hidden') => 'block',
                    $element->hasAttribute('hidden'), \in_array($tag, ['head', 'style', 'script', 'link', 'meta', 'title', 'template', 'datalist', 'option', 'optgroup', 'rp'], true) => 'none',
                    \in_array($tag, ['html', 'body', 'div', 'p', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'form', 'fieldset', 'pre', 'blockquote', 'details', 'figure', 'figcaption', 'hr', 'address'], true) => 'block',
                    'dialog' === $tag => $element->hasAttribute('open') ? 'block' : 'none',
                    'li' === $tag => 'list-item',
                    'summary' === $tag => 'details' === $element->parentElement?->localName && $element === \Symfony\Component\HtmlRenderer\Html\Details::summary($element->parentElement) ? 'list-item' : 'block',
                    'table' === $tag => 'table',
                    'caption' === $tag => 'table-caption',
                    'col' === $tag => 'table-column',
                    'colgroup' === $tag => 'table-column-group',
                    'ruby' === $tag => 'ruby',
                    'rb' === $tag => 'ruby-base',
                    'rt' === $tag => 'ruby-text',
                    'rtc' === $tag => 'ruby-text-container',
                    'tr' === $tag => 'table-row',
                    'thead' === $tag => 'table-header-group',
                    'tbody' === $tag => 'table-row-group',
                    'tfoot' === $tag => 'table-footer-group',
                    \in_array($tag, ['td', 'th'], true) => 'table-cell',
                    \in_array($tag, ['input', 'textarea', 'button', 'select', 'progress', 'meter', 'img', 'svg', 'canvas', 'video'], true) => 'inline-block',
                    default => 'inline',
                };
                $own = $winners[$element] ?? [];
                if (isset($own['all']) && \in_array($keyword = strtolower(trim($own['all'][0])), ['initial', 'inherit', 'unset', 'revert', 'revert-layer'], true)) {
                    $reset = $own['all'];
                    $userAgent = array_map(static fn (array $winner) => $winner[0], $this->userAgentWinners[$winners][$element] ?? []);
                    foreach (CssWideKeywords::affected(array_unique([...array_keys($own), ...self::INHERITED, 'display', 'appearance'])) as $property) {
                        if (!isset($own[$property]) || $reset[1] >= $own[$property][1]) {
                            $own[$property] = [CssWideKeywords::resolve($keyword, $property, !$reset[1][0] ? (bool) $reset[1][1] : !(bool) $reset[1][1], $userAgent, self::INHERITED), $reset[1], $property];
                        }
                    }
                    unset($own['all']);
                    $winners[$element] = $own;
                }
                $cascaded = array_map(static fn (array $winner) => $winner[0], $winners[$element] ?? []);
                foreach ($inheritedOverrides as $property => $value) {
                    if (!isset($cascaded[$property]) || \in_array($cascaded[$property], ['inherit', 'unset'], true)) {
                        $cascaded[$property] = $value;
                    }
                }
                $animationCascaded = $cascaded;
                foreach ($winners[$element] ?? [] as $property => [$value, $rank]) {
                    if (\in_array($value, ['revert', 'revert-layer'], true)) {
                        $userAgent = array_map(static fn (array $winner) => $winner[0], $this->userAgentWinners[$winners][$element] ?? []);
                        $value = CssWideKeywords::resolve($value, $property, !$rank[0] ? (bool) $rank[1] : !(bool) $rank[1], $userAgent, self::INHERITED);
                    }
                    if (null !== $timeline) {
                        $animationCascaded[$property] = $value;
                        unset($inheritedProperties[$property]);
                    }
                    if ('inherit' === $value || 'unset' === $value && (str_starts_with($property, '--') || \in_array($property, self::INHERITED, true))) {
                        if (isset($inherited[$property])) {
                            $values[$property] = $inherited[$property];
                            if (null !== $timeline) {
                                $inheritedProperties[$property] = true;
                            }
                        } elseif ('http://www.w3.org/2000/svg' === $element->namespaceURI && null !== ($initial = SvgStyle::initial($property))) {
                            $values[$property] = $initial;
                        } else {
                            unset($values[$property]);
                        }
                    } elseif ('initial' === $value || 'unset' === $value) {
                        if (\in_array($property, ['font-style', 'letter-spacing', 'word-spacing'], true)) {
                            $values[$property] = 'normal';
                        } elseif ('http://www.w3.org/2000/svg' === $element->namespaceURI && null !== ($initial = SvgStyle::initial($property))) {
                            $values[$property] = $initial;
                        } else {
                            unset($values[$property]);
                        }
                    } else {
                        $values[$property] = $value;
                    }
                }
                $variableInputs = null !== $timeline && ($hasCustomKeyframes && (isset($values['animation']) || isset($values['animation-name'])) || (isset($values['transition']) || isset($values['transition-property'])) && array_any(array_keys($values), static fn (string $property): bool => str_starts_with($property, '--'))) ? $values : null;
                [$values, $resolvedValues, $invalidRegistered] = $this->resolveRegisteredValues($values, $registered, $inherited);
                $attributes = [];
                foreach ($element->attributes as $attribute) {
                    $attributes[$attribute->name] = $attribute->value;
                }
                $resolvedValues = $this->resolveElementValues($resolvedValues, $width, $height, $element, $parent, $attributes, $siblingPositions);

                foreach ($values as $property => $value) {
                    $resolved = $resolvedValues[$property] ?? null;
                    $source = ($winners[$element] ?? [])[$property][2] ?? $property;
                    if (null !== $resolved && isset(($winners[$element] ?? [])[$property]) && ($resolvedValues[$source] ?? null) !== (($winners[$element] ?? [])[$property][3] ?? null)) {
                        $resolved = DeclarationExpander::resolvedValue($property, $source, $resolvedValues[$source] ?? $resolved, fn (string $value): bool => \is_array($this->parseColor($value)));
                    }
                    if (null === $resolved) {
                        if (isset($inherited[$property]) && \in_array($property, self::INHERITED, true)) {
                            $values[$property] = $inherited[$property];
                            if (null !== $timeline) {
                                $inheritedProperties[$property] = true;
                            }
                        } else {
                            unset($values[$property]);
                        }
                    } else {
                        $values[$property] = $resolved;
                    }
                }
                $logicalValues = null !== $variableInputs ? $values : null;
                $logicalWinners = null !== $variableInputs ? ($winners[$element] ?? []) : [];
                [$values, $physicalWinners] = self::physicalValues($values, $winners[$element] ?? []);
                $winners[$element] = $physicalWinners;
                if (isset($values['white-space-collapse']) || isset($values['text-wrap-mode'])) {
                    $used = $values;
                    unset($used['white-space']);
                    $values['white-space'] = WhiteSpace::resolve($used);
                }
                $rendered = 'none' !== ($values['display'] ?? 'inline') && (null === $parent || ($renderedElements[$parent] ?? true));
                if (null !== $parent && 'details' === $parent->localName && !$parent->hasAttribute('open') && $element !== \Symfony\Component\HtmlRenderer\Html\Details::summary($parent)) {
                    $rendered = false;
                }
                $renderedElements[$element] = $rendered;
                $scheme = ColorScheme::forced($values['color-scheme'] ?? '') ?? $scheme;
                foreach ($cascaded as $property => $value) {
                    if (preg_match('/(?:attr|sibling-index|sibling-count)\\(/i', $value)) {
                        if (isset($values[$property])) {
                            $cascaded[$property] = $values[$property];
                        } else {
                            unset($cascaded[$property]);
                        }
                    }
                }
                $resolvedSemantic = $values;
                $values = $this->colors($values, $inherited, $scheme);
                if ($isStarting) {
                    $startingAnimationValues = $values;
                }
                if (!$isStarting && null !== $timeline && (!$rendered || 'reduce' === ($this->mediaFeatures['prefers-reduced-motion'] ?? 'no-preference'))) {
                    $timeline->forget($identities[$element] ?? $element);
                } elseif (!$isStarting && null !== $timeline) {
                    $important = [];
                    foreach ($winners[$element] ?? [] as $property => [, $rank]) {
                        if ($rank[0]) {
                            $important[] = $property;
                        }
                    }
                    $values = $timeline->apply($identities[$element] ?? $element, $values, $this->keyframeValues($keyframes, $values, $scheme, $registered), $time, $important, $startingRecord['animation-values'] ?? [], null === $containers ? null : fn (Animation $definition): ?float => ScrollTimeline::fraction($element, $definition, $values, $result, $containers, $styleParents, fn (string $value, bool $vertical, float $reference, array $style): ?float => $this->mediaLength($value, $width, $height, $this->queryFont($style), $this->queryFont($result[$element->ownerDocument->documentElement] ?? []), $vertical, $reference), fn (float $viewport, bool $vertical): float => $this->layoutMetrics?->constrainLength($viewport, 'view-timeline-viewport', $vertical) ?? $viewport), null === $containers ? null : fn (string $property, string $from, string $to, array $style): array => SizeInterpolation::ends($from, $to, 'allow-keywords' === ($style['interpolate-size'] ?? ''), ($containers[$element] ?? [])['intrinsic-height'] ?? null, fn (string $value): ?float => $this->mediaLength($value, $width, $height, $this->queryFont(($containers[$element] ?? [])['style'] ?? $style), $this->queryFont($result[$element->ownerDocument->documentElement] ?? []), true, ($containers[$parent] ?? [])['height'] ?? $height)));
                    $dependent = [];
                    if (null !== $variableInputs) {
                        $applied = array_fill_keys($timeline->getAppliedProperties($identities[$element] ?? $element), true);
                        $custom = array_filter($applied, static fn (string $property): bool => str_starts_with($property, '--'), \ARRAY_FILTER_USE_KEY);
                        if ($custom) {
                            $resolvedValues = array_replace($resolvedValues, array_intersect_key($values, $custom));
                            $resolvedSemantic = array_replace($resolvedSemantic, array_intersect_key($values, $custom));
                            $inputs = array_replace($variableInputs, array_intersect_key($values, $custom));
                            [, $scope, $invalidVariables] = $this->resolveRegisteredValues($inputs, $registered, $inherited);
                            $invalidRegistered = array_replace($invalidRegistered, $invalidVariables);
                            $scope = $this->resolveElementValues($scope, $width, $height, $element, $parent, $attributes, $siblingPositions);
                            $dependent = CustomProperties::dependents($inputs, $scope, $custom);
                            foreach ($dependent as $property => $_) {
                                if (isset($applied[$property])) {
                                    continue;
                                }
                                try {
                                    if (!isset($scope[$property])) {
                                        throw new InvalidArgumentException('An animated custom property made this value invalid.');
                                    }
                                    $resolved = $this->resolveElementValue($scope[$property], $scope, $width, $height, $element, $parent, $attributes, $siblingPositions);
                                    $source = ($winners[$element] ?? [])[$property][2] ?? $property;
                                    $sourceValue = $scope[$source] ?? $resolved;
                                    $resolved = DeclarationExpander::resolvedValue($property, $source, $sourceValue, fn (string $value): bool => \is_array($this->parseColor($value)));
                                    if (null === $resolved) {
                                        throw new InvalidArgumentException('An animated custom property made this declaration invalid.');
                                    }
                                    $values[$property] = $resolvedValues[$property] = $resolvedSemantic[$property] = $resolved;
                                } catch (InvalidArgumentException) {
                                    unset($resolvedValues[$property], $resolvedSemantic[$property]);
                                    if (isset($inherited[$property]) && \in_array($property, self::INHERITED, true)) {
                                        $values[$property] = $inherited[$property];
                                        $inheritedProperties[$property] = true;
                                    } else {
                                        unset($values[$property]);
                                    }
                                }
                            }
                            if (null !== $logicalValues) {
                                $candidate = array_replace($logicalValues, array_intersect_key($values, $dependent + $applied));
                                [$candidate, $physicalWinners] = self::physicalValues($candidate, $logicalWinners);
                                foreach ($logicalWinners as $property => $_) {
                                    $old = LogicalProperties::physical($property, $logicalValues['writing-mode'] ?? 'horizontal-tb', $logicalValues['direction'] ?? 'ltr');
                                    $new = LogicalProperties::physical($property, $values['writing-mode'] ?? 'horizontal-tb', $values['direction'] ?? 'ltr');
                                    if ($old === $property && $new === $property) {
                                        continue;
                                    }
                                    foreach ([$property, $old, $new] as $physical) {
                                        if (!isset($applied[$physical])) {
                                            if (isset($candidate[$physical])) {
                                                $values[$physical] = $candidate[$physical];
                                            } else {
                                                unset($values[$physical]);
                                            }
                                        }
                                    }
                                }
                                $winners[$element] = $physicalWinners;
                            }
                        }
                    }
                    $appliedColors = array_fill_keys($timeline->getAppliedProperties($identities[$element] ?? $element), true);
                    if (isset($appliedColors['color']) || isset($dependent['color'])) {
                        foreach ($resolvedSemantic as $property => $value) {
                            if (!isset($appliedColors[$property]) && str_ends_with($property, '-color') && preg_match('/\\bcurrentcolor\\b/i', $value)) {
                                $values[$property] = $value;
                            }
                        }
                    }
                    $scheme = ColorScheme::forced($values['color-scheme'] ?? '') ?? $scheme;
                    $values = $this->colors($values, $inherited, $scheme);
                }
                $values['display'] = str_contains($values['display'] ?? '', 'list-item') ? $values['display'] : CssParser::display($values['display'] ?? 'inline');
                $parentSize = (float) ($inherited['font-size'] ?? $this->defaultFont->size);
                $rootSize = isset($result[$document->documentElement]) ? (float) ($result[$document->documentElement]['font-size'] ?? $this->defaultFont->size) : $this->defaultFont->size;
                $fontSize = self::fontLength($values['font-size'] ?? $this->defaultFont->size.'px', $parentSize, $rootSize, $width, $height) ?? $parentSize;
                $values['font-size'] = $fontSize.'px';
                $values['font-family'] ??= $this->defaultFont->family;
                $values['font-style'] = strtolower(trim($values['font-style'] ?? $this->defaultFont->style));
                $parentWeight = (float) ($inherited['font-weight'] ?? $this->defaultFont->weight);
                $weight = strtolower($values['font-weight'] ?? (string) $this->defaultFont->weight);
                $values['font-weight'] = (string) (\in_array($weight, ['bolder', 'lighter'], true) ? DeclarationExpander::relativeFontWeight($weight, $parentWeight) : DeclarationExpander::fontWeight($weight));
                $normalLineHeight = $this->defaultFont->size > 0 ? $this->defaultFont->lineHeight / $this->defaultFont->size : 1.2;
                $line = $values['line-height'] ?? 'normal';
                $declaredLine = ($winners[$element] ?? [])['line-height'][0] ?? null;
                $inheritsLine = \in_array($declaredLine, ['inherit', 'unset'], true) || null === $declaredLine && $line === ($inherited['line-height'] ?? 'normal');
                if ($inheritsLine && null !== $parent && isset($lineHeightMultipliers[$parent])) {
                    $line = $lineHeightMultipliers[$parent];
                }
                if ('normal' === $line || is_numeric($line)) {
                    $lineHeightMultipliers[$element] = $line;
                    $values['line-height'] = $fontSize * ('normal' === $line ? $normalLineHeight : max(0, (float) $line)).'px';
                } else {
                    $values['line-height'] = (self::fontLength($line, $fontSize, $rootSize, $width, $height) ?? $fontSize * $normalLineHeight).'px';
                }
                $values = $this->textSpacing($values, $inherited, $result[$document->documentElement] ?? $values, $width, $height);
                $values = $this->decorationThickness($values, $result[$document->documentElement] ?? [], $width, $height);
                $specified = array_fill_keys(array_keys($inheritedOverrides), true);
                foreach ($winners[$element] ?? [] as $property => [$declaration]) {
                    if (null !== $declaration && !\in_array($declaration, ['inherit', 'unset'], true) && (isset($resolvedValues[$property]) || 'initial' === $declaration)) {
                        $specified[$property] = true;
                    }
                }
                if ('match-parent' === strtolower(trim($values['text-align'] ?? ''))) {
                    $values['text-align'] = TextAlignment::parent($inherited);
                }
                $declarations = $this->declarationRecords($winners, $element, $resolvedValues, $invalidRegistered);
                $record = ['computed' => $values, 'resolved' => $resolvedSemantic, 'cascaded' => $cascaded, 'specified' => $specified, 'declarations' => $declarations, 'matched-rules' => array_values($matchedRules[$element] ?? [])];
                if (!$isStarting && null !== $timeline) {
                    $record['animated'] = $this->animatedProperties($timeline->getAppliedProperties($identities[$element] ?? $element), null !== $parent ? ($this->cascadeRecords[$parent]['animated'] ?? []) : [], $animationCascaded, $declarations, $inheritedProperties, $resolvedValues, isset($lineHeightMultipliers[$element]));
                    if ($timeline->hasScrollAnimation($identities[$element] ?? $element)) {
                        $record['scroll-driven'] = true;
                    }
                }
                if ($isStarting) {
                    $startingRecord = $record;
                    $startingRecord['animation-values'] = $startingAnimationValues;
                    $winners = $normalWinners;
                    $isStarting = false;
                    unset($lineHeightMultipliers[$element], $renderedElements[$element]);
                    continue;
                }
                if (null !== $startingRecord) {
                    $record['starting-style'] = $startingRecord;
                    $record['starting-rules'] = array_values($startingElements[$element]);
                }
                $this->cascadeRecords[$element] = $record;
                $result[$element] = $values;
                $pseudos = [];
                $pseudoRecords = [];
                foreach ($pseudoWinners as $name => $pseudoMap) {
                    if (!isset($pseudoMap[$element])) {
                        continue;
                    }
                    $pseudo = self::inheritedValues($values, $registered);
                    $pseudoInherited = array_fill_keys(array_keys($pseudo), true);
                    foreach ($registered as $property => [$inherits]) {
                        if (!$inherits) {
                            unset($pseudoInherited[$property]);
                        }
                    }
                    $pseudo['display'] = 'inline';
                    foreach ($pseudoMap[$element] as $property => [$value]) {
                        if (!\in_array($value, ['inherit', 'unset'], true)) {
                            unset($pseudoInherited[$property]);
                        }
                        if ('inherit' === $value) {
                            if (isset($values[$property])) {
                                $pseudo[$property] = $values[$property];
                            }
                        } elseif (\in_array($property, ['font-style', 'letter-spacing', 'word-spacing'], true) && 'initial' === $value) {
                            $pseudo[$property] = 'normal';
                        } elseif (!\in_array($value, ['initial', 'unset'], true)) {
                            $pseudo[$property] = $value;
                        }
                    }
                    $resolvedPseudo = CustomProperties::resolve($pseudo);
                    foreach ($pseudo as $property => $value) {
                        $resolved = $resolvedPseudo[$property] ?? null;
                        if (null !== $resolved) {
                            try {
                                $resolved = $this->resolveValue($resolved, $resolvedPseudo, $width, $height);
                            } catch (InvalidArgumentException) {
                                $resolved = null;
                            }
                        }
                        $source = $pseudoMap[$element][$property][2] ?? $property;
                        if (null !== $resolved && isset($pseudoMap[$element][$property]) && ($resolvedPseudo[$source] ?? null) !== ($pseudoMap[$element][$property][3] ?? null)) {
                            $resolved = DeclarationExpander::resolvedValue($property, $source, $resolvedPseudo[$source] ?? $resolved, fn (string $value): bool => \is_array($this->parseColor($value)));
                        }
                        if (null === $resolved) {
                            unset($pseudo[$property], $resolvedPseudo[$property]);
                        } else {
                            $resolvedPseudo[$property] = $resolved;
                            $pseudo[$property] = $resolved;
                        }
                    }
                    if (isset($pseudo['white-space-collapse']) || isset($pseudo['text-wrap-mode'])) {
                        $used = $pseudo;
                        unset($used['white-space']);
                        $pseudo['white-space'] = WhiteSpace::resolve($used);
                    }
                    $pseudoSemantic = $pseudo;
                    $pseudoScheme = ColorScheme::forced($pseudo['color-scheme'] ?? '') ?? ('dark' === ($this->mediaFeatures['prefers-color-scheme'] ?? 'light') ? 'dark' : 'light');
                    $pseudo = $this->colors($pseudo, $values, $pseudoScheme);
                    if (null !== $timeline) {
                        $identity = $identities[$element] ?? $element;
                        $generated = !\in_array($name, ['before', 'after', 'scroll-marker'], true) && !str_starts_with($name, 'scroll-button(') || !\in_array(strtolower(trim($pseudo['content'] ?? 'none')), ['none', 'normal'], true);
                        if ('target-text' === $name && !\in_array($name, ($elementStates[$element] ?? [])['active-pseudo-elements'] ?? [], true)) {
                            $generated = false;
                        }
                        if ('scroll-marker-group' === $name && !ScrollControls::hostsMarkerGroup($values)) {
                            $generated = false;
                        } elseif ('scroll-marker' === $name) {
                            $group = $styleParents[$element] ?? $element->parentElement;
                            $generated = $generated && null !== $group && isset($result[$group]) && ScrollControls::hostsMarkerGroup($result[$group]);
                        }
                        if (!$rendered || !$generated || 'reduce' === ($this->mediaFeatures['prefers-reduced-motion'] ?? 'no-preference')) {
                            $timeline->forget($identity, $name);
                        } else {
                            $important = [];
                            foreach ($pseudoMap[$element] as $property => [, $rank]) {
                                if ($rank[0]) {
                                    $important[] = $property;
                                }
                            }
                            $pseudo = $timeline->apply($identity, $pseudo, $this->keyframeValues($keyframes, $pseudo, $pseudoScheme, $registered), $time, $important, [], null === $containers ? null : static fn (Animation $definition): ?float => ScrollTimeline::fraction($element, $definition, $pseudo, $result, $containers, $styleParents), null, $name);
                            $applied = array_fill_keys($timeline->getAppliedProperties($identity, $name), true);
                            if (isset($applied['color'])) {
                                foreach ($pseudoSemantic as $property => $value) {
                                    if (!isset($applied[$property]) && str_ends_with($property, '-color') && preg_match('/\\bcurrentcolor\\b/i', $value)) {
                                        $pseudo[$property] = $value;
                                    }
                                }
                            }
                            $pseudo = $this->colors($pseudo, $values, $pseudoScheme);
                        }
                    }
                    $pseudoFontSize = self::fontLength($pseudo['font-size'] ?? $values['font-size'], $fontSize, $rootSize, $width, $height) ?? $fontSize;
                    $pseudo['font-size'] = $pseudoFontSize.'px';
                    $pseudoWeight = strtolower($pseudo['font-weight'] ?? $values['font-weight']);
                    $pseudo['font-weight'] = (string) (\in_array($pseudoWeight, ['bolder', 'lighter'], true) ? DeclarationExpander::relativeFontWeight($pseudoWeight, (float) $values['font-weight']) : DeclarationExpander::fontWeight($pseudoWeight));

                    $pseudoLine = $pseudo['line-height'] ?? 'normal';
                    $declaredPseudoLine = $pseudoMap[$element]['line-height'][0] ?? null;
                    if ((null === $declaredPseudoLine || \in_array($declaredPseudoLine, ['inherit', 'unset'], true)) && isset($lineHeightMultipliers[$element])) {
                        $pseudoLine = $lineHeightMultipliers[$element];
                    }
                    $pseudo['line-height'] = ('normal' === $pseudoLine || is_numeric($pseudoLine) ? $pseudoFontSize * ('normal' === $pseudoLine ? $normalLineHeight : max(0, (float) $pseudoLine)) : (self::fontLength($pseudoLine, $pseudoFontSize, $rootSize, $width, $height) ?? $pseudoFontSize * $normalLineHeight)).'px';
                    $pseudo = $this->textSpacing($pseudo, $values, $result[$document->documentElement] ?? $values, $width, $height);
                    $pseudo = $this->decorationThickness($pseudo, $result[$document->documentElement] ?? [], $width, $height);
                    $pseudo['display'] = CssParser::display($pseudo['display'] ?? 'inline');
                    $pseudos[$name] = $pseudo;
                    $pseudoRecords[$name] = [
                        'computed' => $pseudo,
                        'resolved' => $pseudoSemantic,
                        'cascaded' => array_map(static fn (array $winner): string => $winner[0], $pseudoMap[$element]),
                        'specified' => array_fill_keys(array_keys($pseudoMap[$element]), true),
                        'declarations' => $this->declarationRecords($pseudoMap, $element, $resolvedPseudo),
                    ];
                    if (null !== $timeline) {
                        $pseudoRecords[$name]['animated'] = $this->animatedProperties($timeline->getAppliedProperties($identities[$element] ?? $element, $name), $record['animated'] ?? [], $pseudoRecords[$name]['cascaded'], $pseudoRecords[$name]['declarations'], $pseudoInherited, $resolvedPseudo, 'normal' === $pseudoLine || is_numeric($pseudoLine));
                    }
                    if (str_starts_with($name, 'highlight(') || \in_array($name, ['first-letter', 'first-line', 'before', 'after', 'details-content'], true)) {
                        $properties = array_fill_keys(array_keys($pseudoMap[$element]), true);
                        if (isset($properties['font-size'])) {
                            $properties['line-height'] = true;
                        }
                        $declared = $this->pseudoDeclaredStyles[$element] ?? [];
                        $declared[$name] = array_intersect_key($pseudo, $properties);
                        $this->pseudoDeclaredStyles[$element] = $declared;
                    }
                }
                $timeline?->retainPseudoElements($identities[$element] ?? $element, array_keys($pseudos));
                if ($pseudos) {
                    $this->pseudoStyles[$element] = $pseudos;
                    $record = $this->cascadeRecords[$element];
                    $record['pseudos'] = $pseudoRecords;
                    $this->cascadeRecords[$element] = $record;
                }
                break;
            }
        }

        return $result;
    }

    public function getPseudoDeclaredStyle(\Dom\Element $element, string $role): array
    {
        return ($this->pseudoDeclaredStyles[$element] ?? [])[$role] ?? [];
    }

    public function getCounterStyles(): CounterStyles
    {
        return $this->counterStyles;
    }

    public function getPositionTries(): array
    {
        return ($this->activeStylesheet ?? $this->sourceStylesheet)->positionTries ?? [];
    }

    public function getPseudoStyle(\Dom\Element $element, string $name): array
    {
        return ($this->pseudoStyles[$element] ?? [])[$name] ?? [];
    }

    private function resolveRegisteredValues(array $values, array $registered, array $inherited): array
    {
        $resolvedValues = CustomProperties::resolve($values);
        $invalidRegistered = [];
        foreach ($registered as $property => [$inherits, $initial, $syntax]) {
            if (isset($values[$property]) && !PropertySyntax::matches($syntax, $resolvedValues[$property] ?? '', fn (string $color): bool => \is_array($this->parseColor($color)))) {
                $invalidRegistered[$property] = true;
                $fallback = $inherits ? ($inherited[$property] ?? $initial) : $initial;
                if (null === $fallback) {
                    unset($values[$property]);
                } else {
                    $values[$property] = $fallback;
                }
            }
        }
        if ($invalidRegistered) {
            $resolvedValues = CustomProperties::resolve($values);
        }

        return [$values, $resolvedValues, $invalidRegistered];
    }

    private static function physicalValues(array $values, array $winners): array
    {
        $physicalWinners = $winners;
        foreach ($winners as $property => $winner) {
            $physical = LogicalProperties::physical($property, $values['writing-mode'] ?? 'horizontal-tb', $values['direction'] ?? 'ltr');
            if ($physical !== $property && (!isset($physicalWinners[$physical]) || $winner[1] >= $physicalWinners[$physical][1])) {
                if (isset($values[$property])) {
                    $values[$physical] = $values[$property];
                } else {
                    unset($values[$physical]);
                }
                $physicalWinners[$physical] = $winner;
            }
        }

        return [$values, $physicalWinners];
    }

    private function resolveElementValues(array $scope, float $width, float $height, \Dom\Element $element, ?\Dom\Element $parent, array $attributes, \SplObjectStorage $siblingPositions): array
    {
        foreach ($scope as $property => $resolved) {
            if ('content' === $property) {
                continue;
            }
            try {
                $scope[$property] = $this->resolveElementValue($resolved, $scope, $width, $height, $element, $parent, $attributes, $siblingPositions);
            } catch (InvalidArgumentException) {
                unset($scope[$property]);
            }
        }

        return $scope;
    }

    private function resolveElementValue(string $resolved, array $scope, float $width, float $height, \Dom\Element $element, ?\Dom\Element $parent, array $attributes, \SplObjectStorage $siblingPositions): string
    {
        $resolved = $this->resolveValue($resolved, $scope, $width, $height);
        if (false !== stripos($resolved, 'sibling-')) {
            if (!isset($siblingPositions[$element])) {
                $siblings = [];
                for ($sibling = $parent->firstElementChild ?? $element; null !== $sibling; $sibling = $sibling->nextElementSibling) {
                    $siblings[] = $sibling;
                }
                foreach ($siblings as $position => $sibling) {
                    $siblingPositions[$sibling] = [$position + 1, \count($siblings)];
                }
            }
            [$index, $count] = $siblingPositions[$element];
            $resolved = ElementFunctions::siblings($resolved, $index, $count);
        }
        if (false !== stripos($resolved, 'attr(')) {
            $resolved = ElementFunctions::attributes($resolved, $attributes);
        }

        return $resolved;
    }

    private function keyframeValues(array $keyframes, array $values, string $scheme, array $registered): array
    {
        foreach (Animation::listFromDeclarations($values) as $animation) {
            if (!isset($keyframes[$animation->name])) {
                continue;
            }
            foreach ($keyframes[$animation->name] as $index => [$offset, $declarations]) {
                $frame = [];
                foreach ($declarations as $property => $value) {
                    try {
                        $frame[$property] = CustomProperties::substitute($value, $values);
                        if (isset($registered[$property])) {
                            $syntax = $registered[$property][2];
                            if (!PropertySyntax::matches($syntax, $frame[$property], fn (string $color): bool => \is_array($this->parseColor($color, $scheme)))) {
                                unset($frame[$property]);
                                continue;
                            }
                            if ('<color>' === trim($syntax)) {
                                $frame[$property] = $this->colors(['color' => $frame[$property]], $values, $scheme)['color'];
                            }
                        }
                    } catch (InvalidArgumentException) {
                        continue;
                    }
                }
                $resolved = $this->colors(array_replace(['color' => $values['color']], $frame), $values, $scheme);
                $keyframes[$animation->name][$index] = [$offset, array_intersect_key($resolved, $declarations)];
            }
        }

        return $keyframes;
    }

    private function decorationThickness(array $values, array $root, float $width, float $height): array
    {
        $value = $values['text-decoration-thickness'] ?? 'auto';
        if (!\in_array($value, ['auto', 'from-font'], true)) {
            $font = $this->queryFont($values);
            $length = preg_match('/^(\d*\.?\d+)%$/D', $value, $match) ? (float) $match[1] * $font->size / 100 : $this->mediaLength($value, $width, $height, $font, $this->queryFont($root));
            if (null !== $length && is_finite($length) && $length >= 0) {
                $values['text-decoration-thickness'] = $length.'px';
            }
        }

        return $values;
    }

    private function colors(array $values, array $inherited, string $scheme): array
    {
        $initial = $this->initialColors[$scheme] ??= ColorValue::stringify(ColorValue::parse('CanvasText', $scheme));
        $values['color'] ??= $initial;
        foreach ($values as $property => $value) {
            if (str_starts_with($property, '--') || 'color' !== $property && !str_ends_with($property, '-color')) {
                continue;
            }
            if ('accent-color' !== $property && isset($values['accent-color']) && 'auto' !== strtolower(trim($values['accent-color']))) {
                $value = ColorValue::accent($value, $values['accent-color']);
            }
            if ('border-color' === $property && \count($parts = ValueTokenizer::split($value)) > 1) {
                $parts = array_map(fn (string $part): string => $this->colors(['color' => $values['color'], 'border-top-color' => $part], $inherited, $scheme)['border-top-color'], $parts);
                $values[$property] = implode(' ', $parts);
                continue;
            }
            if ('scrollbar-color' === $property) {
                if ('auto' === strtolower(trim($value))) {
                    $values[$property] = 'auto';
                    continue;
                }
                $colors = [];
                foreach (ValueTokenizer::split($value) as $part) {
                    if ('currentcolor' === strtolower(trim($part))) {
                        $colors[] = 'currentcolor';
                    } elseif (\is_array($color = $this->parseColor(CurrentColor::replace($part, $values['color']) ?? $part, $scheme))) {
                        $colors[] = ColorValue::stringify($color);
                    } else {
                        $colors = [];
                        break;
                    }
                }
                $values[$property] = 2 === \count($colors) ? implode(' ', $colors) : ($inherited[$property] ?? 'auto');
                continue;
            }
            $normalized = strtolower(trim($value));
            if ('currentcolor' === $normalized) {
                $values[$property] = 'color' === $property ? ($inherited['color'] ?? $initial) : $values['color'];
                continue;
            }
            $value = CurrentColor::replace($value, 'color' === $property ? ($inherited['color'] ?? $initial) : $values['color']) ?? $value;
            $color = $this->parseColor($value, $scheme);
            if (false === $color) {
                if ('color' === $property) {
                    $values[$property] = $inherited['color'] ?? $initial;
                } elseif (str_starts_with($property, 'border-')) {
                    $values[$property] = $values['color'];
                } else {
                    unset($values[$property]);
                }
            } elseif (\is_array($color) && (isset($this->colorAliases[$normalized]) && !self::ordinaryColorName($normalized) || str_contains($value, '(') || isset(ColorValue::SYSTEM_COLORS[$normalized]) || preg_match('/^#(?:[a-f\d]{4}|[a-f\d]{8})$/i', $value))) {
                $values[$property] = ColorValue::stringify($color);
            }
        }

        return $values;
    }

    private static function ordinaryColorName(string $value): bool
    {
        $value = strtolower(trim($value));

        return (bool) preg_match('/^[a-z]+$/D', $value) && !isset(ColorValue::SYSTEM_COLORS[$value]) && \is_array(ColorValue::parse($value));
    }

    private function resolveValue(string $value, array $scope, float $width, float $height): string
    {
        if (!preg_match('/(?:env|if|--[\\w-]+)\\(/i', $value)) {
            return $value;
        }
        if (null !== $this->valueResolver) {
            return ($this->valueResolver)($value, $scope);
        }
        $substitute = fn (string $value, array $scope): string => CustomProperties::substitute($value, $scope, fn (string $value): string => EnvironmentValues::substitute($value, fn (string $name): ?string => $this->mediaFeatures['environment'][$name] ?? null));
        $conditions = function (array $scope) use ($width, $height): \Closure {
            return function (string $test, string $argument) use ($scope, $width, $height): bool {
                try {
                    return match ($test) {
                        'media' => $this->matchesMedia([MediaQuery::parse(str_starts_with($argument, '(') ? $argument : '('.$argument.')')], $width, $height),
                        'supports' => new CssParser()->supports(preg_match('/^(?:--[\\w-]+|[a-z-]+)\\s*:/i', $argument) ? '('.$argument.')' : $argument),
                        'style' => ContainerQuery::parse('style('.$argument.')')->matchesStyle($scope),
                        default => false,
                    };
                } catch (InvalidArgumentException) {
                    return false;
                }
            };
        };
        $evaluator = new FunctionEvaluator($this->functions, $substitute, fn (string $type, string $value): bool => PropertySyntax::matches($type, $value, fn (string $color): bool => \is_array($this->parseColor($color))), $conditions);

        return Conditional::resolve($evaluator->resolve($substitute($value, $scope), $scope), $conditions($scope));
    }

    private function parseColor(string $value, ?string $scheme = null): array|false|null
    {
        $scheme ??= 'dark' === ($this->mediaFeatures['prefers-color-scheme'] ?? 'light') ? 'dark' : 'light';
        // Aliases and lookup context stay fixed during this resolution pass; no results survive the next pass.
        $cacheable = \strlen($value) <= 256;
        $key = $cacheable ? $scheme.'\0'.$value : '';
        if ($cacheable && \array_key_exists($key, $this->resolvedColors)) {
            return $this->resolvedColors[$key];
        }
        $value = CurrentColor::replace($value, 'white') ?? $value;

        $resolved = ColorValue::parse($value, $scheme, function (string $alias) use ($scheme): array|false|null {
            if (null !== $this->colorLookup && \is_array($resolved = ($this->colorLookup)($alias, $scheme))) {
                return $resolved;
            }
            if (!isset($this->colorAliases[$alias]) || \is_array(ColorValue::parse($alias, $scheme))) {
                return null;
            }

            return ColorValue::parse($this->colorAliases[$alias], $scheme, null !== $this->colorLookup ? fn (string $name): array|false|null => ($this->colorLookup)($name, $scheme) : null);
        });
        if ($cacheable && \count($this->resolvedColors) < 1024) {
            $this->resolvedColors[$key] = $resolved;
        }

        return $resolved;
    }

    private static function fontLength(string $value, float $fontSize, float $rootSize, float $width, float $height): ?float
    {
        $resolved = Length::evaluate($value, static fn (string $token): ?float => self::fontPrimitive($token, $fontSize, $rootSize, $width, $height));

        return null !== $resolved && is_finite($resolved) && $resolved >= 0 ? $resolved : null;
    }

    private static function fontPrimitive(string $value, float $fontSize, float $rootSize, float $width, float $height): ?float
    {
        if (!preg_match('/^([+\-]?(?:\d+\.?\d*|\.\d+))(px|em|rem|%|pt|pc|in|cm|mm|q|vw|vh)?$/i', trim($value), $m)) {
            return null;
        }
        $unit = strtolower($m[2] ?? 'px');
        $size = Length::absolute((float) $m[1], $unit) ?? (float) $m[1] * match ($unit) {
            'em' => $fontSize,
            'rem' => $rootSize,
            '%' => $fontSize / 100,
            'vw' => $width / 100,
            'vh' => $height / 100,
            default => 1,
        };

        return is_finite($size) && $size >= 0 ? $size : null;
    }

    /**
     * Winning declarations retain their source property and order independently of used-value normalization.
     * Absence from declarations means that a computed value comes from inheritance or an initial value.
     *
     * @return array{computed: array<string, string>, resolved: array<string, string>, cascaded: array<string, string>, specified: array<string, true>, declarations: array<string, array{value: string, source: string, source-value: string, priority: list<int|string>, order: array{int, int}, important: bool, origin: string, valid: bool, initial: bool}>, matched-rules?: list<int>, animated?: array<string, true>, pseudos?: array<string, array>, starting-style?: array, starting-rules?: list<int>, scroll-driven?: bool}
     */
    public function getCascadeRecord(\Dom\Element $element, ?string $pseudoElement = null): array
    {
        if (null !== $pseudoElement) {
            return ($this->cascadeRecords[$element]['pseudos'] ?? [])[$pseudoElement] ?? ['computed' => [], 'resolved' => [], 'cascaded' => [], 'specified' => [], 'declarations' => []];
        }

        return $this->cascadeRecords[$element] ?? ['computed' => [], 'resolved' => [], 'cascaded' => [], 'specified' => [], 'declarations' => []];
    }

    public static function inheritedValues(array $parent, array $registered = []): array
    {
        static $names = null;
        $names ??= array_fill_keys(self::INHERITED, true);
        $values = array_filter($parent, static fn (string $property) => str_starts_with($property, '--') || isset($names[$property]), \ARRAY_FILTER_USE_KEY);
        foreach ($registered as $property => [$inherits, $initial]) {
            if (!$inherits) {
                unset($values[$property]);
            }
            if (!isset($values[$property]) && null !== $initial) {
                $values[$property] = $initial;
            }
        }

        return $values;
    }

    public function releaseComputedStyles(): void
    {
        $this->cascadeRecords = new \SplObjectStorage();
        $this->pseudoStyles = new \SplObjectStorage();
        $this->pseudoDeclaredStyles = new \SplObjectStorage();
        $this->userAgentWinners = new \WeakMap();
        $this->explicitWinners = new \WeakMap();
        $this->expansionDefaults = [];
        $this->expansions = [];
        $this->expansionProperties = [];
        $this->resolvedColors = [];
        $this->initialColors = [];
    }

    private function animatedProperties(array $applied, array $parent, array $cascaded, array $declarations, array $inheritedProperties, array $resolved, bool $fontDependentLineHeight): array
    {
        $animated = array_fill_keys($applied, true);
        foreach ($parent as $property => $_) {
            $value = strtolower(trim($cascaded[$property] ?? ''));
            if (isset($inheritedProperties[$property]) || 'color' === $property && 'currentcolor' === $value) {
                $animated[$property] = true;
            }
            if ('font-size' === $property && preg_match('/(?:[\d.](?:em|%)\b|%)/i', $cascaded['font-size'] ?? '') && ($declarations['font-size']['valid'] ?? false)) {
                $animated['font-size'] = true;
            }
            if ('font-weight' === $property && \in_array(strtolower($cascaded['font-weight'] ?? ''), ['bolder', 'lighter'], true)) {
                $animated['font-weight'] = true;
            }
        }
        foreach (CustomProperties::dependents($cascaded, $resolved, $animated) as $property => $_) {
            if (isset($animated[$property]) || ($declarations[$property]['valid'] ?? false)) {
                $animated[$property] = true;
            }
        }
        foreach ($cascaded as $property => $value) {
            if (isset($animated['color']) && ($declarations[$property]['valid'] ?? false) && preg_match('/\\bcurrentcolor\\b/i', $resolved[$property] ?? $value)) {
                $animated[$property] = true;
            }
        }
        if ($fontDependentLineHeight && isset($animated['font-size'])) {
            $animated['line-height'] = true;
        }

        return $animated;
    }

    private function declarationRecords(\WeakMap $winners, \Dom\Element $element, array $resolved, array $invalid = []): array
    {
        $declarations = [];
        foreach ($winners[$element] ?? [] as $property => $winner) {
            [$raw, $rank, $source] = $winner;
            $valid = !isset($invalid[$property]) && (isset($resolved[$property]) || 'initial' === strtolower(trim($raw)));
            $candidate = ($winner[4] ?? false) ? (($this->explicitWinners[$winners][$element] ?? [])[$property] ?? null) : null;
            $declarations[$property] = [
                'value' => $raw,
                'source' => $source,
                'source-value' => $winner[3] ?? $raw,
                'priority' => $rank,
                'expansion' => $winner[5] ?? [$source],
                'implicit' => $winner[4] ?? false,
                'explicit-candidate' => $candidate,
                'order' => [$rank[7], $rank[8]],
                'important' => (bool) $rank[0],
                'origin' => ($rank[0] ? !$rank[1] : $rank[1]) ? 'author' : 'user-agent',
                'valid' => $valid,
                'initial' => 'initial' === strtolower(trim($raw)) || 'unset' === strtolower(trim($raw)) && !str_starts_with($property, '--') && !\in_array($property, self::INHERITED, true),
            ];
        }

        return $declarations;
    }

    /** @return list<Declaration> */
    private function expand(Declaration $declaration): array
    {
        $source = $declaration->property.'\0'.$declaration->value;
        $key = $source.'\0'.(int) $declaration->important;
        if (!isset($this->expansions[$key])) {
            $this->expansions[$key] = DeclarationExpander::expand($declaration, fn (string $value): bool => \is_array($this->parseColor($value)));
            $this->expansionProperties[$source] = array_column($this->expansions[$key], 'property');
        }

        return $this->expansions[$key];
    }

    private function apply(\WeakMap $winners, \Dom\Element $element, Declaration $declaration, int $specificity, int $order, string $layer = CssRule::UNLAYERED, ?string $source = null, bool $userAgent = false, int $declarationIndex = 0, bool $scoped = false, int $context = 0, ?string $sourceValue = null): void
    {
        if (null === $source) {
            foreach ($this->expand($declaration) as $expanded) {
                $this->apply($winners, $element, $expanded, $specificity, $order, $layer, $declaration->property, $userAgent, $declarationIndex, $scoped, $context, $declaration->value);
            }

            return;
        }
        $winners[$element] ??= [];
        $values = &$winners[$element];
        $rank = CascadePriority::of($declaration, $specificity, $order, $layer, $userAgent, $declarationIndex, $scoped, $context);
        $sourceValue ??= $declaration->value;
        $defaultsKey = $source.'\0'.$sourceValue;
        $implicit = isset(($this->expansionDefaults[$defaultsKey] ??= DeclarationExpander::implicitDefaults($source, $sourceValue, fn (string $value): bool => \is_array($this->parseColor($value))))[$declaration->property]);
        $winner = [$declaration->value, $rank, $source, $sourceValue, $implicit, $this->expansionProperties[$defaultsKey] ?? [$source]];
        if (!$implicit && preg_match('/^border-(?:(?:top|right|bottom|left)-)?(?:width|style|color)$/D', $declaration->property)) {
            $explicitMap = $this->explicitWinners[$winners] ??= new \WeakMap();
            $explicitMap[$element] ??= [];
            $explicit = &$explicitMap[$element];
            if (!isset($explicit[$declaration->property]) || $rank >= $explicit[$declaration->property][1]) {
                $explicit[$declaration->property] = $winner;
            }
        }
        if ($userAgent && !$declaration->important) {
            $targetDefaults = $this->userAgentWinners[$winners] ??= new \WeakMap();
            $targetDefaults[$element] ??= [];
            $defaults = &$targetDefaults[$element];
            if (!isset($defaults[$declaration->property]) || $rank >= $defaults[$declaration->property][1]) {
                $defaults[$declaration->property] = $winner;
            }
        }
        if (!isset($values[$declaration->property]) || $rank >= $values[$declaration->property][1]) {
            $values[$declaration->property] = $winner;
        }
    }

    public function hasScrollControls(): bool
    {
        return array_any($this->rules, static fn (CssRule $rule): bool => null !== $rule->selector->pseudoElement && (str_starts_with($rule->selector->pseudoElement, 'scroll-button(') || 'scroll-marker' === $rule->selector->pseudoElement) && array_any($rule->declarations, static fn (Declaration $declaration): bool => 'content' === $declaration->property && !\in_array(strtolower(trim($declaration->value)), ['none', 'normal'], true)));
    }

    public function hasContainerQueries(): bool
    {
        return array_any($this->rules, static fn (CssRule $rule): bool => [] !== $rule->containers);
    }

    private function matchesContainers(\Dom\Element $element, array $queries, ?\SplObjectStorage $containers, float $viewportWidth, float $viewportHeight): bool
    {
        if (null === $containers) {
            return false;
        }
        foreach ($queries as $query) {
            if (!$query instanceof ContainerQuery) {
                return false;
            }
            if ($query->styles) {
                $style = $this->containerOf($element, $query->name, null, $containers);
                if (null === $style || !$query->matchesStyle($style['style'])) {
                    return false;
                }
            }
            if ($query->scroll) {
                $scroll = $this->containerOf($element, $query->name, 'scroll-state', $containers);
                if (null === $scroll || !$query->matchesScrollState($scroll['scroll'] ?? [])) {
                    return false;
                }
            }
            if (null !== $query->condition) {
                $box = $this->containerOf($element, $query->name, $query->condition->usesBlockAxis() ? 'size' : 'inline-size', $containers);
                $font = $this->queryFont($box['style'] ?? []);
                $rootFont = $this->queryFont(($containers[$element->ownerDocument->documentElement] ?? [])['style'] ?? []);
                if (null === $box || !isset($box['width'], $box['height']) || !is_finite($box['width']) || !is_finite($box['height']) || $box['width'] < 0 || $box['height'] < 0 || !$query->condition->evaluate(fn (string $feature, string $operator, ?string $value): bool => \in_array($feature, ['width', 'height', 'inline-size', 'block-size', 'aspect-ratio', 'orientation'], true) && $this->evaluateMedia($feature, $operator, $value, $box['width'], $box['height'], fn (string $length): ?float => $this->mediaLength($length, $viewportWidth, $viewportHeight, $font, $rootFont)))) {
                    return false;
                }
            }
        }

        return true;
    }

    private function containerOf(\Dom\Element $element, ?string $name, ?string $type, \SplObjectStorage $containers): ?array
    {
        for ($ancestor = $element->parentElement; null !== $ancestor; $ancestor = $ancestor->parentElement) {
            if (!isset($containers[$ancestor])) {
                continue;
            }
            $box = $containers[$ancestor];
            $style = $box['style'] ?? [];
            $types = ContainerQuery::containerTypesOf($style);
            if (null !== $type && !\in_array($type, $types, true) && ('inline-size' !== $type || !\in_array('size', $types, true))) {
                continue;
            }
            if (null === $name || \in_array($name, ContainerQuery::containerNames($style), true)) {
                return $box + ['style' => $style];
            }
        }

        return null;
    }

    private function matchesMedia(array $conditions, float $width, float $height): bool
    {
        foreach ($conditions as $condition) {
            $query = $condition instanceof MediaQuery ? $condition : MediaQuery::parse($condition);
            if (!$query->evaluate(fn (string $feature, string $operator, ?string $value): bool => $this->evaluateMedia($feature, $operator, $value, $width, $height))) {
                return false;
            }
        }

        return true;
    }

    private function evaluateMedia(string $feature, string $operator, ?string $value, float $width, float $height, ?\Closure $lengthResolver = null): bool
    {
        if ('media-type' === $feature) {
            return 'all' === $value || $value === ($this->mediaFeatures['type'] ?? 'screen');
        }
        if (\in_array($feature, ['width', 'inline-size', 'device-width', 'height', 'block-size', 'device-height'], true)) {
            $actual = \in_array($feature, ['height', 'block-size', 'device-height'], true) ? $height : $width;

            return MediaQuery::compare($actual, $operator, null !== $value ? (null !== $lengthResolver ? $lengthResolver($value) : $this->mediaLength($value, $width, $height)) : null);
        }
        if (\in_array($feature, ['aspect-ratio', 'device-aspect-ratio'], true)) {
            return MediaQuery::compare($height > 0 ? $width / $height : \INF, $operator, null !== $value ? MediaQuery::ratio($value) : null);
        }
        if ('orientation' === $feature) {
            return 'bool' === $operator || $value === ($height > $width ? 'portrait' : 'landscape');
        }
        $features = $this->mediaFeatures + ['prefers-color-scheme' => 'light', 'prefers-reduced-motion' => 'no-preference', 'prefers-contrast' => 'no-preference', 'forced-colors' => 'none', 'hover' => 'none', 'any-hover' => 'none', 'pointer' => 'none', 'any-pointer' => 'none', 'resolution' => 1.0, 'color' => 8, 'color-index' => 0, 'monochrome' => 0, 'grid' => 0, 'scripting' => 'none', 'overflow-block' => 'scroll', 'overflow-inline' => 'scroll'];
        if (!\array_key_exists($feature, $features)) {
            return false;
        }
        $actual = $features[$feature];
        if (\is_int($actual) || \is_float($actual)) {
            $expected = null === $value ? null : ('resolution' === $feature ? MediaQuery::resolution($value) : (is_numeric($value) ? (float) $value : null));

            return MediaQuery::compare($actual, $operator, $expected);
        }

        return 'bool' === $operator ? !\in_array($actual, ['none', 'no-preference', false, 0, '0'], true) : $actual === $value;
    }

    private function textSpacing(array $style, array $inherited, array $root, float $width, float $height): array
    {
        if (!isset($style['letter-spacing']) && !isset($style['word-spacing']) && 0.0 === $this->defaultFont->letterSpacing && 0.0 === $this->defaultFont->wordSpacing) {
            return $style;
        }
        foreach (['letter-spacing' => $this->defaultFont->letterSpacing, 'word-spacing' => $this->defaultFont->wordSpacing] as $property => $default) {
            if (!isset($style[$property]) && 0.0 === $default) {
                continue;
            }
            $value = strtolower(trim($style[$property] ?? $default.'px'));
            if ('normal' === $value || is_numeric($value)) {
                $style[$property] = $value;
                continue;
            }
            $length = $this->mediaLength($value, $width, $height, $this->queryFont($style), $this->queryFont($root), null, null, true);
            $style[$property] = null !== $length ? $length.'px' : ($inherited[$property] ?? 'normal');
        }

        return $style;
    }

    private function queryFont(array $style): FontStyle
    {
        return new FontStyle($style['font-family'] ?? $this->defaultFont->family, (float) ($style['font-size'] ?? $this->defaultFont->size), (float) ($style['line-height'] ?? $this->defaultFont->lineHeight), (int) ($style['font-weight'] ?? $this->defaultFont->weight), $style['direction'] ?? $this->defaultFont->direction, $style['font-style'] ?? $this->defaultFont->style);
    }

    private function mediaLength(string $value, float $width, float $height, ?FontStyle $font = null, ?FontStyle $rootFont = null, ?bool $vertical = null, ?float $reference = null, bool $computedSpacing = false): ?float
    {
        $font ??= $this->queryFont([]);
        $rootFont ??= $this->queryFont([]);
        $length = Length::evaluate($value, function (string $token) use ($width, $height, $font, $rootFont, $vertical, $reference, $computedSpacing): ?float {
            if (!preg_match('/^([+\-]?(?:\d+\.?\d*|\.\d+)(?:e[+\-]?\d+)?)(%|px|em|rem|r?ch|r?ex|r?lh|pt|pc|in|cm|mm|q|[sld]?v(?:w|h|i|b|min|max))?$/i', $token, $m)) {
                return null;
            }
            $unit = strtolower($m[2] ?? '');
            if ('%' === $unit && null === $reference) {
                return null;
            }
            $metricsFont = str_starts_with($unit, 'r') ? $rootFont : $font;
            $advance = \in_array($unit, ['ch', 'rch', 'ex', 'rex'], true) ? $this->textMeasurer->measure('0', $metricsFont)->width : 0;
            // Font measurements may use device coordinates; inherited computed spacing uses CSS pixels.
            if ($computedSpacing && \in_array($unit, ['ch', 'rch', 'ex', 'rex'], true)) {
                $advance = $this->layoutMetrics?->constrainLength($advance, 'computed-font-advance', false, $metricsFont) ?? $advance;
            }

            $number = (float) $m[1];
            $resolved = Length::absolute($number, $unit) ?? $number * match (true) {
                '%' === $unit => $reference / 100,
                \in_array($unit, ['ch', 'rch', 'ex', 'rex'], true) => $advance,
                \in_array($unit, ['em', 'rem'], true) => $metricsFont->size,
                \in_array($unit, ['lh', 'rlh'], true) => $metricsFont->lineHeight,
                (bool) preg_match('/^[sld]?v(?:w|i)$/', $unit) => $width / 100,
                (bool) preg_match('/^[sld]?v(?:h|b)$/', $unit) => $height / 100,
                str_ends_with($unit, 'vmin') => min($width, $height) / 100,
                str_ends_with($unit, 'vmax') => max($width, $height) / 100,
                default => 1,
            };

            return null === $vertical ? $resolved : ($this->layoutMetrics?->resolveUnit($number, $unit, $vertical, $resolved) ?? $resolved);
        });
        if (null !== $length && null !== $vertical) {
            $length = $this->layoutMetrics?->constrainLength($length, 'view-timeline-inset', $vertical, $font) ?? $length;
        }

        return null !== $length && is_finite($length) ? $length : null;
    }
}
