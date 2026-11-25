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
use Symfony\Component\HtmlRenderer\Css\ColorValue;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Event\DocumentEvent;
use Symfony\Component\HtmlRenderer\Event\KeyboardEvent;
use Symfony\Component\HtmlRenderer\Event\PointerEvent;
use Symfony\Component\HtmlRenderer\Event\WheelEvent;
use Symfony\Component\HtmlRenderer\Form\ConstraintValidator;
use Symfony\Component\HtmlRenderer\Form\ControlState;
use Symfony\Component\HtmlRenderer\Form\ControlValue;
use Symfony\Component\HtmlRenderer\Form\NumberStepper;
use Symfony\Component\HtmlRenderer\Form\NumericControl;
use Symfony\Component\HtmlRenderer\Form\SelectOptions;
use Symfony\Component\HtmlRenderer\Form\Submission;
use Symfony\Component\HtmlRenderer\Html\EmbeddedDocument;
use Symfony\Component\HtmlRenderer\Html\EmbeddedDocumentBudget;
use Symfony\Component\HtmlRenderer\Html\ShadowRoots;
use Symfony\Component\HtmlRenderer\Html\TreeScope;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Layout\ScrollGeometry;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\Resource\ResourceCollection;
use Symfony\Component\HtmlRenderer\Text\TextEditor;
use Symfony\Component\HtmlRenderer\Text\TextSelection;

final class RenderSession
{
    private array $listeners = [];
    private array $highlights = [];
    private array $visited = [];
    private ?\Dom\Element $target = null;
    private array $topLayers = [];
    private \SplObjectStorage $interestShown;
    private \SplObjectStorage $interestActive;
    private bool $updatingInterest = false;
    private \SplObjectStorage $modalFocus;
    private \SplObjectStorage $returnValues;
    private \SplObjectStorage $overlayTransitions;
    private int $listenerSequence = 0;
    private array $invalidationListeners = [];
    private readonly int $maxTextBytes;
    private Timeline $timeline;
    private ResourceCollection $resources;
    private \SplObjectStorage $embedded;
    private EmbeddedDocumentBudget $embeddedBudget;
    private int $embeddedDepth = 0;
    private ?\Dom\Element $embeddedPointer = null;
    private ?array $pressedScrollControl = null;
    private ?\Dom\Element $embeddedHost = null;
    private ?self $embeddedOwner = null;
    private ?object $embeddedReservation = null;
    private ?StyleResolver $resolver = null;
    private ?ShadowRoots $shadowRoots = null;
    private ?array $shadowView = null;
    private ?string $shadowSourceRevision = null;
    private ?string $shadowRevision = null;
    private float $time = 0.0;
    private ?RenderingResult $result = null;
    private ?string $revision = null;
    private bool $initialScrollApplied = false;
    private ?\Dom\Element $focused = null;
    private ?\Dom\Element $hovered = null;
    private ?\Dom\Element $pressed = null;
    private ?\Dom\Element $selectionTarget = null;
    private int $selectionAnchor = 0;
    private ?\Dom\Element $rangeTarget = null;
    private string $rangeStartValue = '';
    private \SplObjectStorage $suggestions;
    private \SplObjectStorage $values;
    private \SplObjectStorage $checked;
    private \SplObjectStorage $selections;
    private \SplObjectStorage $editors;
    private \SplObjectStorage $scroll;
    private \SplObjectStorage $scrollDirections;
    private \SplObjectStorage $states;
    private \SplObjectStorage $userAgentStyles;
    private \SplObjectStorage $interacted;
    private \SplObjectStorage $pickerOptions;
    private \SplObjectStorage $pickerScroll;

    public function __construct(private readonly \Dom\HTMLDocument $document, private RenderingContext $context, private readonly int $maxBytes = 4194304, ?int $maxTextBytes = null)
    {
        $this->maxTextBytes = $maxTextBytes ?? $maxBytes;
        if ($maxBytes <= 0 || $this->maxTextBytes <= 0) {
            throw new \InvalidArgumentException('Document and text size limits must be positive.');
        }
        $this->interestShown = new \SplObjectStorage();
        $this->interestActive = new \SplObjectStorage();
        $this->modalFocus = new \SplObjectStorage();
        $this->returnValues = new \SplObjectStorage();
        $this->overlayTransitions = new \SplObjectStorage();
        $this->suggestions = new \SplObjectStorage();
        $this->values = new \SplObjectStorage();
        $this->checked = new \SplObjectStorage();
        $this->selections = new \SplObjectStorage();
        $this->editors = new \SplObjectStorage();
        $this->scroll = new \SplObjectStorage();
        $this->scrollDirections = new \SplObjectStorage();
        $this->states = new \SplObjectStorage();
        $this->userAgentStyles = new \SplObjectStorage();
        $this->interacted = new \SplObjectStorage();
        $this->pickerOptions = new \SplObjectStorage();
        $this->pickerScroll = new \SplObjectStorage();
        $this->timeline = new Timeline();
        $this->resources = new ResourceCollection($context->resourceLoader);
        $this->embedded = new \SplObjectStorage();
        $this->embeddedBudget = new EmbeddedDocumentBudget($maxBytes);
    }

    public function getDocument(): \Dom\HTMLDocument
    {
        return $this->document;
    }

    public function getContext(): RenderingContext
    {
        return $this->context;
    }

    public function setContext(RenderingContext $context): void
    {
        if ($this->context->resourceLoader !== $context->resourceLoader) {
            $this->resources = new ResourceCollection($context->resourceLoader);
        }
        if ($this->context->resourceLoader !== $context->resourceLoader || $this->context->layoutMetrics !== $context->layoutMetrics || $this->context->defaultFont !== $context->defaultFont || $this->context->textMeasurer !== $context->textMeasurer || $this->context->mediaFeatures !== $context->mediaFeatures || $this->context->userAgentStylesheet !== $context->userAgentStylesheet || $this->context->colors !== $context->colors || $this->context->colorLookup !== $context->colorLookup) {
            $this->resolver = null;
        }
        $this->context = $context;
        $this->invalidate();
    }

    public function advance(float $seconds): void
    {
        if (!is_finite($seconds) || $seconds < 0 || !is_finite($this->time + $seconds)) {
            throw new \InvalidArgumentException('Elapsed time must be finite and non-negative.');
        }
        if ($seconds > 0) {
            $this->time += $seconds;
            foreach ($this->embedded as $frame) {
                $this->embedded[$frame]['session']->advance($seconds);
            }
            $this->invalidate();
        }
    }

    public function onInvalidation(\Closure $listener): \Closure
    {
        $id = ++$this->listenerSequence;
        $this->invalidationListeners[$id] = $listener;

        return function () use ($id): void { unset($this->invalidationListeners[$id]); };
    }

    public function invalidate(): void
    {
        if (null === $this->revision) {
            return;
        }
        $this->revision = null;
        foreach ($this->invalidationListeners as $listener) {
            $listener();
        }
    }

    public function render(): RenderingResult
    {
        $this->pruneLayers();
        foreach (['hovered', 'pressed', 'selectionTarget', 'rangeTarget'] as $capture) {
            if (null !== $this->$capture && (!$this->containsElement($this->$capture))) {
                $this->$capture = null;
            }
        }
        $this->updateInterest();
        $serialized = $this->document->saveHtml();
        if (\strlen($serialized) > $this->maxBytes) {
            throw new \LengthException('The document exceeds the configured size limit.');
        }
        if (0 === $this->embeddedDepth) {
            $this->embeddedBudget->setRootBytes(\strlen($serialized));
        } elseif (null !== $this->embeddedReservation && !$this->embeddedBudget->resize($this->embeddedReservation, \strlen($serialized))) {
            throw new \LengthException('The embedded documents exceed the aggregate document size limit.');
        }
        $this->pruneEmbedded();
        foreach ($this->embedded as $frame) {
            $entry = $this->embedded[$frame];
            if (!$this->embeddedBudget->allows($entry['reservation'])) {
                $this->forgetEmbedded($frame);
                $this->invalidate();

                continue;
            }
            if (($entry['result'] ?? null) !== $entry['session']->render()) {
                $this->invalidate();
            }
        }
        $revision = $this->refreshShadowView(hash('xxh128', $serialized))['revision'];
        if ($revision !== $this->revision) {
            if (null !== $root = $this->document->documentElement) {
                Html\IdAttributes::mark($root);
            }
            foreach ([$this->suggestions, $this->values, $this->checked, $this->selections, $this->editors, $this->scroll, $this->scrollDirections, $this->states, $this->userAgentStyles, $this->returnValues, $this->interacted, $this->pickerOptions, $this->pickerScroll, $this->interestShown, $this->interestActive] as $storage) {
                $detached = [];
                foreach ($storage as $element) {
                    if (!$this->containsElement($element)) {
                        $detached[] = $element;
                    }
                }
                foreach ($detached as $element) {
                    unset($storage[$element]);
                }
            }
            foreach ($this->listeners as $type => $listeners) {
                foreach ($listeners as $id => [$element]) {
                    if (null !== $element && !$this->containsElement($element)) {
                        unset($this->listeners[$type][$id]);
                    }
                }
            }
            $this->getFocusedElement();
            $states = [];
            $selectedOptions = new \SplObjectStorage();
            foreach ($this->elements('select') as $select) {
                $selected = new \SplObjectStorage();
                foreach ($this->selectedOptions($select) as $option) {
                    $selected[$option] = true;
                }
                foreach ($select->querySelectorAll('option') as $option) {
                    $selectedOptions[$option] = isset($selected[$option]);
                }
            }
            foreach ($this->elements('*') as $element) {
                $state = array_keys($this->states[$element] ?? []);
                if ($element === $this->document->documentElement && $this->highlights) {
                    $state['highlights'] = $this->highlights;
                }
                if (isset($this->interestShown[$element])) {
                    $state['anchor'] = $this->interestShown[$element];
                }
                if (false !== $layer = array_search($element, $this->topLayers, true)) {
                    if ('select' === $element->localName) {
                        $state['picker-open'] = true;
                        $state['picker-layer'] = $layer;
                        $state['picker-scroll-y'] = $this->pickerScroll[$element] ?? 0.0;
                    } else {
                        $state['top-layer'] = $layer;
                        $state[] = isset($this->modalFocus[$element]) ? 'modal' : 'popover-open';
                    }
                }
                if ($element === $this->getTarget()) {
                    $state[] = 'target';
                }
                if (\in_array($element->localName, ['a', 'area'], true) && isset($this->visited[$element->getAttribute('href') ?? ''])) {
                    $state[] = 'visited';
                }
                if ($element === $this->focused) {
                    $state[] = 'focus';
                    $type = strtolower($element->getAttribute('type') ?? 'text');
                    if ('textarea' === $element->localName || 'input' === $element->localName && !\in_array($type, ['hidden', 'checkbox', 'radio', 'button', 'submit', 'reset', 'image', 'range'], true)) {
                        [$anchor, $caret] = $this->editor($element)->getSelection();
                        $scheme = $this->context->mediaFeatures['prefers-color-scheme'] ?? 'light';
                        $foreground = ColorValue::stringify(ColorValue::parse('HighlightText', $scheme));
                        $background = ColorValue::stringify(ColorValue::parse('Highlight', $scheme));
                        $state['selection'] = new TextSelection($anchor, $caret, true, $foreground, $background);
                        if (null !== $completion = $this->activeCompletion($element)) {
                            $state['completion'] = $completion;
                        }
                    }
                }
                if (null !== $this->pressed && $this->composedContains($element, $this->pressed)) {
                    $state[] = 'active';
                }
                if ($element === $this->hovered) {
                    $state[] = 'hover';
                }
                if (isset($this->userAgentStyles[$element])) {
                    $state['user-agent-styles'] = $this->userAgentStyles[$element];
                }
                if (isset($this->interacted[$element])) {
                    $state['user-interacted'] = true;
                }
                if (isset($this->values[$element])) {
                    $state['value'] = $this->values[$element];
                } elseif ('input' === $element->localName && \in_array(strtolower($element->getAttribute('type') ?? ''), ['file', 'color'], true)) {
                    $state['value'] = $this->getValue($element);
                }
                if ('select' === $element->localName) {
                    $options = $this->selectedOptions($element);
                    $state['value'] = isset($options[0]) ? SelectOptions::value($options[0]) : '';
                    $state['selection-labels'] = array_map(SelectOptions::label(...), $options);
                    $state['selected-values'] = array_map(SelectOptions::value(...), $options);
                }
                if ('option' === $element->localName && null !== $select = $element->closest('select')) {
                    if (($this->pickerOptions[$select] ?? null) === $element && ($this->focused === $select || !SelectOptions::isListBox($select))) {
                        $state[] = 'picker-active';
                    }
                }
                if (isset($selectedOptions[$element])) {
                    $state['checked'] = $selectedOptions[$element];
                }
                if (isset($this->checked[$element])) {
                    $state['checked'] = $this->checked[$element];
                }
                if (isset($this->scroll[$element])) {
                    [$state['scroll-x'], $state['scroll-y']] = $this->scroll[$element];
                    $state['scroll-direction'] = $this->scrollDirections[$element] ?? [];
                }
                if (null !== $this->result && null !== $previousScroll = $this->result->getScrollGeometry($element)) {
                    $snapType = $this->result->getComputedStyle($element)['scroll-snap-type'] ?? 'none';
                    if ($previousScroll->maxX > 0 && Layout\ScrollSnapGeometry::mode($snapType, false)) {
                        $state['scroll-snap-x'] = $previousScroll->offsetX;
                    }
                    if ($previousScroll->maxY > 0 && Layout\ScrollSnapGeometry::mode($snapType, true)) {
                        $state['scroll-snap-y'] = $previousScroll->offsetY;
                    }
                }
                if ($state) {
                    $states[] = [$element, $state];
                }
            }
            $this->result = HtmlRenderer::compute($this->document, $this->context, $states, $this->timeline, $this->time, $this->resources, $this->resolver ??= new StyleResolver($this->resources, $this->context->defaultFont, $this->context->textMeasurer, $this->context->mediaFeatures, $this->context->userAgentStylesheet, $this->context->colors, $this->context->colorLookup, null, $this->context->layoutMetrics), $this->renderEmbedded(...), $this->shadowView);
            foreach ($this->scroll as $element) {
                $geometry = $this->result->getScrollGeometry($element);
                $this->scroll[$element] = null === $geometry ? [0.0, 0.0] : [$geometry->offsetX, $geometry->offsetY];
            }
            $this->revision = $revision;
        }

        if (!$this->initialScrollApplied) {
            $this->initialScrollApplied = true;
            foreach ($this->elements('*') as $element) {
                if ('nearest' === ($this->result->getComputedStyle($element)['scroll-initial-target'] ?? 'none') && $this->result->getBounds($element)) {
                    $this->scrollIntoView($element);

                    return $this->render();
                }
            }
        }

        return $this->result;
    }

