<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Widget;

use Symfony\Bridge\TuiHtml\Content\NativeContentRegistry;
use Symfony\Bridge\TuiHtml\Event\FormSubmitEvent;
use Symfony\Bridge\TuiHtml\Html\Element\PathCompleter;
use Symfony\Bridge\TuiHtml\Internal\ControlStyles;
use Symfony\Bridge\TuiHtml\Render\DisplayListRenderer;
use Symfony\Bridge\TuiHtml\Render\Renderer as HtmlWidgetRenderer;
use Symfony\Bridge\TuiHtml\Render\TerminalColorPalette;
use Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics;
use Symfony\Bridge\TuiHtml\Render\TerminalRenderer;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Event\DocumentEvent;
use Symfony\Component\HtmlRenderer\Event\KeyboardEvent;
use Symfony\Component\HtmlRenderer\Event\PointerEvent;
use Symfony\Component\HtmlRenderer\Event\WheelEvent;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\FormSubmission;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\RenderingResult;
use Symfony\Component\HtmlRenderer\RenderSession;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\Bidi;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\RendererDecoratorInterface;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\BracketedPasteTrait;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\Tui\Widget\ScheduledTickTrait;
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Symfony\Component\Tui\Widget\WidgetContext;

final class DocumentWidget extends AbstractWidget implements FocusableInterface
{
    use BracketedPasteTrait;
    use KeybindingsTrait;
    use ScheduledTickTrait;
    private ?\Symfony\Bridge\TuiHtml\Internal\BackgroundImageCache $backgroundImages = null;

    private bool $focused = false;
    private Density $density = Density::Regular;
    private ?MediaContext $mediaContext = null;
    private ?\Closure $inputListener = null;
    private ?\Closure $unsubscribeInvalidation = null;
    private ?\Closure $unsubscribeCompletion = null;
    private ?array $viewport = null;
    private ?\Dom\Element $lastFocused = null;
    private array $lastEmbeddedFocus = [];
    private \Closure $clock;
    private ?float $lastAnimationTick = null;
    private ?string $generatedUserAgentStylesheet = null;
    private string $baseUserAgentStylesheet = '';
    private bool $terminalUserAgentStylesheet = false;
    private ?ControlStyles $controlStyles = null;
    private \WeakMap $embeddedControlStyles;
    private ?RenderingContext $renderingContext = null;
    private ?MediaContext $renderingMedia = null;
    private array $callerMediaFeatures = [];
    private ?DisplayList $paintedDisplayList = null;
    private ?MediaContext $paintedMedia = null;
    private ?bool $paintedBidi = null;
    private array $paintedLines = [];
    private TerminalTextMeasurer $textMetrics;
    private ?TerminalLayoutMetrics $terminalLayoutMetrics = null;
    private readonly ?\Closure $nativeContentResolver;
    private array $nativeFocusSubscriptions = [];

    public function __construct(private readonly RenderSession $session, private readonly TerminalTextMeasurer $metrics = new TerminalTextMeasurer(), private readonly ?NativeContentRegistry $nativeContent = null)
    {
        $this->nativeContentResolver = null === $nativeContent ? null : $nativeContent->resolve(...);
        $this->clock = static fn (): float => hrtime(true) / 1e9;
        $this->textMetrics = $this->metrics;
        $this->embeddedControlStyles = new \WeakMap();
    }

    public function setDensity(Density $density): static
    {
        if ($density !== $this->density) {
            $this->density = $density;
            $this->invalidate();
            $this->getContext()?->requestRender();
        }

        return $this;
    }

    public function setMediaContext(?MediaContext $mediaContext): static
    {
        if ($mediaContext != $this->mediaContext || (null !== $mediaContext && $mediaContext->density !== $this->density)) {
            $this->mediaContext = $mediaContext;
            if (null !== $mediaContext) {
                $this->density = $mediaContext->density;
            }
            $this->invalidate();
            $this->getContext()?->requestRender();
        }

        return $this;
    }

    public function isFocused(): bool
    {
        return $this->focused;
    }

    public function setFocused(bool $focused): static
    {
        if ($this->focused !== $focused) {
            $this->focused = $focused;
            if ($focused) {
                if (null === $this->session->getFocusedElement()) {
                    try {
                        if (null !== $this->lastFocused && $this->session->containsElement($this->lastFocused)) {
                            $this->session->focus($this->lastFocused);
                            $this->restoreEmbeddedFocus();
                        } else {
                            $this->session->focusNext();
                        }
                    } catch (\InvalidArgumentException) {
                        $this->session->focusNext();
                    }
                }
            } else {
                $this->lastFocused = $this->session->getFocusedElement();
                $this->rememberEmbeddedFocus();
                $this->session->focus(null);
            }
            $this->invalidate();
        }

        $this->synchronizeNativeFocus();

        return $this;
    }

