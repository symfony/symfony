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

    /** @var array<class-string<AbstractEvent>, list<callable>> */
    private array $listeners = [];

    // Render cache: stores the last output of Renderer::renderWidget()
    // keyed on (renderRevision, columns, rows) so unchanged widgets
    // skip style resolution, layout, chrome, and content rendering.

    /** @var string[]|null */
    private ?array $renderCacheLines = null;
    private int $renderCacheRevision = -1;
    private int $renderCacheColumns = -1;
    private int $renderCacheRows = -1;

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

        return $flags;
    }

    final public function invalidate(): void
    {
        ++$this->renderRevision;
        $this->renderCacheLines = null;

        if (null !== $this->parent) {
            $this->parent->invalidate();
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

        $this->listeners = [];

        $this->onDetach();
        $this->parent = null;
        $this->context = null;
    }

    /**
     * @return $this
     */
    final public function setStyle(?Style $style): static
    {
        if ($this->internalStyle !== $style) {
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
     * Listeners are stored locally on the widget and automatically cleared on detach.
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
     * Return the cached render output if still valid.
     *
     * The cache is keyed on (renderRevision, columns, rows). A cache hit
     * means the widget's state and available dimensions have not changed
     * since the last render, so the Renderer can skip the entire pipeline
     * (style resolution, layout, chrome, content rendering).
     *
     * @internal Used by the Renderer
     *
     * @return string[]|null Cached lines, or null on miss
     */
    final public function getRenderCache(int $columns, int $rows): ?array
    {
        if ($this->renderCacheRevision === $this->getRenderRevision()
            && $this->renderCacheColumns === $columns
            && $this->renderCacheRows === $rows
        ) {
            return $this->renderCacheLines;
        }

        return null;
    }

    /**
     * Store the render output for future cache lookups.
     *
     * @internal Used by the Renderer
     *
     * @param string[] $lines
     */
    final public function setRenderCache(array $lines, int $columns, int $rows): void
    {
        $this->renderCacheLines = $lines;
        $this->renderCacheRevision = $this->getRenderRevision();
        $this->renderCacheColumns = $columns;
        $this->renderCacheRows = $rows;
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
    }

    /**
     * Lifecycle hook: override to sync state before rendering.
     *
     * Called by the Renderer on every frame, even when the render cache is
     * valid. Use it to update child widget content, manage overlays, or
     * perform other pre-render state updates. Keep it lightweight; heavy
     * work should be guarded by dirty checks.
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

        return $this->resolveElement($element)->apply($text);
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
    final protected function dispatch(AbstractEvent $event): void
    {
        // Per-widget listeners (no target check needed, they're already scoped)
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener($event);
        }

        $this->context?->dispatch($event);
    }
}