    /**
     * Inspects the current composition without computing styles or layout.
     *
     * @return iterable<array{\Dom\Element, ?\Dom\Element}> Native source elements and composed parents in preorder
     *
     * @internal
     */
    public function getComposedElements(): iterable
    {
        if (null !== $this->shadowRoots || null !== $this->document->querySelector('template[shadowrootmode]')) {
            $serialized = $this->document->saveHtml();
            if (\strlen($serialized) > $this->maxBytes) {
                throw new \LengthException('The document exceeds the configured size limit.');
            }
            $this->refreshShadowView(hash('xxh128', $serialized));
        }
        $view = $this->shadowView;
        foreach (($view['document'] ?? $this->document)->querySelectorAll('*') as $visual) {
            $element = null === $view ? $visual : $view['owners'][$visual];
            if (!$this->containsElement($element)) {
                continue;
            }
            $parent = null === $view ? $element->parentElement : ($view['parents'][$element] ?? null);

            yield [$element, $parent instanceof \Dom\Element ? $parent : null];
        }
    }

    /** @internal Tests current source ownership and active native composition membership. */
    public function containsElement(\Dom\Element $element): bool
    {
        if ($element->ownerDocument !== $this->document) {
            return false;
        }
        if (null === $this->shadowView) {
            return $element->isConnected;
        }
        if (!$this->shadowRoots->isActiveNode($element, $this->document)) {
            return false;
        }
        $view = $this->shadowView;
        if (!isset($view['mirrors'][$element])) {
            $view = $this->refreshShadowView(hash('xxh128', $this->document->saveHtml()))['view'];
        }

        return null === $view ? $element->isConnected : isset($view['mirrors'][$element]);
    }

    private function elements(string $selector): iterable
    {
        if (null === $this->shadowView) {
            return $this->document->querySelectorAll($selector);
        }
        $elements = [];
        foreach ($this->shadowView['document']->querySelectorAll($selector) as $element) {
            $elements[] = $this->shadowView['owners'][$element];
        }

        return $elements;
    }

    private function composedParent(\Dom\Element $element): ?\Dom\Element
    {
        if (null === $this->shadowView) {
            return $element->parentElement;
        }
        $parent = $this->shadowView['parents'][$element] ?? null;

        return $parent instanceof \Dom\Element ? $parent : null;
    }

    private function composedContains(\Dom\Element $ancestor, \Dom\Element $element): bool
    {
        for ($depth = 0; null !== $element && $depth <= 512; ++$depth, $element = $this->composedParent($element)) {
            if ($ancestor === $element) {
                return true;
            }
        }

        return false;
    }

    private function isInert(\Dom\Element $element): bool
    {
        for ($depth = 0; null !== $element && $depth <= 512; ++$depth, $element = $this->composedParent($element)) {
            if ($element->hasAttribute('inert')) {
                return true;
            }
        }

        return false;
    }

    /** @return array{revision: string, view: ?array} */
    private function refreshShadowView(string $sourceRevision): array
    {
        if (null === $this->shadowRoots) {
            if ($this->shadowSourceRevision === $sourceRevision || null === $this->document->querySelector('template[shadowrootmode]')) {
                $this->shadowSourceRevision = $sourceRevision;

                return ['revision' => $sourceRevision, 'view' => $this->shadowView];
            }
            $this->shadowRoots = new ShadowRoots($this->maxTextBytes);
        }
        $revision = hash('xxh128', $sourceRevision."\0".$this->shadowRoots->getFingerprint());
        if ($revision !== $this->shadowRevision) {
            $view = $this->shadowRoots->createView($this->document);
            if (null !== $this->shadowView) {
                $this->resources->releaseDocument($this->shadowView['document']);
            }
            $this->shadowView = $view['depths'] ? $view : null;
            $this->shadowRevision = hash('xxh128', $sourceRevision."\0".$view['fingerprint']);
            if (null === $this->shadowView) {
                $this->shadowRoots = null;
                $this->shadowRevision = null;
            }
        }
        $this->shadowSourceRevision = $sourceRevision;

        return ['revision' => $this->shadowRevision ?? $sourceRevision, 'view' => $this->shadowView];
    }

    /** Cached session/source inspection; call render() to apply native layout mutations. */
    public function getEmbeddedSession(\Dom\Element $frame): ?self
    {
        if (!$this->containsElement($frame) || 'iframe' !== strtolower($frame->localName)) {
            if (isset($this->embedded[$frame])) {
                $this->forgetEmbedded($frame);
                $this->invalidate();
            }

            return null;
        }
        $entry = $this->embedded[$frame] ?? null;
        if (null !== $this->revision && null !== $entry && $entry['attributes'] === self::embeddedSourceSignature($frame)) {
            return $entry['session'];
        }
        $this->render();

        return ($this->embedded[$frame] ?? [])['session'] ?? null;
    }

    /** The document session that owns the deepest focused element. */
    public function getFocusedSession(): self
    {
        $element = $this->getFocusedElement();

        return null !== $element && isset($this->embedded[$element]) ? $this->embedded[$element]['session']->getFocusedSession() : $this;
    }

    private static function embeddedSourceSignature(\Dom\Element $frame): array
    {
        return $frame->hasAttribute('srcdoc') ? ['srcdoc', $frame->getAttribute('srcdoc')] : ['src', trim($frame->getAttribute('src') ?? '')];
    }

    private function pruneEmbedded(): void
    {
        $removed = [];
        foreach ($this->embedded as $frame) {
            if (!$this->containsElement($frame) || $this->embedded[$frame]['attributes'] !== self::embeddedSourceSignature($frame)) {
                $removed[] = $frame;
            }
        }
        foreach ($removed as $frame) {
            $this->forgetEmbedded($frame);
        }
    }

    private function forgetEmbedded(\Dom\Element $frame): void
    {
        $entry = $this->embedded[$frame] ?? null;
        if (null === $entry) {
            return;
        }
        ($entry['unsubscribe'])();
        if ($this->embeddedPointer === $frame) {
            $this->embeddedPointer = null;
        }
        $child = $entry['session'];
        $child->embeddedHost = null;
        $child->embeddedOwner = null;
        $child->embeddedReservation = null;
        $child->focus(null);
        $frames = iterator_to_array($child->embedded, false);
        foreach ($frames as $nested) {
            $child->forgetEmbedded($nested);
        }
        $this->resources->releaseDocument($child->document);
        $child->resources = new ResourceCollection($child->context->resourceLoader);
        $child->resolver = null;
        $child->embeddedDepth = 0;
        $child->embeddedBudget = new EmbeddedDocumentBudget($child->maxBytes);
        $child->invalidate();
        unset($this->embedded[$frame]);
    }

    private function embeddedContext(float $width, float $height): RenderingContext
    {
        $context = $this->context->withViewport($width, $height);
        $viewportStyle = "html{height:100vh;overflow:auto}\n";
        $stylesheet = str_starts_with($context->userAgentStylesheet, $viewportStyle) ? $context->userAgentStylesheet : $viewportStyle.$context->userAgentStylesheet;

        return new RenderingContext($width, $height, $context->textMeasurer, $context->resourceLoader, $context->layoutMetrics, $context->defaultFont, $context->mediaFeatures, $stylesheet, $context->colors, $context->hyphenator, $context->replacedContentResolver, $context->colorLookup);
    }

    private function renderEmbedded(Fragment $fragment): ?DisplayList
    {
        $frame = $fragment->node;
        if (!$frame instanceof \Dom\Element) {
            return null;
        }
        $viewport = $fragment->getContentBox();
        if ($this->embeddedDepth >= 3 || $viewport->width <= 0 || $viewport->height <= 0 || null === $source = EmbeddedDocument::read($frame, $this->resources->load(...))) {
            $this->forgetEmbedded($frame);

            return null;
        }
        $fingerprint = hash('xxh128', $source);
        $entry = $this->embedded[$frame] ?? null;
        if (null !== $entry && !$this->embeddedBudget->allows($entry['reservation'])) {
            $this->forgetEmbedded($frame);
            $entry = null;
        }
        if (null === $entry || $entry['source'] !== $fingerprint) {
            if (null !== $entry) {
                $this->forgetEmbedded($frame);
                $entry = null;
            }
            if (null === $reservation = $this->embeddedBudget->reserve(\strlen($source))) {
                return null;
            }
            $child = new HtmlRenderer($this->maxTextBytes)->createSession($source, $this->embeddedContext($viewport->width, $viewport->height));
            $child->resources = $this->resources;
            $child->embeddedBudget = $this->embeddedBudget;
            $child->embeddedDepth = $this->embeddedDepth + 1;
            $child->embeddedHost = $frame;
            $child->embeddedOwner = $this;
            $child->embeddedReservation = $reservation;
            $child->time = $this->time;
            $entry = ['source' => $fingerprint, 'attributes' => self::embeddedSourceSignature($frame), 'session' => $child, 'reservation' => $reservation, 'unsubscribe' => $child->onInvalidation($this->invalidate(...))];
            $this->embedded[$frame] = $entry;
        }
        $child = $entry['session'];
        $context = $this->embeddedContext($viewport->width, $viewport->height);
        if ($child->context != $context) {
            $child->setContext($context);
        }
        if ($child->resources !== $this->resources) {
            $child->resources = $this->resources;
            $child->resolver = null;
            $child->invalidate();
        }

        $entry['result'] = $child->render();
        $this->embedded[$frame] = $entry;

        return $entry['result']->getDisplayList();
    }

    /**
     * Sets a host-maintained element state without changing document attributes.
     */
    /** @param string|list<string> $texts */
    public function highlight(string $name, string|array $texts, bool $matchCase = false): void
    {
        $this->highlights = Text\HighlightRanges::register($this->highlights, $name, (array) $texts, $matchCase);
        $this->invalidate();
    }

    public function removeHighlight(string $name): void
    {
        $this->highlights = Text\HighlightRanges::register($this->highlights, $name, null);
        $this->invalidate();
    }