    private function rememberEmbeddedFocus(): void
    {
        $this->lastEmbeddedFocus = [];
        $owner = $this->session;
        for ($depth = 0; $depth < 3; ++$depth) {
            $host = $owner->getFocusedElement();
            if (null === $host || 'iframe' !== $host->localName) {
                break;
            }
            $child = $owner->getEmbeddedSession($host);
            $target = $child?->getFocusedElement();
            if (null === $target) {
                break;
            }
            $this->lastEmbeddedFocus[] = [\WeakReference::create($owner), \WeakReference::create($host), \WeakReference::create($child), \WeakReference::create($target)];
            $owner = $child;
        }
    }

    private function restoreEmbeddedFocus(): void
    {
        $owner = $this->session;
        foreach ($this->lastEmbeddedFocus as [$savedOwner, $savedHost, $savedChild, $savedTarget]) {
            $host = $savedHost->get();
            $child = $savedChild->get();
            $target = $savedTarget->get();
            if ($savedOwner->get() !== $owner || !$host instanceof \Dom\Element || !$child instanceof RenderSession || !$target instanceof \Dom\Element || !$owner->containsElement($host) || $host->ownerDocument !== $owner->getDocument() || $host !== $owner->getFocusedElement() || $child !== $owner->getEmbeddedSession($host) || !$child->containsElement($target) || $target->ownerDocument !== $child->getDocument()) {
                break;
            }
            try {
                $child->focus($target);
            } catch (\InvalidArgumentException) {
                break;
            }
            if ($child->getFocusedElement() !== $target) {
                break;
            }
            $owner = $child;
        }
        $this->lastEmbeddedFocus = [];
        $active = $this->session->getFocusedSession();
        if ($active !== $this->session && null === $active->getFocusedElement()) {
            $active->focusNext();
        }
    }

