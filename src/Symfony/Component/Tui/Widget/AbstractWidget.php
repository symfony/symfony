<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

use Symfony\Component\Tui\Event\AbstractEvent;
use Symfony\Component\Tui\Exception\RenderException;
use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\DefaultStyleSheet;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Tui;

/**
 * Base widget class with lifecycle hooks and dirty tracking.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractWidget
{
    private int $renderRevision = 0;
    private ?string $id = null;
    private ?string $label = null;
    private ?AbstractWidget $parent = null;
    private ?WidgetContext $context = null;
    private ?Style $internalStyle = null;
    private static ?StyleSheet $defaultStyleSheet = null;

    /** @var string[] */
    private array $styleClasses = [];

    /** @var array<string, true> */
    private array $states = [];

    /** @var array<class-string<AbstractEvent>, list<callable>> */
    private array $listeners = [];

    // Render cache: stores the last output of Renderer::renderWidgetLines()
    // keyed on (renderRevision, columns, rows) so unchanged widgets
    // skip style resolution, layout, chrome, and content rendering.

    private ?LineBufferInterface $renderCacheLines = null;
    private int $renderCacheRevision = -1;
    private int $renderCacheColumns = -1;
    private int $renderCacheRows = -1;
    /** @var array<mixed> What the Renderer keeps with the lines cached, see setRenderCache() */
    private array $renderCacheExtra = [];
    /** @var array<string, array{LineBufferInterface, array<mixed>}> The renders of the same revision in other sizes, a layout trying a few, by "columns:rows", the oldest first, each with its extra */
    private array $renderCacheOthers = [];
    /** @var array{\WeakReference<self>, int}|null The parent whose layout set its place among floats, and its revision then, see floatsSetByLayout() */
    private ?array $floatsSetBy = null;

    /**
     * @return $this
     */
    final public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    final public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set an optional human-readable label for the widget.
     *
     * Used by parent widgets (e.g., TabsWidget) to extract metadata
     * from children added via templates.
     *
     * @return $this
     */
    final public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    final public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * Find a descendant widget by ID (depth-first search).
     *
     * Searches this widget and its subtree. Returns null if not found.
     */
    final public function findById(string $id): ?self
    {
        if ($this->id === $id) {
            return $this;
        }

        if ($this instanceof ParentInterface) {
            foreach ($this->all() as $child) {
                $found = $child->findById($id);
                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }

    final public function getParent(): ?self
    {
        return $this->parent;
    }

    final public function getContext(): ?WidgetContext
    {
        return $this->context;
    }

    /**
     * @return string[]
     */
    final public function getStyleClasses(): array
    {
        return $this->styleClasses;
    }

    /**
     * @param string[] $classes
     */
    final public function setStyleClasses(array $classes): void
    {
        if ($this->styleClasses !== $classes) {
            $this->styleClasses = $classes;
            $this->invalidate();
        }
    }

    /**
     * @return $this
     */
    final public function addStyleClass(string $class): static
    {
        if (!\in_array($class, $this->styleClasses, true)) {
            $this->styleClasses[] = $class;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * @return $this
     */
    final public function removeStyleClass(string $class): static
    {
        $newClasses = array_values(array_filter(
            $this->styleClasses,
            static fn (string $c) => $c !== $class,
        ));

        if ($newClasses !== $this->styleClasses) {
            $this->styleClasses = $newClasses;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * @return string[]
     */
    final public function getStateFlags(): array
    {
        $flags = [];

        if (null === $this->parent) {
            $flags[] = 'root';
        }

        if ($this instanceof FocusableInterface && $this->isFocused()) {
            $flags[] = 'focus';
        }

        foreach ($this->states as $state => $_) {
            if ('root' !== $state && 'focus' !== $state) {
                $flags[] = $state;
            }
        }

        return $flags;
    }

    /**
     * Turns a custom state flag on or off.
     *
     * State flags are matched by state selectors, like the built-in "root"
     * and "focus" ones: a widget with the "hover" state matches ':hover',
     * '.button:hover' and Button::class.':hover'.
     *
     * @return $this
     */
    final public function setState(string $state, bool $on = true): static
    {
        if ($on === isset($this->states[$state])) {
            return $this;
        }

        if ($on) {
            $this->states[$state] = true;
        } else {
            unset($this->states[$state]);
        }

        $this->invalidate();

        return $this;
    }

    final public function hasState(string $state): bool
    {
        return \in_array($state, $this->getStateFlags(), true);
    }

    final public function invalidate(): void
    {
        ++$this->renderRevision;
        $this->renderCacheLines = null;
        $this->renderCacheExtra = [];
        $this->renderCacheOthers = [];

        if (null !== $this->parent) {
            $this->parent->invalidate();
        } else {
            // Once per change, at the root: the styles built on those of the widgets may be stale
            $this->context?->invalidateStyles();
        }
    }

    final public function getRenderRevision(): int
    {
        return $this->renderRevision;
    }

    /**
     * @internal
     */
    final public function attach(?self $parent, WidgetContext $context): void
    {
        $this->parent = $parent;
        $this->context = $context;

        if ($this instanceof FocusableInterface) {
            $context->getFocusManager()->add($this);
        }

        $this->onAttach($context);
    }

    /**
     * @internal
     */
    final public function detach(): void
    {
        $context = $this->context;

        if (null !== $context && $this instanceof FocusableInterface) {
            $context->getFocusManager()->remove($this);
        }

        // The listeners stay. They live on this widget and are dispatched
        // from it, so there is nothing registered elsewhere to unwind, and
        // dropping them silently unhooks the callbacks a caller registered
        // on a widget it still holds and may add back. Releasing one is the
        // caller's call to make, through off().
        $this->onDetach();
        $this->parent = null;
        $this->context = null;
    }

    /**
     * @return $this
     */
    final public function setStyle(?Style $style): static
    {
        // Another object of the same values, like a style computed again, does not draw the widget again
        if (null === $style ? null !== $this->internalStyle : !$style->equals($this->internalStyle)) {
            $this->internalStyle = $style;
            $this->invalidate();
        }

        return $this;
    }

    final public function getStyle(): ?Style
    {
        return $this->internalStyle;
    }

    /**
     * Collect and return an escape sequence the terminal must process when
     * this widget is removed from the tree, and reset the associated state.
     *
     * The {@see WidgetTree} calls this just before detaching the widget and
     * writes the returned data to the terminal. Override this in widgets
     * that allocate terminal-side resources (e.g. Kitty image data).
     *
     * Implementations should clear any resource IDs they return sequences
     * for, so a second call is a no-op.
     *
     * The default implementation returns an empty string (no cleanup needed).
     */
    public function collectTerminalCleanupSequence(): string
    {
        return '';
    }

    /**
     * Check if this widget has any local listeners for the given event type.
     *
     * @param class-string<AbstractEvent> $eventClass
     */
    final public function hasListeners(string $eventClass): bool
    {
        return isset($this->listeners[$eventClass]) && $this->listeners[$eventClass];
    }

    /**
     * Register a listener for a specific event type on this widget.
     *
     * The listener is only called when this specific widget dispatches the event.
     * Listeners are stored locally on the widget and live as long as it does:
     * taking the widget out of the tree and adding it back keeps them. Use
     * {@see off()} to release one.
     *
     * @param class-string<AbstractEvent> $eventClass The event class to listen for
     * @param callable                    $listener   The listener to invoke
     *
     * @return $this
     */
    final public function on(string $eventClass, callable $listener): static
    {
        $this->listeners[$eventClass][] = $listener;

        return $this;
    }

    /**
     * Remove listeners registered on this widget for a specific event type.
     *
     * Passing a listener removes every registration of it, comparing by
     * identity: the callable has to be the one that was passed to {@see on()},
     * which for an inline closure means the same instance. First-class
     * callables of the same method on the same object do match. Passing no
     * listener removes every listener the widget holds for the event class.
     *
     * @param class-string<AbstractEvent> $eventClass The event class to stop listening for
     * @param callable|null               $listener   The listener to remove, or null for all of them
     *
     * @return $this
     */
    final public function off(string $eventClass, ?callable $listener = null): static
    {
        if (null === $listener) {
            unset($this->listeners[$eventClass]);

            return $this;
        }

        $remaining = [];
        foreach ($this->listeners[$eventClass] ?? [] as $registered) {
            if ($registered !== $listener && !($listener instanceof \Closure && $registered == $listener)) {
                $remaining[] = $registered;
            }
        }

        if ($remaining) {
            $this->listeners[$eventClass] = $remaining;
        } else {
            unset($this->listeners[$eventClass]);
        }

        return $this;
    }

    /**
     * Return the cached render output if still valid.
     *
     * The cache is keyed on (renderRevision, columns, rows). A cache hit
     * means the widget's state and available dimensions have not changed
     * since the last render, so the Renderer can skip the entire pipeline
     * (style resolution, layout, chrome, content rendering).
     *
     * @internal Used by the Renderer
     *
     * @return LineBufferInterface|null Cached lines, or null on miss
     */
    final public function getRenderCache(int $columns, int $rows): ?LineBufferInterface
    {
        if ($this->renderCacheRevision === $this->getRenderRevision()
            && $this->renderCacheColumns === $columns
            && $this->renderCacheRows === $rows
        ) {
            return $this->renderCacheLines;
        }

        return $this->renderCacheRevision === $this->getRenderRevision() ? ($this->renderCacheOthers[$columns.':'.$rows][0] ?? null) : null;
    }

    /**
     * What the Renderer kept with the render cached in that size, see setRenderCache(): empty on a miss.
     *
     * @internal Used by the Renderer
     *
     * @return array<mixed>
     */
    final public function getRenderCacheExtra(int $columns, int $rows): array
    {
        if ($this->renderCacheRevision !== $this->getRenderRevision()) {
            return [];
        }
        if (null !== $this->renderCacheLines && $this->renderCacheColumns === $columns && $this->renderCacheRows === $rows) {
            return $this->renderCacheExtra;
        }

        return $this->renderCacheOthers[$columns.':'.$rows][1] ?? [];
    }

    /**
     * Store the render output for future cache lookups.
     *
     * @internal Used by the Renderer
     *
     * @param array<mixed> $extra What the render gives besides its lines, kept and dropped with them, see getRenderCacheExtra()
     */
    final public function setRenderCache(LineBufferInterface $lines, int $columns, int $rows, array $extra = []): void
    {
        // The render in another size of the same revision kept too, a few of them
        if (null !== $this->renderCacheLines && $this->renderCacheRevision === $this->getRenderRevision() && [$this->renderCacheColumns, $this->renderCacheRows] !== [$columns, $rows]) {
            unset($this->renderCacheOthers[$this->renderCacheColumns.':'.$this->renderCacheRows]);
            $this->renderCacheOthers[$this->renderCacheColumns.':'.$this->renderCacheRows] = [$this->renderCacheLines, $this->renderCacheExtra];
            if (\count($this->renderCacheOthers) > 3) {
                array_shift($this->renderCacheOthers);
            }
        } elseif ($this->renderCacheRevision !== $this->getRenderRevision()) {
            $this->renderCacheOthers = [];
        }
        unset($this->renderCacheOthers[$columns.':'.$rows]);
        $this->renderCacheLines = $lines;
        $this->renderCacheExtra = $extra;
        $this->renderCacheRevision = $this->getRenderRevision();
        $this->renderCacheColumns = $columns;
        $this->renderCacheRows = $rows;
    }

    /**
     * Records that the layout of its parent placed it among floats now, see floatsSetByLayout().
     */
    final protected function markFloatsSet(): void
    {
        $this->floatsSetBy = null === $this->parent ? null : [\WeakReference::create($this->parent), $this->parent->getRenderRevision()];
    }

    /**
     * Whether what the layout of its parent set about the floats beside it still holds: the same parent, unchanged since,
     * not one it moved to, nor one laid out again another way, like a flex container or with it out of the flow.
     */
    final protected function floatsSetByLayout(): bool
    {
        $set = $this->floatsSetBy;

        return null === $set ? null === $this->parent : $set[0]->get() === $this->parent && $set[1] === $this->parent?->getRenderRevision();
    }

    /**
     * Run a render that is not this widget's usual one, like a measure, without its render cache: what it renders is not
     * kept, and the renders kept before are kept after it.
     *
     * @template T
     *
     * @param \Closure(): T $render
     *
     * @return T
     *
     * @internal Used by the widgets and the Renderer
     */
    final public function withoutRenderCache(\Closure $render): mixed
    {
        $saved = [$this->renderCacheLines, $this->renderCacheRevision, $this->renderCacheColumns, $this->renderCacheRows, $this->renderCacheExtra, $this->renderCacheOthers];
        $this->clearRenderCache();
        try {
            return $render();
        } finally {
            [$this->renderCacheLines, $this->renderCacheRevision, $this->renderCacheColumns, $this->renderCacheRows, $this->renderCacheExtra, $this->renderCacheOthers] = $saved;
        }
    }

    /**
     * Clear the render cache without changing the render revision.
     *
     * Used by the layout engine to force a re-render for position tracking
     * after the measurement pass has already cached the output.
     *
     * @internal Used by the LayoutEngine
     */
    final public function clearRenderCache(): void
    {
        $this->renderCacheLines = null;
        $this->renderCacheExtra = [];
        $this->renderCacheOthers = [];
    }

    /**
     * Lifecycle hook: override to sync state before rendering.
     *
     * Called before the Renderer renders this widget, including when its own
     * render cache is valid. Use it to update child widget content, manage
     * overlays, or perform other pre-render state updates. Keep it lightweight;
     * heavy work should be guarded by dirty checks.
     *
     * This is not a per-frame tick: when an ancestor's cached subtree is reused,
     * this widget is not visited at all. Use {@see ScheduledTickTrait} for work
     * that must run on every frame.
     */
    public function beforeRender(): void
    {
    }

    /**
     * Render the widget content into terminal lines.
     *
     * The returned lines represent the widget's visual content, one array
     * element per terminal row. The Renderer calls this method with a
     * context whose dimensions already exclude chrome (padding, border).
     *
     * ## Contract
     *
     * - Lines MAY contain ANSI escape sequences for styling
     * - Lines MUST NOT exceed `$context->getColumns()` in visible width
     * - Lines MUST NOT contain newline characters (each element is one row)
     * - Empty strings are valid (blank rows)
     * - Image protocol sequences (Kitty/iTerm2) are exempt from the width constraint
     *
     * The Renderer validates the width constraint and throws a
     * {@see RenderException} if any line exceeds
     * the available columns.
     *
     * @return string[] One element per terminal row
     */
    abstract public function render(RenderContext $context): array;

    /**
     * Post-process the widget's finished lines, chrome included.
     *
     * The Renderer calls this with the fully rendered box: content, padding,
     * border and background are already applied. Override it to rewrite the
     * box as a whole, e.g. to paint a layer under it, dim it or mask it,
     * something render() cannot do because chrome is applied after it.
     *
     * The result is cached with the render, so a widget that animates its
     * post-processing must call invalidate() to produce a new frame.
     *
     * The width contract of render() applies to the returned lines too.
     *
     * @param list<string> $lines One element per terminal row
     *
     * @return list<string>
     */
    public function postRender(array $lines, RenderContext $context): array
    {
        return $lines;
    }

    /**
     * Makes a widget a child of this one, so it takes part in the render tree.
     *
     * Call it from a widget that owns other widgets, once the child is stored.
     * It is safe to call before this widget is attached itself: the child joins
     * the context along with its parent.
     */
    final protected function attachChild(self $child): void
    {
        $child->setParent($this);
        $this->getContext()?->attachChild($this, $child);
    }

    /**
     * Releases a widget previously passed to attachChild().
     */
    final protected function detachChild(self $child): void
    {
        $child->setParent(null);
        $this->getContext()?->detachChild($child);
    }

    /**
     * @internal
     */
    final protected function setParent(?self $parent): void
    {
        $this->parent = $parent;
        $this->invalidate();
    }

    protected function onAttach(WidgetContext $context): void
    {
    }

    protected function onDetach(): void
    {
    }

    /**
     * Resolve a sub-element style from the stylesheet.
     *
     * Sub-elements are parts within a widget that need independent styling
     * (e.g., "cursor", "selected", "description"). The stylesheet resolves
     * them using CSS pseudo-element syntax (::).
     *
     * Resolution order:
     * 1. FQCN::element
     * 2. .class::element
     * 3. FQCN::element:state (e.g., :focus)
     * 4. .class::element:state
     *
     * @see StyleSheet::resolveElement()
     */
    final protected function resolveElement(string $element): Style
    {
        $context = $this->getContext();
        if (null === $context) {
            return (self::$defaultStyleSheet ??= DefaultStyleSheet::create())->resolveElement($this, $element);
        }

        return $context->resolveElement($this, $element);
    }

    /**
     * Apply a sub-element style to text.
     *
     * Shorthand for `$this->resolveElement($element)->apply($text)`.
     */
    final protected function applyElement(string $element, string $text): string
    {
        if ('' === $text) {
            return $text;
        }

        $styled = $this->resolveElement($element)->apply($text);
        $style = $this->context?->resolveStyle($this);

        return $styled !== $text && $style?->isCssCascade() ? $styled.$style->getAnsiRestore() : $styled;
    }

    /**
     * Dispatch a widget event.
     *
     * Invokes per-widget listeners first (registered via {@see on()}),
     * then dispatches to the global EventDispatcher for listeners
     * registered via {@see Tui::on()}.
     *
     * Also requests a render after dispatching, since listeners typically
     * mutate UI state.
     */
    /**
     * Dispatches an event on this widget, to its listeners and to those of the application.
     *
     * @internal for the HTML view, which dispatches events on the widgets of its elements
     */
    final public function dispatchOn(AbstractEvent $event): void
    {
        $this->dispatch($event);
    }

    final protected function dispatch(AbstractEvent $event): void
    {
        // Per-widget listeners (no target check needed, they're already scoped)
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener($event);
        }

        $this->context?->dispatch($event);
    }
}