    public function setState(\Dom\Element $element, string $state, bool $enabled = true): void
    {
        if (!$this->containsElement($element) || !preg_match('/^[a-z][a-z0-9-]*$/D', $state) || \in_array($state, ['target', 'visited', 'active', 'focus', 'focus-visible', 'focus-within', 'hover', 'checked', 'enabled', 'disabled', 'required', 'optional', 'read-only', 'read-write', 'valid', 'invalid', 'user-valid', 'user-invalid', 'placeholder-shown', 'in-range', 'out-of-range', 'modal', 'popover-open', 'picker-active'], true)) {
            throw new \InvalidArgumentException('The state must be a host-maintained name on an element in this document.');
        }
        $states = $this->states[$element] ?? [];
        if (isset($states[$state]) === $enabled) {
            return;
        }
        if ($enabled) {
            $states[$state] = true;
        } else {
            unset($states[$state]);
        }
        if ($states) {
            $this->states[$element] = $states;
        } else {
            unset($this->states[$element]);
        }
        $this->invalidate();
    }

    /**
     * Supplies host UA declarations while keeping author CSS authoritative.
     *
     * @param array<string, string> $properties
     */
    public function setUserAgentStyle(\Dom\Element $element, array $properties, ?string $pseudoElement = null): void
    {
        if (!$this->containsElement($element) || null !== $pseudoElement && !preg_match('/^[a-z][a-z0-9-]*$/D', $pseudoElement)) {
            throw new \InvalidArgumentException('The style target must be connected to this document and its pseudo-element must be a resolved role name.');
        }
        $normalized = [];
        foreach ($properties as $property => $value) {
            if (!\is_string($property) || !\is_string($value) || !preg_match('/^(?:--[a-z0-9_-]+|-?[a-z][a-z0-9_-]*)$/iD', $property)) {
                throw new \InvalidArgumentException('UA declarations require CSS property names and string values.');
            }
            $property = str_starts_with($property, '--') ? $property : strtolower($property);
            unset($normalized[$property]);
            $normalized[$property] = $value;
        }
        $role = $pseudoElement ?? '';
        $styles = $this->userAgentStyles[$element] ?? [];
        if (($styles[$role] ?? []) === $normalized) {
            return;
        }
        if ($normalized) {
            $styles[$role] = $normalized;
        } else {
            unset($styles[$role]);
        }
        if ($styles) {
            $this->userAgentStyles[$element] = $styles;
        } else {
            unset($this->userAgentStyles[$element]);
        }
        $this->invalidate();
    }

    public function on(string $type, \Closure $listener, ?\Dom\Element $element = null): \Closure
    {
        if (null !== $element && !$this->containsElement($element)) {
            throw new \InvalidArgumentException('The listener target must belong to this document.');
        }
        $id = ++$this->listenerSequence;
        $this->listeners[$type][$id] = [$element, $listener];

        return function () use ($type, $id): void {
            unset($this->listeners[$type][$id]);
            if (!($this->listeners[$type] ?? [])) {
                unset($this->listeners[$type]);
            }
        };
    }

    public function dispatch(DocumentEvent $event): void
    {
        if (null !== $this->shadowView) {
            $this->refreshShadowView(hash('xxh128', $this->document->saveHtml()));
        }
        if (!$this->containsElement($event->target)) {
            throw new \InvalidArgumentException('The event target must belong to this document.');
        }
        $path = [];
        for ($element = $event->target; null !== $element; $element = $this->composedParent($element)) {
            $path[] = $element;
        }
        $path[] = null;
        foreach ($path as $element) {
            foreach ($this->listeners[$event->type] ?? [] as [$target, $listener]) {
                if ($target === $element) {
                    $listener($event);
                }
            }
            if ($event->isPropagationStopped()) {
                break;
            }
        }
    }

    public function focus(?\Dom\Element $element): void
    {
        if (null !== $this->shadowView) {
            $this->refreshShadowView(hash('xxh128', $this->document->saveHtml()));
        }
        if (null !== $element && (!$this->containsElement($element) || (ControlState::isDisabled($element) || $this->isBlocked($element)))) {
            throw new \InvalidArgumentException('The focused element must be connected, enabled and interactive in this document.');
        }
        if ($element !== $this->focused) {
            $previous = $this->focused;
            if (null !== $previous && isset($this->embedded[$previous])) {
                $this->embedded[$previous]['session']->focus(null);
            }
            if (null !== $previous && $this->containsElement($previous) && 'select' === $previous->localName) {
                $this->hidePicker($previous);
            }
            $this->focused = $element;
            if (null !== $element && 'select' === $element->localName && SelectOptions::isListBox($element)) {
                $options = SelectOptions::options($element);
                if (!\in_array($this->pickerOptions[$element] ?? null, $options, true)) {
                    $active = $this->selectedOptions($element)[0] ?? ($options[0] ?? null);
                    if (null !== $active) {
                        $this->pickerOptions[$element] = $active;
                    }
                }
            }
            $this->invalidate();
            if (null !== $previous && $this->containsElement($previous)) {
                $this->dispatch(new DocumentEvent('blur', $previous));
            }
            if (null !== $element && $this->containsElement($element)) {
                $this->dispatch(new DocumentEvent('focus', $element));
                if ($this->focused === $element && $this->containsElement($element)) {
                    $this->scrollIntoView($element);
                }
            }
        }
        $this->updateInterest();
    }

    public function getFocusedElement(): ?\Dom\Element
    {
        $this->pruneLayers();
        if (null !== $this->focused && (!$this->containsElement($this->focused) || (ControlState::isDisabled($this->focused) || $this->isBlocked($this->focused)))) {
            $this->focus(null);
        }

        return $this->focused;
    }

    public function focusNext(bool $backward = false, bool $wrap = true): bool
    {
        $wrap = $wrap || null !== $this->activeModal();
        $focused = $this->getFocusedElement();
        if (null !== $focused && isset($this->embedded[$focused]) && $this->embedded[$focused]['session']->focusNext($backward, false)) {
            return true;
        }
        $elements = [];
        $result = $this->render();
        foreach ($this->elements('input, textarea, select, button, a[href], [tabindex], summary, details, iframe') as $element) {
            if ('details' === $element->localName && null !== $this->summary($element) || 'summary' === $element->localName && ('details' !== $element->parentElement?->localName || $this->summary($element->parentElement) !== $element)) {
                continue;
            }
            $tabIndex = $element->getAttribute('tabindex');
            $hidden = 'hidden' === ($result->getComputedStyle($element)['visibility'] ?? '');
            for ($ancestor = $element; null !== $ancestor; $ancestor = $this->composedParent($ancestor)) {
                $style = $result->getComputedStyle($ancestor);
                if ('none' === ($style['display'] ?? '')) {
                    $hidden = true;
                    break;
                }
            }
            if (!$hidden && $result->getBounds($element) && !(ControlState::isDisabled($element) || $this->isBlocked($element)) && !(null !== $tabIndex && is_numeric($tabIndex) && (int) $tabIndex < 0) && 'hidden' !== $element->getAttribute('type')) {
                // Early native DOM versions can return the same element for multiple selector branches.
                $elements[spl_object_id($element)] = $element;
            }
        }
        usort($elements, static function (\Dom\Element $a, \Dom\Element $b): int {
            $left = max(0, (int) $a->getAttribute('tabindex'));
            $right = max(0, (int) $b->getAttribute('tabindex'));

            return (0 === $left ? \PHP_INT_MAX : $left) <=> (0 === $right ? \PHP_INT_MAX : $right);
        });
        if (!$elements) {
            $this->focus(null);

            return false;
        }
        $index = array_search($this->getFocusedElement(), $elements, true);
        $next = false === $index ? ($backward ? \count($elements) - 1 : 0) : $index + ($backward ? -1 : 1);
        if (!$wrap && ($next < 0 || $next >= \count($elements))) {
            return false;
        }
        $target = $elements[($next + \count($elements)) % \count($elements)];
        $this->focus($target);
        if (isset($this->embedded[$target])) {
            $child = $this->embedded[$target]['session'];
            $child->focus(null);
            $child->focusNext($backward, false);
        }

        return true;
    }

    public function isAnimating(): bool
    {
        if ($this->timeline->hasPendingFrames($this->time)) {
            return true;
        }
        foreach ($this->embedded as $frame) {
            if ($this->embedded[$frame]['session']->isAnimating()) {
                return true;
            }
        }

        return false;
    }

    public function select(\Dom\Element $select, array $values, bool $dispatch = true): void
    {
        if (!$this->containsElement($select) || 'select' !== $select->localName || !array_all($values, static fn ($value): bool => \is_string($value))) {
            throw new \InvalidArgumentException('Selection requires a select element and string values.');
        }
        if ($dispatch && ControlState::isDisabled($select)) {
            return;
        }
        $this->setSelectedOptions($select, SelectOptions::selected($select, $values), $dispatch);
    }

    public function getSelectedValues(\Dom\Element $select): array
    {
        if (!$this->containsElement($select) || 'select' !== $select->localName) {
            throw new \InvalidArgumentException('The selection target must be a select in this document.');
        }

        return array_map(SelectOptions::value(...), $this->selectedOptions($select));
    }

    public function isChecked(\Dom\Element $element): bool
    {
        if (!$this->containsElement($element)) {
            throw new \InvalidArgumentException('The control must belong to this document.');
        }

        return $this->checked[$element] ?? $element->hasAttribute('checked');
    }

    public function getValue(\Dom\Element $element): string
    {
        if (!$this->containsElement($element)) {
            throw new \InvalidArgumentException('The control must belong to this document.');
        }

        if ('select' === $element->localName) {
            return $this->getSelectedValues($element)[0] ?? '';
        }

        $value = $this->values[$element] ?? ControlValue::initial($element);
        if ('input' === $element->localName && 'range' === strtolower($element->getAttribute('type') ?? 'text')) {
            $model = NumericControl::range($element, $value);

            return NumberStepper::format($model['value'], $model['step'], $model['min']);
        }

        return $value;
    }

    /**
     * Overrides native datalist suggestions; null restores the document's list.
     *
     * @param list<string>|null $suggestions
     */
    public function setSuggestions(\Dom\Element $input, ?array $suggestions): void
    {
        if (!$this->containsElement($input) || 'input' !== $input->localName) {
            throw new \InvalidArgumentException('The suggestion target must be an input in this document.');
        }
        if (null !== $suggestions) {
            if (\count($suggestions) > 2000) {
                throw new \LengthException('An input cannot retain more than 2000 suggestions.');
            }
            $bytes = 0;
            foreach ($suggestions as $suggestion) {
                if (!\is_string($suggestion) || 1 !== preg_match('//u', $suggestion)) {
                    throw new \InvalidArgumentException('Suggestions must be valid UTF-8 strings.');
                }
                if (\strlen($suggestion) > $this->maxTextBytes - $bytes) {
                    throw new \LengthException('Suggestions exceed the configured text size limit.');
                }
                $bytes += \strlen($suggestion);
            }
            $suggestions = array_values($suggestions);
        }
        if ($suggestions === ($this->suggestions[$input] ?? null)) {
            return;
        }
        if (null === $suggestions) {
            unset($this->suggestions[$input]);
        } else {
            $this->suggestions[$input] = $suggestions;
        }
        $this->invalidate();
    }

    public function getCompletion(\Dom\Element $input): ?string
    {
        if ($input->ownerDocument !== $this->document || 'input' !== $input->localName) {
            throw new \InvalidArgumentException('The completion target must be an input in this document.');
        }

        return Form\Datalist::completion($input, $this->getValue($input), $this->suggestions[$input] ?? null);
    }

    private function activeCompletion(\Dom\Element $input): ?string
    {
        if ('input' !== $input->localName || null === $completion = $this->getCompletion($input)) {
            return null;
        }
        $value = $this->getValue($input);
        $maxLength = trim($input->getAttribute('maxlength') ?? '');
        if (ctype_digit($maxLength) && mb_strlen($value) >= (int) $maxLength || \strlen($value) + \strlen($completion) > $this->maxTextBytes) {
            return null;
        }
        [$anchor, $caret] = $this->editor($input)->getSelection();

        return $anchor === $caret && $caret === preg_match_all('/\\X/u', $this->getValue($input)) ? $completion : null;
    }

    public function input(string $text): void
    {
        $element = $this->getFocusedElement();
        if (null === $element || !\in_array($element->localName, ['input', 'textarea'], true) || $element->hasAttribute('readonly')) {
            return;
        }
        if ('input' === $element->localName && \in_array(strtolower($element->getAttribute('type') ?? 'text'), ['checkbox', 'radio', 'button', 'submit', 'reset', 'image'], true)) {
            return;
        }
        $this->setValue($element, $text, true);
    }