    public function render(RenderContext $context): array
    {
        $viewport = [$context->getColumns(), $context->getRows()];
        $media = ($this->mediaContext ?? MediaContext::fromEnvironment())->withDensity($this->density)->withSize(...$viewport);
        if ($media->textSizing && !$this->supportsTextSizing()) {
            $media = $media->withTextSizing(false);
        }
        $this->textMetrics = $this->textMetrics->withTextSizing($media->textSizing);
        if ($this->nativeContent?->prepare($this->textMetrics, $media)) {
            $this->session->invalidate();
        }
        $rendering = $this->session->getContext();
        if ($rendering !== $this->renderingContext && (null === $this->renderingContext || $rendering->mediaFeatures !== $this->renderingContext->mediaFeatures)) {
            $this->callerMediaFeatures = $rendering->mediaFeatures;
        }
        $userAgentStylesheet = $this->userAgentStylesheet($rendering->userAgentStylesheet);
        if (($viewport !== $this->viewport || $rendering !== $this->renderingContext || $media != $this->renderingMedia || $userAgentStylesheet !== $rendering->userAgentStylesheet) && $viewport[0] > 0 && $viewport[1] > 0) {
            $font = null === $rendering->layoutMetrics ? new FontStyle('monospace', 16, $this->metrics->cellHeight) : $rendering->defaultFont;
            $features = $this->callerMediaFeatures + [
                'type' => 'screen',
                'prefers-color-scheme' => $media->colorScheme->value,
                'prefers-reduced-motion' => $media->reducedMotion ? 'reduce' : 'no-preference',
                'prefers-contrast' => $media->highContrast ? 'more' : 'no-preference',
                'forced-colors' => $media->forcedColors ? 'active' : 'none',
                'pointer' => $media->pointer ? 'fine' : 'none',
                'any-pointer' => $media->pointer ? 'fine' : 'none',
                'hover' => $media->pointer ? 'hover' : 'none',
                'any-hover' => $media->pointer ? 'hover' : 'none',
                'resolution' => $this->metrics->cellHeight / 16,
                'color' => $media->forcedColors ? 0 : ($media->colors >= MediaContext::TRUE_COLORS ? 8 : ($media->colors >= 256 ? 2 : 1)),
                'color-index' => $media->colors >= MediaContext::TRUE_COLORS ? 0 : $media->colors,
                'monochrome' => $media->forcedColors ? 1 : 0,
                'grid' => 1,
            ];
            $layoutMetrics = $rendering->layoutMetrics;
            if (null === $layoutMetrics || $layoutMetrics === $this->terminalLayoutMetrics) {
                $layoutMetrics = $this->terminalLayoutMetrics = new TerminalLayoutMetrics($this->textMetrics, $this->terminalUserAgentStylesheet);
            }
            $rendering = new RenderingContext($rendering->width, $rendering->height, $this->textMetrics, $rendering->resourceLoader, $layoutMetrics, $font, $features, $userAgentStylesheet, $rendering->colors + TerminalColorPalette::get(), $rendering->hyphenator, $rendering->replacedContentResolver ?? $this->nativeContentResolver, $rendering->colorLookup);
            $this->session->setContext($rendering->withViewport($viewport[0] * $this->metrics->cellWidth, $viewport[1] * $this->metrics->cellHeight, $this->textMetrics));
            $this->viewport = $viewport;
            $this->renderingContext = $this->session->getContext();
            $this->renderingMedia = $media;
        }

        $result = $this->synchronizeDocumentPolicies($this->session, $this->density);
        $this->synchronizeNativeFocus();
        $this->updateAnimationTick();

        $displayList = $result->getDisplayList();
        $bidi = Bidi::isAvailable();
        if ($displayList !== $this->paintedDisplayList || $media != $this->paintedMedia || $bidi !== $this->paintedBidi) {
            $this->paintedLines = (new DisplayListRenderer($this->textMetrics, $media, $this->backgroundImages ??= new \Symfony\Bridge\TuiHtml\Internal\BackgroundImageCache()))->render($displayList, ...$viewport);
            $this->paintedDisplayList = $displayList;
            $this->paintedMedia = $media;
            $this->paintedBidi = $bidi;
        }

        return $this->paintedLines;
    }

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }
        $owner = $this->session->getFocusedSession();
        $input = $owner->getFocusedElement();
        $this->synchronizeNativeFocus();
        $native = null === $input ? null : $this->nativeContent?->getWidget($input);
        if ($native instanceof FocusableInterface && $owner->containsElement($input)) {
            $key = $this->keyboardEvent($data);
            if (null !== $key) {
                $event = new DocumentEvent('keydown', $input, ['key' => $key]);
                $owner->dispatch($event);
                if ($event->isDefaultPrevented()) {
                    return;
                }
            }
            if ($owner->containsElement($input) && $owner->getFocusedElement() === $input && $this->nativeContent->getWidget($input) === $native) {
                $native->handleInput($data);
            }
            $this->invalidate();
            $this->getContext()?->requestRender();

            return;
        }
        $value = null !== $input && 'input' === $input->localName ? $owner->getValue($input) : null;
        if (null !== $paste = $this->processBracketedPaste($data)) {
            $paste = StringUtils::decodeCtrlLetterKeys($paste);
            $paste = StringUtils::stripControlBytes(AnsiUtils::stripAnsiCodes(StringUtils::sanitizeUtf8($paste)));
            $this->dispatchSubmission($this->session->key(new KeyboardEvent('Unidentified', $paste)));
        }
        if ('' !== $data && null !== $key = $this->keyboardEvent($data)) {
            $this->dispatchSubmission($this->session->key($key));
        }
        if ((null === $this->unsubscribeCompletion || $owner !== $this->session) && null !== $value && $owner === $this->session->getFocusedSession() && $input === $owner->getFocusedElement() && $value !== $owner->getValue($input)) {
            $this->updatePathSuggestions($input);
        }
        $this->invalidate();
        $this->getContext()?->requestRender();
    }

    public function handlePointer(int $row, int $column, bool $activate = false): ?\Dom\Element
    {
        $element = $this->session->render()->hitTest($column * $this->metrics->cellWidth, $row * $this->metrics->cellHeight);
        if ($activate) {
            $this->handlePointerGesture('down', $row, $column);
            $this->handlePointerGesture('up', $row, $column);
        } else {
            $this->handlePointerGesture('move', $row, $column);
        }

        return $element;
    }

    /** Scrolls the document at widget-local cells; the application captures physical wheel input. */
    public function scrollAt(int $row, int $column, int $rows, int $columns = 0): bool
    {
        $x = $column * $this->metrics->cellWidth;
        $y = $row * $this->metrics->cellHeight;
        if (0 === $rows && 0 === $columns || $x < 0 || $y < 0 || $x >= $this->session->getContext()->width || $y >= $this->session->getContext()->height) {
            return false;
        }
        $changed = $this->session->handleWheel(new WheelEvent($x, $y, $columns * $this->metrics->cellWidth, $rows * $this->metrics->cellHeight));
        $this->invalidate();
        $this->getContext()?->requestRender();

        return $changed;
    }

    public function handlePointerGesture(string $type, int $row, int $column, int $button = 0, bool $shift = false): void
    {
        $pointer = new PointerEvent($type, $column * $this->metrics->cellWidth, $row * $this->metrics->cellHeight, $button, $shift);
        if ('down' === $type) {
            $this->getContext()?->getFocusManager()->setFocus($this);
        }
        $this->dispatchSubmission($this->session->handlePointer($pointer));
        $this->invalidate();
        $this->getContext()?->requestRender();
    }

    private function updatePathSuggestions(?\Dom\Element $input): void
    {
        $owner = $this->session->getFocusedSession();
        if (null === $input || $input->ownerDocument !== $owner->getDocument() || !$owner->containsElement($input) || 'input' !== $input->localName || 'file' !== strtolower($input->getAttribute('type') ?? 'text')) {
            return;
        }
        $completer = new PathCompleter($input->getAttribute('accept'), $input->hasAttribute('multiple'));
        $suggestions = array_values(array_filter($completer->complete($owner->getValue($input)), static fn (string $suggestion): bool => 1 === preg_match('//u', $suggestion)));
        try {
            $owner->setSuggestions($input, $suggestions);
        } catch (\LengthException) {
            // A small document budget may not accommodate the directory's suggestions.
            $owner->setSuggestions($input, []);
        }
    }

    private function synchronizeNativeFocus(): void
    {
        if (null === $this->nativeContent) {
            return;
        }
        $owner = $this->session->getFocusedSession();
        $element = $owner->getFocusedElement();
        $this->nativeContent->synchronizeFocus($this->focused && null !== $element && $owner->containsElement($element) ? $element : null);
    }

    protected function onAttach(WidgetContext $context): void
    {
        $this->nativeContent?->attach($this, $context);
        if (null !== $this->nativeContent) {
            foreach (['focus', 'blur'] as $type) {
                $this->nativeFocusSubscriptions[] = $this->session->on($type, function (): void {
                    $this->synchronizeNativeFocus();
                });
            }
            $this->synchronizeNativeFocus();
        }
        $this->unsubscribeCompletion = $this->session->on('input', function (DocumentEvent $event): void {
            $this->updatePathSuggestions($event->target);
        });
        $this->unsubscribeInvalidation = $this->session->onInvalidation(function (): void {
            $this->invalidate();
            $this->getContext()?->requestRender();
        });
        $this->inputListener = function (InputEvent $event): void {
            if (!$this->focused || $this->isBufferingPaste()) {
                return;
            }
            $key = $this->keyboardEvent($event->getData());
            if (null === $key || 'Tab' !== $key->key || $key->control || $key->alt || $key->meta) {
                return;
            }
            if (null !== $this->onInput && ($this->onInput)($event->getData())) {
                $event->stopPropagation();

                return;
            }
            $backward = $key->shift;
            if (!$this->session->tab($backward, false)) {
                $manager = $this->getContext()?->getFocusManager();
                if ($backward) {
                    $manager?->focusPrevious();
                } else {
                    $manager?->focusNext();
                }
            }
            $event->stopPropagation();
            $this->invalidate();
            $this->getContext()?->requestRender();
        };
        $context->getEventDispatcher()->addListener(InputEvent::class, $this->inputListener);
        $this->updateAnimationTick();
    }

    protected function onDetach(): void
    {
        foreach ($this->nativeFocusSubscriptions as $unsubscribe) {
            $unsubscribe();
        }
        $this->nativeFocusSubscriptions = [];
        ($this->unsubscribeCompletion ?? static function () {})();
        $this->unsubscribeCompletion = null;
        ($this->unsubscribeInvalidation ?? static function () {})();
        $this->unsubscribeInvalidation = null;
        $this->clearScheduledTick();
        $this->lastAnimationTick = null;
        if (null !== $this->inputListener) {
            $this->getContext()?->getEventDispatcher()->removeListener(InputEvent::class, $this->inputListener);
            $this->inputListener = null;
        }
        $this->setFocused(false);
        $this->nativeContent?->detach();
    }

    protected function resolveScheduledTickContext(): ?WidgetContext
    {
        return $this->getContext();
    }

    protected function onScheduledTick(): void
    {
        if (null === $context = $this->getContext()) {
            $this->clearScheduledTick();
            $this->lastAnimationTick = null;

            return;
        }
        if ($context->prefersReducedMotion()) {
            $this->clearScheduledTick();
            $this->lastAnimationTick = null;

            return;
        }
        $now = ($this->clock)();
        $elapsed = max(0.0, $now - ($this->lastAnimationTick ?? $now));
        $this->lastAnimationTick = $now;
        if ($elapsed > 0) {
            $this->session->advance($elapsed);
            $this->invalidate();
            $context->requestRender();
        }
        if (!$this->session->isAnimating()) {
            $this->clearScheduledTick();
            $this->lastAnimationTick = null;
        }
    }

    private function supportsTextSizing(): bool
    {
        $renderer = $this->getContext()?->getRenderer();
        if (null === $renderer) {
            return true;
        }
        $visited = [];
        while (!isset($visited[spl_object_id($renderer)])) {
            if ($renderer instanceof TerminalRenderer || $renderer instanceof HtmlWidgetRenderer) {
                return true;
            }
            if (!$renderer instanceof RendererDecoratorInterface) {
                break;
            }
            $visited[spl_object_id($renderer)] = true;
            $renderer = $renderer->getDecoratedRenderer();
        }

        return false;
    }

    private function synchronizeDocumentPolicies(RenderSession $session, Density $inheritedDensity): RenderingResult
    {
        $controls = $session === $this->session ? $this->controlStyles ??= new ControlStyles() : $this->embeddedControlStyles[$session] ??= new ControlStyles();
        $elements = iterator_to_array($session->getComposedElements(), false);
        $controls->synchronize($session, $this->terminalUserAgentStylesheet, $elements);
        $densities = new \SplObjectStorage();
        foreach ($elements as [$element, $parent]) {
            if (!$session->containsElement($element)) {
                continue;
            }
            $inherited = null === $parent ? $inheritedDensity : ($densities[$parent] ?? $inheritedDensity);
            $density = Density::tryFrom($element->getAttribute('data-density') ?? '') ?? $inherited;
            $densities[$element] = $density;
            $session->setState($element, 'condensed', Density::Condensed === $density);
            if ($session->containsElement($element)) {
                $session->setState($element, 'regular', Density::Regular === $density);
            }
        }
        $result = $session->render();
        $children = $frames = [];
        foreach ($elements as [$frame]) {
            if ('iframe' !== $frame->localName || !$session->containsElement($frame)) {
                continue;
            }
            $child = $session->getEmbeddedSession($frame);
            $frames[] = [$frame, $child];
            if (null !== $child) {
                $children[] = [$child, $densities[$frame] ?? $inheritedDensity, $child->render()];
            }
        }
        $controls->synchronizeFrames($session, $frames, $this->terminalUserAgentStylesheet);
        $changed = false;
        foreach ($children as [$child, $density, $before]) {
            if ($before !== $this->synchronizeDocumentPolicies($child, $density)) {
                $changed = true;
            }
        }

        return $changed || $frames ? $session->render() : $result;
    }

    private function userAgentStylesheet(string $provided): string
    {
        if ($provided !== $this->generatedUserAgentStylesheet) {
            $this->terminalUserAgentStylesheet = '' === $provided;
            $this->baseUserAgentStylesheet = $provided ?: self::terminalStylesheet();
        }

        return $this->generatedUserAgentStylesheet = $this->baseUserAgentStylesheet;
    }

    private static function terminalStylesheet(): string
    {
        static $stylesheet;
        if (null === $stylesheet) {
            $directory = \dirname(__DIR__).'/Html/Resources/';
            $stylesheet = "* { box-sizing: border-box }\n";
            foreach (['ua.css', 'default.css'] as $file) {
                $stylesheet .= (file_get_contents($directory.$file) ?: throw new \LogicException('The terminal user agent stylesheet cannot be read.'))."\n";
            }
            $stylesheet .= <<<'CSS'
                html { line-height: 16px }
                table { border-spacing: var(--tui-cell-spacing, 1ch) 0 }
                rt { font-size: 100% }
                ::scroll-button(*):disabled { opacity: .5 }
                ::scroll-marker-group { display: flex; column-gap: 1ch }
                button:focus, select:tui-dropdown:focus:not(:open), input:is([type="checkbox" i], [type="radio" i], [type="button" i], [type="submit" i], [type="reset" i]):focus { color: Canvas; background-color: default; background-clip: content-box; filter: none }
                @media (forced-colors: active) {
                    button:focus, select:tui-dropdown:focus:not(:open), input:is([type="checkbox" i], [type="radio" i], [type="button" i], [type="submit" i], [type="reset" i]):focus { text-decoration-line: underline }
                }
                input, textarea, button, select { border: 0; padding: 0; min-height: 1lh }
                input::placeholder, textarea::placeholder { color: inherit }
                input[type="color" i] { width: calc(20ch + 4ch) }
                @property --tui-progress-offset { syntax: "<percentage>"; inherits: true; initial-value: 0% }
                @keyframes tui-html-progress { from { --tui-progress-offset: 0% } to { --tui-progress-offset: 75% } }
                progress:indeterminate { animation: tui-html-progress 1.5s linear infinite alternate }
                progress:indeterminate::bar, progress:indeterminate::bar-fill { top: 0; height: 1lh }
                progress:indeterminate::bar-fill { left: var(--tui-progress-offset); width: 25% }
                @media (prefers-reduced-motion: reduce) { progress:indeterminate { animation: none } }

                input:not([type="checkbox" i], [type="radio" i], [type="hidden" i], [type="button" i], [type="submit" i], [type="reset" i], [type="range" i]) { width: calc(20ch + 3ch); padding-inline-end: 1ch }
                input:not([type="checkbox" i], [type="radio" i], [type="hidden" i], [type="button" i], [type="submit" i], [type="reset" i], [type="range" i])::before { content: "> " }
                button::before, input:is([type="button" i], [type="submit" i], [type="reset" i])::before { content: "[ " }
                button::after, input:is([type="button" i], [type="submit" i], [type="reset" i])::after { content: " ]" }
                input:is([type="checkbox" i], [type="radio" i]) { appearance: none; width: 3ch; height: 1lh }
                input[type="checkbox" i]::before { content: "[ ]" }
                input[type="checkbox" i]:checked::before { content: "[x]" }
                input[type="radio" i]::before { content: "( )" }
                input[type="radio" i]:checked::before { content: "(•)" }
                CSS;
        }

        return $stylesheet;
    }

    private function updateAnimationTick(): void
    {
        if (null !== ($context = $this->getContext()) && !$context->prefersReducedMotion() && $this->session->isAnimating()) {
            $this->lastAnimationTick ??= ($this->clock)();
            $this->startScheduledTick(1 / 30);
        } else {
            $this->clearScheduledTick();
            $this->lastAnimationTick = null;
        }
    }

    private function dispatchSubmission(?FormSubmission $submission): void
    {
        if (null !== $submission) {
            $this->dispatch(new FormSubmitEvent($this, $submission->values, null, $submission->action, $submission->method));
        }
    }

    private function keyboardEvent(string $data): ?KeyboardEvent
    {
        $parser = $this->getKeybindings()->getParser();
        if ($parser->isKeyRelease($data)) {
            return null;
        }
        $parsed = $parser->parse($data);
        $modifiers = $parsed['modifiers'] ?? [];
        $identifier = $parsed['key'] ?? '';
        $name = '+' === $identifier || str_ends_with($identifier, '++') ? '+' : substr($identifier, (false === $offset = strrpos($identifier, '+')) ? 0 : $offset + 1);
        $key = match ($name) {
            'left' => 'ArrowLeft',
            'right' => 'ArrowRight',
            'up' => 'ArrowUp',
            'down' => 'ArrowDown',
            'home' => 'Home',
            'end' => 'End',
            'backspace' => 'Backspace',
            'delete' => 'Delete',
            'enter' => 'Enter',
            'space' => 'Space',
            'tab' => 'Tab',
            'escape' => 'Escape',
            'page_up' => 'PageUp',
            'page_down' => 'PageDown',
            default => '' === $name ? 'Unidentified' : $name,
        };
        $control = \in_array('ctrl', $modifiers, true);
        $alt = \in_array('alt', $modifiers, true);
        $meta = (bool) array_intersect($modifiers, ['meta', 'super', 'hyper']);
        $text = $parser->decodePrintable($data);
        if (null === $text && !str_contains($data, "\x1b") && !$control && !$alt && !$meta) {
            $text = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($data));
            $text = '' === $text ? null : $text;
        }

        return new KeyboardEvent($key, $text, \in_array('shift', $modifiers, true), $control, $alt, $meta);
    }
}