    public function setValue(\Dom\Element $element, string $text, bool $dispatch = false): void
    {
        if (!$this->containsElement($element) || !\in_array($element->localName, ['input', 'textarea'], true) || 'input' === $element->localName && \in_array(strtolower($element->getAttribute('type') ?? 'text'), ['checkbox', 'radio', 'button', 'submit', 'reset', 'image'], true)) {
            throw new \InvalidArgumentException('The value target must be a text-value input or textarea in this document.');
        }
        if (\strlen($text) > $this->maxTextBytes) {
            throw new \LengthException('The edited value exceeds the configured size limit.');
        }
        if (1 !== preg_match('//u', $text)) {
            throw new \InvalidArgumentException('The edited value must be valid UTF-8.');
        }
        if ('input' === $element->localName && 'range' === strtolower($element->getAttribute('type') ?? 'text')) {
            $model = NumericControl::range($element, $text);
            $text = NumberStepper::format($model['value'], $model['step'], $model['min']);
        }
        $changed = $this->getValue($element) !== $text;
        $this->values[$element] = $text;
        if ($changed) {
            unset($this->editors[$element]);
            $this->invalidate();
        }
        if ($dispatch) {
            $this->interacted[$element] = true;
            $this->invalidate();
            $this->dispatch(new DocumentEvent('input', $element, ['value' => $text]));
        }
    }

    public function setChecked(\Dom\Element $element, bool $checked, bool $dispatch = false): void
    {
        $type = strtolower($element->getAttribute('type') ?? 'text');
        if (!$this->containsElement($element) || 'input' !== $element->localName || !\in_array($type, ['checkbox', 'radio'], true)) {
            throw new \InvalidArgumentException('The checked target must be a checkbox or radio input in this document.');
        }
        if ($dispatch && (ControlState::isDisabled($element) || $this->isBlocked($element))) {
            return;
        }
        if ($checked && 'radio' === $type && '' !== $name = $element->getAttribute('name') ?? '') {
            $owner = ControlState::formOwner($element);
            foreach ($this->elements('input') as $radio) {
                if ($radio !== $element && 'radio' === strtolower($radio->getAttribute('type') ?? '') && $radio->getAttribute('name') === $name && ControlState::formOwner($radio) === $owner && TreeScope::root($radio) === TreeScope::root($element)) {
                    $this->checked[$radio] = false;
                }
            }
        }
        $this->checked[$element] = $checked;
        $this->invalidate();
        if ($dispatch) {
            $this->interacted[$element] = true;
            $this->dispatch(new DocumentEvent('change', $element));
        }
    }

    public function getTextSelection(\Dom\Element $element): array
    {
        return $this->editor($element)->getSelection();
    }

    public function setTextSelection(\Dom\Element $element, int $anchor, int $focus): void
    {
        $editor = $this->editor($element);
        $before = $editor->getSelection();
        $editor->setSelection($anchor, $focus);
        if ($before === $editor->getSelection()) {
            return;
        }
        $this->invalidate();
        $this->dispatch(new DocumentEvent('selectionchange', $element));
    }

    public function key(KeyboardEvent $key): ?FormSubmission
    {
        $submission = $this->processKey($key, true);

        return false === $submission ? null : $submission;
    }

    /** Returns false when focus navigation reaches the document boundary. */
    public function tab(bool $backward = false, bool $wrap = true): bool
    {
        return false !== $this->processKey(new KeyboardEvent('Tab', null, $backward), $wrap);
    }

    /** Returns false when the document did not consume the key. */
    private function processKey(KeyboardEvent $key, bool $wrapFocus): FormSubmission|false|null
    {
        $element = $this->getFocusedElement();
        if (null === $element) {
            if ('Escape' === $key->key) {
                $this->dismissLayer();
            }
            if ('Tab' === $key->key) {
                return $this->focusNext($key->shift, $wrapFocus) ? null : false;
            } elseif (\in_array($key->key, ['PageUp', 'PageDown', 'ArrowLeft', 'ArrowRight'], true)) {
                return $this->scrollKeyboard($key) ? null : false;
            }

            return null;
        }
        if (isset($this->embedded[$element])) {
            $child = $this->embedded[$element]['session'];
            if ('Tab' === $key->key) {
                return $child->tab($key->shift, false) || $this->focusNext($key->shift, $wrapFocus) ? null : false;
            }

            $submission = $child->processKey($key, true);
            if (false !== $submission) {
                return $submission;
            }

            return $this->scrollKeyboard($key, $element) ? null : false;
        }
        $this->dispatch($event = new DocumentEvent('keydown', $element, ['key' => $key]));
        if ($event->isDefaultPrevented() || $this->getFocusedElement() !== $element || !$this->containsElement($element) || (ControlState::isDisabled($element) || $this->isBlocked($element))) {
            return null;
        }
        if ('Escape' === $key->key && $this->dismissLayer()) {
            return null;
        }
        if (\in_array($key->key, ['Tab', 'ArrowRight'], true) && !$key->shift && !$key->control && !$key->alt && !$key->meta && !$element->hasAttribute('readonly') && null !== $completion = $this->activeCompletion($element)) {
            $key = new KeyboardEvent('', $completion);
        }
        if ('Tab' === $key->key) {
            return $this->focusNext($key->shift, $wrapFocus) ? null : false;
        }
        $type = strtolower($element->getAttribute('type') ?? 'text');
        if ('select' === $element->localName && !SelectOptions::isListBox($element)) {
            $open = \in_array($element, $this->topLayers, true);
            if (\in_array($key->key, ['Enter', 'Space', ' '], true)) {
                if ($open) {
                    if (null !== $option = $this->pickerOptions[$element] ?? null) {
                        $this->setSelectedOptions($element, [$option]);
                    }
                    if (!$this->containsElement($element)) {
                        return null;
                    }
                    $this->hidePicker($element);
                } else {
                    $this->showPicker($element);
                }

                return null;
            }
            if (!$open && 'ArrowDown' === $key->key) {
                $this->showPicker($element);

                return null;
            }
            if ($open && \in_array($key->key, ['ArrowUp', 'ArrowDown', 'Home', 'End', 'PageUp', 'PageDown', 'ArrowLeft', 'ArrowRight'], true)) {
                $options = SelectOptions::options($element);
                if ($options) {
                    $index = array_search($this->pickerOptions[$element] ?? null, $options, true);
                    $index = false === $index ? 0 : $index;
                    $index = match ($key->key) {
                        'Home' => 0,
                        'End' => \count($options) - 1,
                        'ArrowUp' => ($index - 1 + \count($options)) % \count($options),
                        'PageUp', 'ArrowLeft' => $this->pageOptionIndex($element, $options, $index, true, true),
                        'PageDown', 'ArrowRight' => $this->pageOptionIndex($element, $options, $index, false, true),
                        default => ($index + 1) % \count($options),
                    };
                    $this->pickerOptions[$element] = $options[$index];
                    $this->invalidate();
                    $this->revealPickerOption($element, $options[$index]);
                }

                return null;
            }
        }
        if ('select' === $element->localName && $element->hasAttribute('multiple') && \in_array($key->key, ['Space', ' '], true)) {
            $options = SelectOptions::options($element);
            $active = $this->pickerOptions[$element] ?? ($this->selectedOptions($element)[0] ?? ($options[0] ?? null));
            if (null !== $active && \in_array($active, $options, true)) {
                $this->activate($active);
            }

            return null;
        }
        if ('Enter' === $key->key && 'textarea' !== $element->localName) {
            if (\in_array($element->localName, ['button', 'a', 'summary', 'details'], true) || \in_array($type, ['submit', 'image'], true)) {
                return $this->activate($element);
            }
            if (\in_array($element->localName, ['input', 'select'], true) && null !== $form = ControlState::formOwner($element)) {
                return $this->submit($form);
            }
        }
        if (\in_array($key->key, ['Space', ' '], true) && (\in_array($element->localName, ['button', 'summary', 'details'], true) || 'input' === $element->localName && \in_array($type, ['checkbox', 'radio', 'button', 'submit', 'reset'], true))) {
            return $this->activate($element);
        }
        if ('select' === $element->localName && (\in_array($key->key, ['ArrowUp', 'ArrowDown', 'Home', 'End'], true) || SelectOptions::isListBox($element) && \in_array($key->key, ['PageUp', 'PageDown', 'ArrowLeft', 'ArrowRight'], true))) {
            $options = SelectOptions::options($element);
            if ($options) {
                $index = array_search($this->pickerOptions[$element] ?? ($this->selectedOptions($element)[0] ?? null), $options, true);
                $index = false === $index ? 0 : $index;
                $index = match ($key->key) {
                    'Home' => 0,
                    'End' => \count($options) - 1,
                    'ArrowUp' => max(0, $index - 1),
                    'PageUp', 'ArrowLeft' => $this->pageOptionIndex($element, $options, $index, true),
                    'PageDown', 'ArrowRight' => $this->pageOptionIndex($element, $options, $index, false),
                    default => min(\count($options) - 1, $index + 1),
                };
                $this->pickerOptions[$element] = $options[$index];
                $this->invalidate();
                if (!$element->hasAttribute('multiple') || $key->shift) {
                    $selected = $element->hasAttribute('multiple') && $key->shift ? $this->selectedOptions($element) : [];
                    if (!\in_array($options[$index], $selected, true)) {
                        $selected[] = $options[$index];
                    }
                    $this->setSelectedOptions($element, $selected);
                }
                if ($this->containsElement($element) && $this->containsElement($options[$index]) && $element->contains($options[$index])) {
                    $this->scrollIntoView($options[$index]);
                }
            }

            return null;
        }
        if ('input' === $element->localName && 'range' === $type && !$element->hasAttribute('readonly') && \in_array($key->key, ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'PageUp', 'PageDown'], true)) {
            $model = NumericControl::range($element, $this->getValue($element));
            $direction = \in_array($key->key, ['ArrowDown', 'ArrowLeft', 'PageDown'], true) ? -1 : 1;
            if (\in_array($key->key, ['ArrowLeft', 'ArrowRight'], true) && 'rtl' === ($this->render()->getComputedStyle($element)['direction'] ?? 'ltr')) {
                $direction *= -1;
            }
            $amount = \in_array($key->key, ['PageUp', 'PageDown'], true) ? max($model['step'], $model['max'] / 10 - $model['min'] / 10) : $model['step'];
            $value = match ($key->key) {
                'Home' => $model['min'],
                'End' => $model['max'],
                default => $model['value'] + $direction * $amount,
            };
            if (is_finite($value)) {
                $value = NumberStepper::constrain($value, $model['min'], $model['max'], $model['step']);
                if ($value !== $model['value']) {
                    $this->input(NumberStepper::format($value, $model['step'], $model['min']));
                    if ($this->containsElement($element) && !ControlState::isDisabled($element) && !$this->isBlocked($element) && !$element->hasAttribute('readonly') && 'range' === strtolower($element->getAttribute('type') ?? '')) {
                        $this->dispatch(new DocumentEvent('change', $element));
                    }
                }
            }

            return null;
        }
        if ('input' === $element->localName && 'number' === $type && \in_array($key->key, ['ArrowUp', 'ArrowDown'], true) && !$element->hasAttribute('readonly')) {
            $min = is_numeric($element->getAttribute('min')) ? (float) $element->getAttribute('min') : null;
            $max = is_numeric($element->getAttribute('max')) ? (float) $element->getAttribute('max') : null;
            $original = $this->getValue($element);
            $current = is_numeric($original) ? (float) $original : 0.0;
            $step = is_numeric($element->getAttribute('step')) && (float) $element->getAttribute('step') > 0 ? (float) $element->getAttribute('step') : 1.0;
            $base = $min ?? (is_numeric($element->getAttribute('value')) ? (float) $element->getAttribute('value') : 0.0);
            $value = NumberStepper::next($current, $step, $min, $max, 'ArrowUp' === $key->key ? 1 : -1, $base);
            if (null !== $value && $value !== $current) {
                $this->input(NumberStepper::format($value, $step, $base));
                $this->dispatch(new DocumentEvent('change', $element));
            }

            return null;
        }
        if ('input' === $element->localName && 'radio' === $type && \in_array($key->key, ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'], true)) {
            $group = [];
            foreach ($this->elements('input') as $radio) {
                if ('radio' === strtolower($radio->getAttribute('type') ?? '') && $radio->getAttribute('name') === $element->getAttribute('name') && ControlState::formOwner($radio) === ControlState::formOwner($element) && TreeScope::root($radio) === TreeScope::root($element) && !ControlState::isDisabled($radio)) {
                    $group[] = $radio;
                }
            }
            if ($group) {
                $index = array_search($element, $group, true);
                $index = ((false === $index ? 0 : $index) + (\in_array($key->key, ['ArrowLeft', 'ArrowUp'], true) ? -1 : 1) + \count($group)) % \count($group);
                $this->activate($group[$index]);
            }

            return null;
        }
        if ('textarea' === $element->localName && \in_array($key->key, ['PageUp', 'PageDown'], true) && !$key->control && !$key->alt && !$key->meta) {
            $editor = $this->editor($element);
            $before = $editor->getSelection();
            $result = $this->render();
            $height = $result->getScrollGeometry($element)?->port->height ?? ($result->getBounds($element)[0]->height ?? 0.0);
            $fontMetrics = $this->context->textMeasurer->measure('M', $this->context->defaultFont);
            $row = $fontMetrics->ascent + $fontMetrics->descent;
            $pages = $row > 0 ? $height / $row : 1.0;
            $lines = $pages >= \PHP_INT_MAX ? \PHP_INT_MAX : max(1, (int) floor($pages));
            $editor->moveLines('PageUp' === $key->key ? -$lines : $lines, $key->shift);
            if ($before !== $editor->getSelection()) {
                $this->invalidate();
                if ($this->containsElement($element)) {
                    $this->dispatch(new DocumentEvent('selectionchange', $element));
                }
            }
            if ($this->containsElement($element) && !$this->isBlocked($element)) {
                $this->revealCaret($element);
            }

            return null;
        }
        if ('textarea' === $element->localName || 'input' === $element->localName && !\in_array($type, ['hidden', 'checkbox', 'radio', 'button', 'submit', 'reset', 'image', 'range'], true)) {
            $editor = $this->editor($element);
            $before = $editor->getSelection();
            $maxLength = trim($element->getAttribute('maxlength') ?? '');
            $edited = $editor->key($key, 'textarea' === $element->localName, $element->hasAttribute('readonly'), ctype_digit($maxLength) ? (int) $maxLength : null);
            if ($edited) {
                $this->invalidate();
                $this->interacted[$element] = true;
                $this->values[$element] = $editor->getText();
                $this->dispatch(new DocumentEvent('input', $element, ['value' => $editor->getText()]));
            }
            if ($before !== $editor->getSelection()) {
                $this->invalidate();
                if ($this->containsElement($element)) {
                    $this->dispatch(new DocumentEvent('selectionchange', $element));
                }
            }
            if (($edited || $before !== $editor->getSelection()) && $this->containsElement($element) && $this->getFocusedElement() === $element && !$this->isBlocked($element)) {
                $this->revealCaret($element);
            }
            if (\in_array($key->key, ['ArrowLeft', 'ArrowRight'], true)) {
                return null;
            }
        }
        if (\in_array($key->key, ['PageUp', 'PageDown', 'ArrowLeft', 'ArrowRight'], true) && !('input' === $element->localName && 'range' === $type)) {
            return $this->scrollKeyboard($key, $element) ? null : false;
        }

        return null;
    }

    public function pointer(float $x, float $y, bool $activate = false): ?\Dom\Element
    {
        $hit = $this->render()->getHitWitness($x, $y);
        $element = $hit['element'] ?? null;
        $this->updatePointer($element);
        if ($activate && null !== $element) {
            if (null !== ($hit['control'] ?? null)) {
                $this->activateScrollControl($hit['control']);
            } elseif (null === ($hit['axis'] ?? null)) {
                $this->activate($element, $x, $y);
            }
        }

        return $element;
    }

    /** Returns whether at least one document scroll offset changed. */
    public function handleWheel(WheelEvent $wheel): bool
    {
        return $this->wheel($wheel)[0];
    }

    /** @return array{bool, float, float} Changed offsets and unconsumed axis deltas. */
    private function wheel(WheelEvent $wheel): array
    {
        if (0.0 === $wheel->deltaX && 0.0 === $wheel->deltaY || $wheel->x < 0 || $wheel->y < 0 || $wheel->x >= $this->context->width || $wheel->y >= $this->context->height) {
            return [false, 0.0, 0.0];
        }
        $result = $this->render();
        $target = $result->hitTest($wheel->x, $wheel->y);
        if (null === $target || $this->isBlocked($target)) {
            return [false, $wheel->deltaX, $wheel->deltaY];
        }
        $changed = false;
        $deltaX = $wheel->deltaX;
        $deltaY = $wheel->deltaY;
        if (isset($this->embedded[$target]) && null !== $point = $result->getContentPoint($target, $wheel->x, $wheel->y)) {
            [$changed, $deltaX, $deltaY] = $this->embedded[$target]['session']->wheel(new WheelEvent($point[0], $point[1], $deltaX, $deltaY));
        } else {
            $this->dispatch($event = new DocumentEvent('wheel', $target, ['wheel' => $wheel]));
            if ($event->isDefaultPrevented() || !$this->containsElement($target) || $this->isBlocked($target)) {
                return [false, 0.0, 0.0];
            }
        }
        [$scrolled, $deltaX, $deltaY] = $this->scrollChain($target, $deltaX, $deltaY);

        return [$changed || $scrolled, $deltaX, $deltaY];
    }

    private function scrollKeyboard(KeyboardEvent $key, ?\Dom\Element $target = null): bool
    {
        if ($key->control || $key->alt || $key->meta || $key->shift || !\in_array($key->key, ['PageUp', 'PageDown', 'ArrowLeft', 'ArrowRight'], true)) {
            return false;
        }
        $vertical = \in_array($key->key, ['PageUp', 'PageDown'], true);
        $direction = \in_array($key->key, ['PageUp', 'ArrowLeft'], true) ? -1.0 : 1.0;
        if (null === $target) {
            $result = $this->render();
            foreach ($this->elements('*') as $element) {
                $style = $result->getComputedStyle($element);
                $axis = $vertical ? 'y' : 'x';
                if (null !== $result->getScrollGeometry($element) && \in_array($style['overflow-'.$axis] ?? $style['overflow'] ?? 'visible', ['auto', 'scroll'], true) && 'hidden' !== ($style['visibility'] ?? 'visible') && !$this->isBlocked($element)) {
                    $target = $element;
                    break;
                }
            }
        }

        if (null === $target) {
            return false;
        }
        [$changed, $remainingX, $remainingY] = $this->scrollChain($target, $vertical ? 0.0 : $direction, $vertical ? $direction : 0.0, true);

        return $changed || 0.0 === ($vertical ? $remainingY : $remainingX);
    }

    private function pageOptionIndex(\Dom\Element $select, array $options, int $index, bool $backward, bool $picker = false): int
    {
        $result = $this->render();
        $geometry = $result->getScrollGeometry($select, $picker ? 'picker' : null);
        $visible = [];
        if (null !== $geometry) {
            foreach ($options as $position => $option) {
                $bounds = $result->getBounds($option)[0] ?? null;
                if (null !== $bounds && $bounds->y >= $geometry->port->y - 1e-9 && $bounds->y + $bounds->height <= $geometry->port->y + $geometry->port->height + 1e-9) {
                    $visible[] = $position;
                }
            }
        }
        $next = $visible ? ($backward ? min($visible) - 1 : max($visible) + 1) : $index + ($backward ? -1 : 1);

        return max(0, min(\count($options) - 1, $next));
    }

    private function revealCaret(\Dom\Element $element): void
    {
        for ($container = $element; null !== $container; $container = $this->composedParent($container)) {
            if (!$this->containsElement($element) || !$this->containsElement($container)) {
                break;
            }
            if (null !== $offset = $this->render()->getScrollOffsetToReveal($element, $container, true)) {
                $this->scroll($container, $offset[0], $offset[1]);
            }
        }
    }

    /** @return array{bool, float, float} */
    private function scrollChain(\Dom\Element $target, float $deltaX, float $deltaY, bool $keyboard = false): array
    {
        $changed = false;
        for ($container = $target; null !== $container && (0.0 !== $deltaX || 0.0 !== $deltaY); $container = $this->composedParent($container)) {
            if (!$this->containsElement($container) || $this->isBlocked($container)) {
                break;
            }
            $result = $this->render();
            $geometry = $result->getScrollGeometry($container);
            if (null === $geometry) {
                continue;
            }
            $style = $result->getComputedStyle($container);
            $offset = [$geometry->offsetX, $geometry->offsetY];
            $before = $offset;
            foreach (['x' => &$deltaX, 'y' => &$deltaY] as $axis => &$delta) {
                if (0.0 === $delta || ('x' === $axis ? $geometry->port->width : $geometry->port->height) <= 0 || !\in_array($style['overflow-'.$axis] ?? $style['overflow'] ?? 'visible', ['auto', 'scroll'], true)) {
                    continue;
                }
                $index = 'x' === $axis ? 0 : 1;
                $maximum = 'x' === $axis ? $geometry->maxX : $geometry->maxY;
                $amount = $keyboard ? $delta * $this->keyboardScrollStep($geometry, 'y' === $axis) : $delta;
                $offset[$index] = max(0.0, min($maximum, $offset[$index] + $amount));
                if ($offset[$index] !== $before[$index] || \in_array($style['overscroll-behavior-'.$axis] ?? $style['overscroll-behavior'] ?? 'auto', ['contain', 'none'], true)) {
                    $delta = 0.0;
                }
            }
            unset($delta);
            if ($offset !== $before) {
                $this->scroll($container, $offset[0], $offset[1]);
                $changed = true;
            }
        }

        return [$changed, $deltaX, $deltaY];
    }

    private function keyboardScrollStep(ScrollGeometry $geometry, bool $vertical): float
    {
        $metrics = $this->context->textMeasurer->measure('M', $this->context->defaultFont);
        $unit = $vertical ? $metrics->ascent + $metrics->descent : $metrics->width;
        $extent = $vertical ? $geometry->port->height : $geometry->port->width;
        $step = $vertical ? max($unit, $extent - $unit) : max($unit, min(4 * $unit, $extent / 4));

        return max(0.0, $this->context->layoutMetrics?->constrainLength($step, 'keyboard-scroll-step', $vertical) ?? $step);
    }

    public function handlePointer(PointerEvent $pointer): ?FormSubmission
    {
        $hit = $this->render()->getHitWitness($pointer->x, $pointer->y);
        $scrollbar = null !== ($hit['axis'] ?? null);
        $control = $hit['control'] ?? null;
        $element = $hit['element'] ?? null;
        $this->updatePointer($element);
        if ('down' === $pointer->type) {
            $this->pressedScrollControl = null;
            $this->pressed = $this->selectionTarget = $this->rangeTarget = null;
            $this->invalidate();
            if (0 === $pointer->button && $this->dismissOutside($element)) {
                return null;
            }
        }
        $target = $element ?? $this->document->documentElement;
        $this->dispatch($event = new DocumentEvent('pointer'.$pointer->type, $target, ['pointer' => $pointer]));
        if ($event->isDefaultPrevented() || 0 !== $pointer->button) {
            if ('up' === $pointer->type) {
                $this->pressedScrollControl = null;
                $this->pressed = $this->selectionTarget = $this->rangeTarget = null;
            }

            return null;
        }
        if (null !== $control || null !== $this->pressedScrollControl) {
            $this->pressed = $this->selectionTarget = $this->rangeTarget = $this->embeddedPointer = null;
            if ('down' === $pointer->type && null !== $control && !$control['disabled'] && $this->containsElement($control['element'])) {
                $this->pressedScrollControl = $control;
            } elseif ('up' === $pointer->type) {
                $pressed = $this->pressedScrollControl;
                $this->pressedScrollControl = null;
                if (null !== $control && null !== $pressed && $pressed['element'] === $control['element'] && $pressed['action'] === $control['action'] && ($pressed['target'] ?? null) === ($control['target'] ?? null)) {
                    $this->activateScrollControl($control);
                }
            }

            return null;
        }
        if ($scrollbar && 'move' !== $pointer->type) {
            $this->pressed = $this->selectionTarget = $this->rangeTarget = $this->embeddedPointer = null;
            $this->invalidate();

            return null;
        }
        $frame = $this->embeddedPointer ?? (null !== $element && isset($this->embedded[$element]) ? $element : null);
        if (null !== $frame && isset($this->embedded[$frame]) && $this->containsElement($frame) && $frame->ownerDocument === $this->document && !$this->isBlocked($frame)) {
            $point = $this->render()->getContentPoint($frame, $pointer->x, $pointer->y, null !== $this->embeddedPointer);
            if (null !== $point) {
                if ('down' === $pointer->type) {
                    $this->focus($frame);
                    $this->embeddedPointer = $frame;
                }
                if (!$this->containsElement($frame) || !isset($this->embedded[$frame]) || $this->isBlocked($frame)) {
                    $this->embeddedPointer = null;

                    return null;
                }
                $submission = $this->embedded[$frame]['session']->handlePointer(new PointerEvent($pointer->type, $point[0], $point[1], $pointer->button, $pointer->shift));
                if ('up' === $pointer->type) {
                    $this->embeddedPointer = null;
                }

                return $submission;
            }
        }
        if ('up' === $pointer->type) {
            $this->embeddedPointer = null;
        }
        if ('down' === $pointer->type && null !== $element && $this->containsElement($element) && !ControlState::isDisabled($this->activationTarget($element)) && !$this->isBlocked($element)) {
            $this->pressed = $element;
            $this->invalidate();
            $type = strtolower($element->getAttribute('type') ?? 'text');
            if ('input' === $element->localName && 'range' === $type && !$element->hasAttribute('readonly')) {
                $this->focus($element);
                $this->rangeTarget = $element;
                $this->rangeStartValue = $this->getValue($element);
                $this->rangeAt($element, $pointer->x);
            }
            if ('textarea' === $element->localName || 'input' === $element->localName && !\in_array($type, ['hidden', 'checkbox', 'radio', 'button', 'submit', 'reset', 'image', 'range'], true)) {
                $this->focus($element);
                $position = $this->render()->getTextPosition($element, $pointer->x, $pointer->y) ?? 0;
                $this->selectionAnchor = $pointer->shift ? $this->editor($element)->getSelection()[0] : $position;
                $this->selectionTarget = $element;
                $this->setTextSelection($element, $this->selectionAnchor, $position);
            }
        }
        if (\in_array($pointer->type, ['move', 'up'], true) && null !== $selection = $this->selectionTarget) {
            if ($this->containsElement($selection) && !ControlState::isDisabled($selection) && !$this->isBlocked($selection)) {
                $position = $this->render()->getTextPosition($selection, $pointer->x, $pointer->y, true);
                if (null !== $position) {
                    $this->setTextSelection($selection, $this->selectionAnchor, $position);
                }
            }
        }
        if (null !== $this->rangeTarget && \in_array($pointer->type, ['move', 'up'], true)) {
            $range = $this->rangeTarget;
            if ($this->containsElement($range) && !ControlState::isDisabled($range) && !$this->isBlocked($range) && !$range->hasAttribute('readonly')) {
                $this->rangeAt($range, $pointer->x);
                if ('up' === $pointer->type && $this->containsElement($range) && !ControlState::isDisabled($range) && !$this->isBlocked($range) && !$range->hasAttribute('readonly') && 'range' === strtolower($range->getAttribute('type') ?? '') && $this->rangeStartValue !== $this->getValue($range)) {
                    $this->dispatch(new DocumentEvent('change', $range));
                }
            }
        }
        if ('up' === $pointer->type) {
            $pressed = $this->pressed;
            $this->pressed = $this->selectionTarget = $this->rangeTarget = null;
            $this->invalidate();
            if (null !== $pressed && null !== $element) {
                $action = $this->activationTarget($pressed);
                if ($element === $pressed || $this->activationTarget($element) === $action) {
                    return $this->activate($element === $pressed ? $pressed : $action);
                }
            }
        }

        return null;
    }

    public function activate(\Dom\Element $element, ?float $x = null, ?float $y = null): ?FormSubmission
    {
        if (!$this->containsElement($element) || (ControlState::isDisabled($element) || $this->isBlocked($element))) {
            return null;
        }
        $origin = $element;
        $element = $this->activationTarget($element);
        if (ControlState::isDisabled($element) || $this->isBlocked($element)) {
            return null;
        }
        if ((null === $x) !== (null === $y) || null !== $x && (!is_finite($x) || !is_finite($y))) {
            throw new \InvalidArgumentException('Pointer activation requires two finite document coordinates.');
        }
        $type = strtolower($element->getAttribute('type') ?? 'text');
        if (null !== $x && ('textarea' === $element->localName || 'input' === $element->localName && !\in_array($type, ['hidden', 'checkbox', 'radio', 'button', 'submit', 'reset', 'image', 'range'], true))) {
            $this->focus($element);
            if ($this->getFocusedElement() === $element && $this->containsElement($element) && !(ControlState::isDisabled($element) || $this->isBlocked($element)) && null !== $position = $this->render()->getTextPosition($element, $x, $y)) {
                $this->setTextSelection($element, $position, $position);
            }
        }
        $this->dispatch($event = new DocumentEvent('click', $origin));
        if ($event->isDefaultPrevented() || !$this->containsElement($element) || (ControlState::isDisabled($element) || $this->isBlocked($element))) {
            return null;
        }
        if ('label' === $element->localName) {
            $id = $element->getAttribute('for');
            $control = null !== $id ? TreeScope::getElementById($element, $id) : $element->querySelector('button, input:not([type="hidden"]), select, textarea');
            if (null !== $control && \in_array($control->localName, ['button', 'input', 'select', 'textarea'], true)) {
                return $this->activate($control);
            }

            return null;
        }
        if ('option' === $element->localName && null !== $select = $element->closest('select')) {
            if (!ControlState::isDisabled($select) && !$this->isBlocked($select) && \in_array($element, SelectOptions::options($select), true)) {
                if (SelectOptions::isListBox($select)) {
                    $this->pickerOptions[$select] = $element;
                }
                $options = $this->selectedOptions($select);
                $options = $select->hasAttribute('multiple') ? (\in_array($element, $options, true) ? array_values(array_filter($options, static fn (\Dom\Element $option): bool => $option !== $element)) : [...$options, $element]) : [$element];
                $this->setSelectedOptions($select, $options);
                if (!$this->containsElement($select)) {
                    return null;
                }
                $this->hidePicker($select);
                if ($this->containsElement($select) && !ControlState::isDisabled($select) && !$this->isBlocked($select)) {
                    $this->focus($select);
                }
            }

            return null;
        }
        $this->focus($element);
        if ($this->getFocusedElement() !== $element || !$this->containsElement($element) || (ControlState::isDisabled($element) || $this->isBlocked($element))) {
            return null;
        }
        if ('select' === $element->localName && !SelectOptions::isListBox($element)) {
            \in_array($element, $this->topLayers, true) ? $this->hidePicker($element) : $this->showPicker($element);

            return null;
        }
        if (\in_array($element->localName, ['a', 'area'], true) && '' !== $href = $element->getAttribute('href') ?? '') {
            if (str_starts_with(trim($href), '#')) {
                $this->navigate($href);
                $this->markVisited($href);
            } else {
                $this->dispatch($event = new DocumentEvent('link', $element, ['href' => $href, 'target' => $element->getAttribute('target'), 'download' => $element->getAttribute('download')]));
                if (!$event->isDefaultPrevented()) {
                    $this->markVisited($href);
                }
            }

            return null;
        }
        $details = 'summary' === $element->localName && 'details' === $element->parentElement?->localName ? $element->parentElement : ('details' === $element->localName && null === $this->summary($element) ? $element : null);
        if (null !== $details && ('details' === $element->localName || $this->summary($details) === $element)) {
            if ($details->hasAttribute('open')) {
                $details->removeAttribute('open');
            } else {
                $name = $details->getAttribute('name');
                if (null !== $name && '' !== $name) {
                    foreach ($this->elements('details') as $other) {
                        if ($other !== $details && TreeScope::root($other) === TreeScope::root($details) && $other->getAttribute('name') === $name && $other->hasAttribute('open')) {
                            $other->removeAttribute('open');
                            $this->dispatch(new DocumentEvent('toggle', $other, ['open' => false]));
                        }
                    }
                }
                $details->setAttribute('open', '');
            }
            $this->dispatch(new DocumentEvent('toggle', $details, ['open' => $details->hasAttribute('open')]));

            return null;
        }
        $type = strtolower($element->getAttribute('type') ?? '');
        if ('input' === $element->localName && \in_array($type, ['checkbox', 'radio'], true)) {
            $this->setChecked($element, 'radio' === $type || !$this->isChecked($element), true);
        }
        if (\in_array($element->localName, ['button', 'input'], true) && $element->hasAttribute('commandfor')) {
            $this->invokeCommand($element);

            return null;
        }
        if (\in_array($element->localName, ['button', 'input'], true) && $element->hasAttribute('popovertarget')) {
            if (null !== $target = TreeScope::getElementById($element, $element->getAttribute('popovertarget'))) {
                if ($target->hasAttribute('popover')) {
                    match (strtolower($element->getAttribute('popovertargetaction') ?? 'toggle')) {
                        'show' => $this->showPopover($target),
                        'hide' => $this->hidePopover($target),
                        default => $this->togglePopover($target),
                    };
                }
            }

            return null;
        }
        if (\in_array($element->localName, ['button', 'input'], true) && 'reset' === $type && null !== $form = ControlState::formOwner($element)) {
            $this->reset($form);

            return null;
        }
        if (('button' === $element->localName && !\in_array($type, ['button', 'reset'], true) || 'input' === $element->localName && \in_array($type, ['submit', 'image'], true)) && null !== $form = ControlState::formOwner($element)) {
            return $this->submit($form, $element);
        }

        return null;
    }

    private function invokeCommand(\Dom\Element $source): void
    {
        $target = TreeScope::getElementById($source, $source->getAttribute('commandfor'));
        if (null === $target) {
            return;
        }
        $command = $source->getAttribute('command') ?? '';
        $custom = str_starts_with($command, '--');
        if (!$custom && !\in_array($command = strtolower($command), ['show-modal', 'close', 'request-close', 'show-popover', 'hide-popover', 'toggle-popover'], true)) {
            return;
        }
        $this->dispatch($event = new DocumentEvent('command', $target, ['command' => $command, 'source' => $source]));
        if ($custom || $event->isDefaultPrevented() || $event->isPropagationStopped() || !$this->containsElement($target) || !$this->containsElement($source) || ControlState::isDisabled($source) || $this->isBlocked($source)) {
            return;
        }
        if ('dialog' === $target->localName) {
            match ($command) {
                'show-modal' => $this->showModal($target),
                'close' => $this->close($target),
                'request-close' => $this->requestClose($target),
                default => null,
            };
        }
        if ($target->hasAttribute('popover')) {
            match ($command) {
                'show-popover' => $this->showPopover($target),
                'hide-popover' => $this->hidePopover($target),
                'toggle-popover' => $this->togglePopover($target),
                default => null,
            };
        }
    }

    public function navigate(string $fragment): bool
    {
        $fragment = ltrim(trim($fragment), '#');
        if (false !== $directive = strpos($fragment, ':~:')) {
            $fragment = substr($fragment, 0, $directive);
        }
        $id = rawurldecode($fragment);
        $target = '' === $id ? null : $this->document->getElementById($id);
        if ('' !== $id && null === $target && null !== $root = $this->document->documentElement) {
            Html\IdAttributes::mark($root);
            $target = $this->document->getElementById($id);
        }
        if ($this->target !== $target) {
            $this->target = $target;
            $this->invalidate();
        }
        if (null === $target) {
            return false;
        }
        $this->scrollIntoView($target);
        if (\in_array($target->localName, ['input', 'textarea', 'select', 'button', 'a'], true) || $target->hasAttribute('tabindex')) {
            if (!ControlState::isDisabled($target) && !$this->isBlocked($target)) {
                $this->focus($target);
            }
        }

        return true;
    }

    public function getTarget(): ?\Dom\Element
    {
        if (null !== $this->target && (!$this->containsElement($this->target))) {
            $this->target = null;
        }

        return $this->target;
    }

    public function setVisitedLinks(iterable $hrefs): void
    {
        $visited = [];
        foreach ($hrefs as $href) {
            if (!\is_string($href)) {
                throw new \InvalidArgumentException('Visited links must be strings.');
            }
            $visited[$href] = true;
        }
        if ($visited !== $this->visited) {
            $this->visited = $visited;
            $this->invalidate();
        }
    }

    public function markVisited(string $href): void
    {
        if (!isset($this->visited[$href])) {
            $this->visited[$href] = true;
            $this->invalidate();
        }
    }

    public function getVisitedLinks(): array
    {
        return array_map('strval', array_keys($this->visited));
    }

    /**
     * Scrolls enclosing ports just enough to reveal the element.
     */
    public function scrollIntoView(\Dom\Element $element): void
    {
        if (!$this->containsElement($element)) {
            throw new \InvalidArgumentException('The scroll target must be connected to this document.');
        }
        for ($container = $this->composedParent($element); null !== $container; $container = $this->composedParent($container)) {
            if (null !== $offset = $this->render()->getScrollOffsetToReveal($element, $container)) {
                $this->scroll($container, $offset[0], $offset[1]);
            }
        }
    }

    public function scroll(\Dom\Element $element, float $x, float $y): void
    {
        if ($element->ownerDocument !== $this->document || !is_finite($x) || !is_finite($y)) {
            throw new \InvalidArgumentException('Invalid scroll target or coordinates.');
        }
        $geometry = $this->render()->getScrollGeometry($element);
        $offset = null === $geometry ? [0.0, 0.0] : [max(0.0, min($geometry->maxX, $x)), max(0.0, min($geometry->maxY, $y))];
        $previous = $this->getScroll($element);
        if ($offset !== $previous) {
            $directions = $this->scrollDirections[$element] ?? [];
            if ($offset[0] !== $previous[0]) {
                $directions = array_values(array_diff($directions, ['left', 'right']));
                $directions[] = $offset[0] > $previous[0] ? 'right' : 'left';
            }
            if ($offset[1] !== $previous[1]) {
                $directions = array_values(array_diff($directions, ['top', 'bottom']));
                $directions[] = $offset[1] > $previous[1] ? 'bottom' : 'top';
            }
            $this->scrollDirections[$element] = $directions;
            $this->scroll[$element] = $offset;
            $this->invalidate();
            $this->dispatch(new DocumentEvent('scroll', $element, ['x' => $offset[0], 'y' => $offset[1]]));
        }
    }

    public function getScroll(\Dom\Element $element): array
    {
        if (!$this->containsElement($element)) {
            throw new \InvalidArgumentException('The scroll target must belong to this document.');
        }
        $geometry = $this->render()->getScrollGeometry($element);

        return null === $geometry ? [0.0, 0.0] : [$geometry->offsetX, $geometry->offsetY];
    }

    public function reset(\Dom\Element $form): void
    {
        if (!$this->containsElement($form) || 'form' !== $form->localName) {
            throw new \InvalidArgumentException('The reset target must be a form in this document.');
        }
        $this->dispatch($event = new DocumentEvent('reset', $form));
        if ($event->isDefaultPrevented()) {
            return;
        }
        foreach ($this->elements('input, textarea, select') as $element) {
            if (ControlState::formOwner($element) !== $form) {
                continue;
            }
            foreach ([$this->values, $this->checked, $this->selections, $this->editors, $this->interacted] as $storage) {
                unset($storage[$element]);
            }
        }
        $this->invalidate();
    }

    public function submit(\Dom\Element $form, ?\Dom\Element $submitter = null): ?FormSubmission
    {
        if (!$this->containsElement($form) || 'form' !== $form->localName) {
            throw new \InvalidArgumentException('The submission target must be a form in this document.');
        }
        if (null !== $submitter && (!$this->containsElement($submitter) || ControlState::formOwner($submitter) !== $form || ControlState::isDisabled($submitter) || !Submission::isSubmitButton($submitter))) {
            throw new \InvalidArgumentException('The submitter must be an enabled submit button associated with this form.');
        }
        foreach ($this->elements('input, textarea, select') as $element) {
            if (ControlState::formOwner($element) === $form && !ControlState::isDisabled($element)) {
                $this->interacted[$element] = true;
            }
        }
        $this->invalidate();
        $values = [];
        $radioValidity = ControlState::radioValidity($this->elements('input'), $this->isChecked(...));
        foreach ($this->elements('input, textarea, select, button') as $element) {
            $owner = ControlState::formOwner($element);
            if ($owner !== $form || ControlState::isDisabled($element)) {
                continue;
            }
            $type = \in_array($element->localName, ['input', 'button'], true) ? strtolower($element->getAttribute('type') ?? '') : '';
            if (\in_array($type, ['reset', 'button'], true) || Submission::isSubmitButton($element) && $element !== $submitter) {
                continue;
            }
            $value = $this->getValue($element);
            if (!$form->hasAttribute('novalidate') && !$submitter?->hasAttribute('formnovalidate')) {
                $valid = ConstraintValidator::control($element, $value, $this->isChecked($element), 'select' === $element->localName ? $this->getSelectedValues($element) : null, $radioValidity[$element] ?? null)[0];
                if (!$valid) {
                    return null;
                }
            }
            if (\in_array($type, ['checkbox', 'radio'])) {
                if (!$this->isChecked($element)) {
                    continue;
                }
                $value = $element->getAttribute('value') ?? 'on';
            }
            if ('select' === $element->localName) {
                $selected = $this->getSelectedValues($element);
                if (!$selected) {
                    continue;
                }
                $value = $element->hasAttribute('multiple') ? $selected : $selected[0];
            }
            foreach (Submission::values($element, $value, $submitter) as $name => $submitted) {
                if (isset($values[$name])) {
                    $values[$name] = [...(array) $values[$name], ...(array) $submitted];
                } else {
                    $values[$name] = $submitted;
                }
            }
        }

        [$action, $method] = Submission::settings($form, $submitter);
        $submission = new FormSubmission($form, $action, $method, $values);
        $this->dispatch($event = new DocumentEvent('submit', $form, ['submission' => $submission]));

        if ($event->isDefaultPrevented() || !$this->containsElement($form) || !$this->embeddedConnected()) {
            return null;
        }
        if ('dialog' === $submission->method) {
            if (null !== $dialog = $form->closest('dialog')) {
                $this->close($dialog, $submitter?->getAttribute('value') ?? '');
            }

            return null;
        }

        return $submission;
    }

    public function show(\Dom\Element $dialog): void
    {
        $this->openDialog($dialog, false);
    }

    public function showModal(\Dom\Element $dialog): void
    {
        $this->openDialog($dialog, true);
    }

    public function close(\Dom\Element $dialog, ?string $returnValue = null): void
    {
        $this->assertDialog($dialog);
        if (!$dialog->hasAttribute('open')) {
            return;
        }
        if (!$this->beforeToggle($dialog, false)) {
            return;
        }
        if (!$this->containsElement($dialog) || !$dialog->hasAttribute('open')) {
            return;
        }
        $previous = $this->modalFocus[$dialog] ?? null;
        unset($this->modalFocus[$dialog]);
        $this->removeLayer($dialog);
        $dialog->removeAttribute('open');
        if (null !== $returnValue) {
            $this->returnValues[$dialog] = $returnValue;
        }
        if (null !== $previous && $this->containsElement($previous) && !ControlState::isDisabled($previous) && !$this->isBlocked($previous)) {
            $this->focus($previous);
        } elseif (null !== $this->focused && $this->composedContains($dialog, $this->focused)) {
            $this->focus(null);
        }
        if (!$this->containsElement($dialog)) {
            return;
        }
        $this->dispatch(new DocumentEvent('toggle', $dialog, ['oldState' => 'open', 'newState' => 'closed']));
        if ($this->containsElement($dialog)) {
            $this->dispatch(new DocumentEvent('close', $dialog, ['returnValue' => $this->getReturnValue($dialog)]));
        }
    }

    public function requestClose(\Dom\Element $dialog, ?string $returnValue = null): void
    {
        $this->assertDialog($dialog);
        if (!$dialog->hasAttribute('open')) {
            return;
        }
        $this->dispatch($event = new DocumentEvent('cancel', $dialog));
        if (!$event->isDefaultPrevented() && $this->containsElement($dialog)) {
            $this->close($dialog, $returnValue);
        }
    }

    public function getReturnValue(\Dom\Element $dialog): string
    {
        $this->assertDialog($dialog);

        return $this->returnValues[$dialog] ?? '';
    }

    public function showPopover(\Dom\Element $popover): void
    {
        if (!$this->containsElement($popover) || !$popover->hasAttribute('popover')) {
            throw new \InvalidArgumentException('The popover must be a connected element with a popover attribute in this document.');
        }
        if (\in_array($popover, $this->topLayers, true)) {
            return;
        }
        if (!$this->beforeToggle($popover, true) || !$this->containsElement($popover) || !$popover->hasAttribute('popover') || \in_array($popover, $this->topLayers, true)) {
            return;
        }
        $mode = $this->popoverMode($popover);
        foreach ($this->topLayers as $open) {
            if ($open->hasAttribute('popover') && !isset($this->modalFocus[$open]) && !$this->composedContains($open, $popover) && ('auto' === $mode && \in_array($this->popoverMode($open), ['auto', 'hint'], true) || 'hint' === $mode && 'hint' === $this->popoverMode($open))) {
                $this->hidePopover($open);
            }
        }
        if (!$this->containsElement($popover) || !$popover->hasAttribute('popover') || \in_array($popover, $this->topLayers, true)) {
            return;
        }
        $this->topLayers[] = $popover;
        $this->invalidate();
        $this->dispatch(new DocumentEvent('toggle', $popover, ['oldState' => 'closed', 'newState' => 'open']));
    }

    public function hidePopover(\Dom\Element $popover): void
    {
        if (!$this->containsElement($popover)) {
            throw new \InvalidArgumentException('The popover must belong to this document.');
        }
        if (!$popover->hasAttribute('popover') || !\in_array($popover, $this->topLayers, true) || isset($this->modalFocus[$popover])) {
            return;
        }
        if (!$this->beforeToggle($popover, false)) {
            return;
        }
        if (!$this->containsElement($popover)) {
            $this->removeLayer($popover);

            return;
        }
        foreach ($this->topLayers as $open) {
            if ($open !== $popover && !isset($this->modalFocus[$open]) && $this->composedContains($popover, $open)) {
                'select' === $open->localName ? $this->hidePicker($open) : $this->hidePopover($open);
            }
        }
        $this->removeLayer($popover);
        if ($this->containsElement($popover)) {
            $this->dispatch(new DocumentEvent('toggle', $popover, ['oldState' => 'open', 'newState' => 'closed']));
        }
    }

    public function togglePopover(\Dom\Element $popover): void
    {
        \in_array($popover, $this->topLayers, true) ? $this->hidePopover($popover) : $this->showPopover($popover);
    }

    public function showPicker(\Dom\Element $select): void
    {
        if (!$this->containsElement($select) || 'select' !== $select->localName || SelectOptions::isListBox($select) || ControlState::isDisabled($select) || $this->isBlocked($select)) {
            throw new \InvalidArgumentException('The picker target must be an interactive single-select field in this document.');
        }
        if (\in_array($select, $this->topLayers, true)) {
            return;
        }
        foreach ($this->topLayers as $layer) {
            if ('select' === $layer->localName) {
                $this->hidePicker($layer);
            }
        }
        $options = SelectOptions::options($select);
        if ($options) {
            $this->pickerOptions[$select] = $this->selectedOptions($select)[0] ?? $options[0];
        }
        $this->pickerScroll[$select] = 0.0;
        $this->topLayers[] = $select;
        $this->invalidate();
        $this->focus($select);
        if ($this->getFocusedElement() !== $select || !$this->containsElement($select) || ControlState::isDisabled($select) || !\in_array($select, $this->topLayers, true)) {
            if ($select->ownerDocument === $this->document) {
                $this->hidePicker($select);
            }

            return;
        }
        if (null !== $option = $this->pickerOptions[$select] ?? null) {
            $this->revealPickerOption($select, $option);
        }
        $this->dispatch(new DocumentEvent('toggle', $select, ['oldState' => 'closed', 'newState' => 'open']));
    }

    public function hidePicker(\Dom\Element $select): void
    {
        if (!$this->containsElement($select) || 'select' !== $select->localName) {
            throw new \InvalidArgumentException('The picker target must be a select in this document.');
        }
        if (!\in_array($select, $this->topLayers, true)) {
            return;
        }
        unset($this->pickerOptions[$select], $this->pickerScroll[$select]);
        $this->removeLayer($select);
        $this->dispatch(new DocumentEvent('toggle', $select, ['oldState' => 'open', 'newState' => 'closed']));
    }

    private function revealPickerOption(\Dom\Element $select, \Dom\Element $option): void
    {
        $result = $this->render();
        $geometry = $result->getScrollGeometry($select, 'picker');
        $bounds = $result->getBounds($option)[0] ?? null;
        if (null === $geometry || null === $bounds) {
            return;
        }
        $offset = $geometry->offsetY;
        if ($bounds->y < $geometry->port->y) {
            $offset -= $geometry->port->y - $bounds->y;
        } elseif ($bounds->y + $bounds->height > $geometry->port->y + $geometry->port->height) {
            $offset += $bounds->y + $bounds->height - $geometry->port->y - $geometry->port->height;
        }
        $offset = max(0.0, min($geometry->maxY, $offset));
        if ($offset !== ($this->pickerScroll[$select] ?? 0.0)) {
            $this->pickerScroll[$select] = $offset;
            $this->invalidate();
        }
    }

    private function beforeToggle(\Dom\Element $element, bool $opening): bool
    {
        if (isset($this->overlayTransitions[$element])) {
            return false;
        }
        $this->overlayTransitions[$element] = true;
        try {
            $this->dispatch($event = new DocumentEvent('beforetoggle', $element, ['oldState' => $opening ? 'closed' : 'open', 'newState' => $opening ? 'open' : 'closed']));

            return !$opening || !$event->isDefaultPrevented();
        } finally {
            unset($this->overlayTransitions[$element]);
        }
    }

    private function openDialog(\Dom\Element $dialog, bool $modal): void
    {
        $this->assertDialog($dialog);
        if (!$this->containsElement($dialog)) {
            throw new \InvalidArgumentException('The dialog must be connected.');
        }
        if (isset($this->modalFocus[$dialog]) || !$modal && $dialog->hasAttribute('open')) {
            return;
        }
        $wasOpen = $dialog->hasAttribute('open');
        if (!$wasOpen) {
            if (!$this->beforeToggle($dialog, true) || !$this->containsElement($dialog) || $dialog->hasAttribute('open')) {
                return;
            }
        }
        $previous = $this->getFocusedElement();
        $dialog->setAttribute('open', '');
        if ($modal) {
            $this->modalFocus[$dialog] = $previous;
            $this->removeLayer($dialog);
            $this->topLayers[] = $dialog;
        }
        $this->invalidate();
        $target = $dialog->querySelector('[autofocus]:not([disabled]), input:not([type="hidden"]):not([disabled]), button:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]');
        $autofocus = $dialog->querySelector('[autofocus]:not([disabled])');
        $target = $autofocus ?? $target ?? $dialog;
        if (!ControlState::isDisabled($target) && !$this->isBlocked($target)) {
            $this->focus($target);
        }
        if (!$wasOpen) {
            $this->dispatch(new DocumentEvent('toggle', $dialog, ['oldState' => 'closed', 'newState' => 'open']));
        }
    }

    private function assertDialog(\Dom\Element $dialog): void
    {
        if (!$this->containsElement($dialog) || 'dialog' !== $dialog->localName) {
            throw new \InvalidArgumentException('The target must be a dialog in this document.');
        }
    }

    private function activeModal(): ?\Dom\Element
    {
        foreach (array_reverse($this->topLayers) as $layer) {
            if (isset($this->modalFocus[$layer]) && $this->containsElement($layer) && $layer->hasAttribute('open')) {
                return $layer;
            }
        }

        return null;
    }

    private function embeddedConnected(): bool
    {
        if (null === $this->embeddedOwner) {
            return true;
        }
        $host = $this->embeddedHost;

        return null !== $host && $this->embeddedOwner->containsElement($host) && $host->ownerDocument === $this->embeddedOwner->document && ($this->embeddedOwner->embedded[$host]['session'] ?? null) === $this && $this->embeddedOwner->embedded[$host]['attributes'] === self::embeddedSourceSignature($host) && !$this->embeddedOwner->isBlocked($host);
    }

    private function isBlocked(\Dom\Element $element): bool
    {
        return !$this->embeddedConnected() || $this->isInert($element) || null !== ($modal = $this->activeModal()) && !$this->composedContains($modal, $element);
    }

    private function dismissOutside(?\Dom\Element $target): bool
    {
        foreach (array_reverse($this->topLayers) as $layer) {
            if (null !== $target && $this->composedContains($layer, $target)) {
                break;
            }
            if ('select' === $layer->localName) {
                $this->hidePicker($layer);

                continue;
            }
            if (isset($this->modalFocus[$layer])) {
                if ('any' === strtolower($layer->getAttribute('closedby') ?? '')) {
                    $this->requestClose($layer);
                }

                return true;
            }
            if ('manual' !== $this->popoverMode($layer) && (null === $target || null === $layer->id || $target->getAttribute('popovertarget') !== $layer->id)) {
                $this->hidePopover($layer);
            }
        }

        return false;
    }

    private function dismissLayer(): bool
    {
        foreach (array_reverse($this->topLayers) as $layer) {
            if ('select' === $layer->localName) {
                $this->hidePicker($layer);

                return true;
            }
            if (isset($this->modalFocus[$layer])) {
                if ('none' !== strtolower($layer->getAttribute('closedby') ?? '')) {
                    $this->requestClose($layer);
                }

                return true;
            }
            if ('manual' !== $this->popoverMode($layer)) {
                $this->hidePopover($layer);

                return true;
            }
        }

        return false;
    }

    private function popoverMode(\Dom\Element $element): string
    {
        $mode = strtolower($element->getAttribute('popover') ?? 'auto');

        return \in_array($mode, ['manual', 'hint'], true) ? $mode : 'auto';
    }

    private function removeLayer(\Dom\Element $element): void
    {
        $this->topLayers = array_values(array_filter($this->topLayers, static fn (\Dom\Element $layer): bool => $layer !== $element));
        $this->invalidate();
    }

    private function isInterested(\Dom\Element $element): bool
    {
        return $this->containsElement($element) && (null !== $this->focused && $this->containsElement($this->focused) && $this->composedContains($element, $this->focused) || null !== $this->hovered && $this->containsElement($this->hovered) && $this->composedContains($element, $this->hovered));
    }

    private function updateInterest(): void
    {
        if ($this->updatingInterest) {
            return;
        }
        $this->updatingInterest = true;
        try {
            $active = new \SplObjectStorage();
            $wanted = new \SplObjectStorage();
            foreach ($this->elements('[interestfor]') as $invoker) {
                if (!$this->isInterested($invoker) || $this->isBlocked($invoker)) {
                    continue;
                }
                $target = TreeScope::getElementById($invoker, trim($invoker->getAttribute('interestfor')));
                if (null === $target || !$target->hasAttribute('popover') || $target === $invoker || $this->isBlocked($target)) {
                    continue;
                }
                $active[$invoker] = $target;
                if (($this->interestActive[$invoker] ?? null) !== $target && !\in_array($target, $this->topLayers, true)) {
                    $this->showPopover($target);
                }
                if ($this->containsElement($target) && \in_array($target, $this->topLayers, true)) {
                    $this->interestShown[$target] = $invoker;
                    if ($this->isInterested($invoker)) {
                        $wanted[$target] = true;
                    }
                }
            }
            $this->interestActive = $active;
            $kept = new \SplObjectStorage();
            foreach ($this->interestShown as $target) {
                if (!$this->containsElement($target) || !$target->hasAttribute('popover')) {
                    continue;
                }
                if (!isset($wanted[$target]) && !$this->isInterested($target)) {
                    $this->hidePopover($target);
                }
                if (\in_array($target, $this->topLayers, true)) {
                    $anchor = $this->interestShown[$target];
                    $kept[$target] = null !== $anchor && $this->containsElement($anchor) ? $anchor : null;
                }
            }
            $this->interestShown = $kept;
        } finally {
            $this->updatingInterest = false;
        }
    }

    private function pruneLayers(): void
    {
        foreach ($this->modalFocus as $dialog) {
            $previous = $this->modalFocus[$dialog];
            if (null !== $previous && (!$this->containsElement($previous))) {
                $this->modalFocus[$dialog] = null;
            }
        }
        foreach ($this->topLayers as $layer) {
            if (!$this->containsElement($layer) || ('select' === $layer->localName ? (ControlState::isDisabled($layer) || SelectOptions::isListBox($layer)) : (isset($this->modalFocus[$layer]) ? !$layer->hasAttribute('open') : !$layer->hasAttribute('popover')))) {
                unset($this->modalFocus[$layer], $this->pickerOptions[$layer], $this->pickerScroll[$layer]);
                $this->removeLayer($layer);
            }
        }
    }

    private function activationTarget(\Dom\Element $element): \Dom\Element
    {
        return $element->closest('a[href], area[href], button, input, textarea, select, label, summary, option') ?? $element;
    }

    private function rangeAt(\Dom\Element $element, float $x): void
    {
        $result = $this->render();
        $bounds = $result->getBounds($element, 'track')[0] ?? $result->getBounds($element)[0] ?? null;
        if (null === $bounds || $bounds->width <= 0) {
            return;
        }
        $fraction = max(0.0, min(1.0, ($x - $bounds->x) / $bounds->width));
        if ('rtl' === ($result->getComputedStyle($element)['direction'] ?? 'ltr')) {
            $fraction = 1 - $fraction;
        }
        $model = NumericControl::range($element, $this->getValue($element));
        $value = NumberStepper::constrain($model['min'] * (1 - $fraction) + $model['max'] * $fraction, $model['min'], $model['max'], $model['step']);
        if ($value !== $model['value']) {
            $this->input(NumberStepper::format($value, $model['step'], $model['min']));
        }
    }

    private function setSelectedOptions(\Dom\Element $select, array $options, bool $dispatch = true): void
    {
        $this->selections[$select] = $options;
        $this->invalidate();
        if ($dispatch) {
            $this->interacted[$select] = true;
            $this->dispatch(new DocumentEvent('change', $select, ['values' => $this->getSelectedValues($select)]));
        }
    }

    private function selectedOptions(\Dom\Element $select): array
    {
        if (!isset($this->selections[$select])) {
            return SelectOptions::selected($select);
        }
        $selected = new \SplObjectStorage();
        foreach ($this->selections[$select] as $option) {
            $selected[$option] = true;
        }
        $result = [];
        foreach (SelectOptions::options($select) as $option) {
            if (isset($selected[$option])) {
                $result[] = $option;
                if (!$select->hasAttribute('multiple')) {
                    break;
                }
            }
        }

        return $result;
    }

    private function summary(\Dom\Element $details): ?\Dom\Element
    {
        return Html\Details::summary($details);
    }

    private function editor(\Dom\Element $element): TextEditor
    {
        if ($element->ownerDocument !== $this->document || !\in_array($element->localName, ['input', 'textarea'], true)) {
            throw new \InvalidArgumentException('The editing target must be a text control in this document.');
        }
        $value = $this->getValue($element);
        if (!isset($this->editors[$element]) || $this->editors[$element]->getText() !== $value) {
            $this->editors[$element] = new TextEditor($value, $this->maxTextBytes);
        }

        return $this->editors[$element];
    }

    private function updatePointer(?\Dom\Element $element): void
    {
        if ($this->hovered !== $element) {
            $this->hovered = $element;
            $this->invalidate();
        }
        $this->updateInterest();
    }

    private function activateScrollControl(array $control): bool
    {
        $element = $control['element'];
        $direction = $control['action'];
        if ('marker' === $direction) {
            $target = $control['target'];
            if (!$this->containsElement($element) || !$this->containsElement($target) || $this->isBlocked($element) || $this->isBlocked($target) || !$this->composedContains($element, $target)) {
                return false;
            }
            $this->dispatch($event = new DocumentEvent('click', $target, ['scroll-marker' => $element]));
            if ($event->isDefaultPrevented() || !$this->containsElement($element) || !$this->containsElement($target) || $this->isBlocked($element) || $this->isBlocked($target) || !$this->composedContains($element, $target) || null === $offset = $this->render()->getScrollOffsetToReveal($target, $element, false, ['start', 'start'])) {
                return false;
            }
            $this->scroll($element, $offset[0], $offset[1]);

            return true;
        }
        if (!$this->containsElement($element) || $this->isBlocked($element) || !Html\ScrollControls::available($this->render()->getScrollGeometry($element), $direction)) {
            return false;
        }
        $this->dispatch($event = new DocumentEvent('click', $element, ['scroll-button' => $direction]));
        if ($event->isDefaultPrevented() || !$this->containsElement($element) || $this->isBlocked($element) || null === $geometry = $this->render()->getScrollGeometry($element)) {
            return false;
        }
        $vertical = \in_array($direction, ['up', 'down'], true);
        $page = Html\ScrollControls::pageSize($vertical ? $geometry->port->height : $geometry->port->width);
        $page = $this->context->layoutMetrics?->constrainLength($page, 'scroll-control-page', $vertical) ?? $page;
        $sign = \in_array($direction, ['up', 'left'], true) ? -1 : 1;
        $this->scroll($element, $geometry->offsetX + ($vertical ? 0.0 : $sign * $page), $geometry->offsetY + ($vertical ? $sign * $page : 0.0));

        return true;
    }
}
