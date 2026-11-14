<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Focus;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Tui\Event\FocusEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\RenderRequestorInterface;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;

/**
 * Owns the focused-widget state and handles focus navigation.
 *
 * Default bindings: F6 (next) and Shift+F6 (previous).
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FocusManager
{
    private ?FocusPolicyInterface $policy = null;
    private const DEFAULT_BINDINGS = [
        'focus_next' => [Key::F6],
        'focus_previous' => ['shift+f6'],
    ];

    private ?AbstractWidget $focused = null;

    /** @var array<int, FocusableInterface&AbstractWidget> */
    private array $focusables = [];

    private Keybindings $keybindings;

    /** @var \WeakMap<AbstractWidget, int> */
    private \WeakMap $tabIndexes;

    /**
     * @internal constructed by the framework; access via {@see WidgetContext::getFocusManager()}
     */
    public function __construct(
        private readonly RenderRequestorInterface $renderRequestor,
        ?Keybindings $keybindings = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $bindings = array_merge(self::DEFAULT_BINDINGS, $keybindings?->all() ?? []);
        $this->keybindings = new Keybindings($bindings, $keybindings?->getParser());
        $this->tabIndexes = new \WeakMap();
    }

    /**
     * Set the position of a widget in the focus cycle, like HTML tabindex.
     *
     * Widgets with a positive index come first, in ascending order, then
     * the ones with index 0 (the default) in the order they were added.
     * A negative index takes the widget out of the cycle: it can still be
     * focused with setFocus().
     *
     * @return $this
     */
    public function setTabIndex(AbstractWidget $widget, int $tabIndex): static
    {
        $this->tabIndexes[$widget] = $tabIndex;

        return $this;
    }

    public function getTabIndex(AbstractWidget $widget): int
    {
        $index = $this->tabIndexes[$widget] ?? null;

        return null !== $index ? $index : 0;
    }

    /**
     * Set the focused widget.
     *
     * Clears the focused flag on the previous widget, sets it on the
     * new one, and fires the onFocusChanged callback.
     */
    public function setFocus(?AbstractWidget $widget): void
    {
        if ($this->focused === $widget) {
            return;
        }

        $previous = $this->focused;

        if ($this->focused instanceof FocusableInterface) {
            $this->focused->setFocused(false);
        }

        $this->focused = $widget;

        if ($widget instanceof FocusableInterface) {
            $widget->setFocused(true);
        }

        $this->notifyFocusChanged($widget, $previous);
        $this->renderRequestor->requestRender();
    }

    /** Whether a widget takes the focus by itself when nothing has it, see setTabStart() */
    private bool $autoFocus = true;

    /**
     * Leaves the focus to the first Tab, like browsers on a page: nothing takes it by itself, the first widget attached
     * nor the next one when the focused one goes away or is hidden.
     * False gives it back to the first focusable, like before.
     *
     * @internal
     */
    public function setTabStart(bool $tabStart = true): void
    {
        $this->autoFocus = !$tabStart;
    }

    /**
     * Whether the focus is left to the first Tab, see setTabStart().
     *
     * @internal
     */
    public function isTabStart(): bool
    {
        return !$this->autoFocus;
    }

    /**
     * Get the currently focused widget.
     */
    public function getFocus(): ?AbstractWidget
    {
        return $this->focused;
    }

    /**
     * @return $this
     */
    public function add(FocusableInterface&AbstractWidget $widget): static
    {
        if (!\in_array($widget, $this->focusables, true)) {
            $this->focusables[] = $widget;

            if ($this->autoFocus && null === $this->focused && $this->isInCycle($widget)) {
                $this->setFocus($widget);
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function remove(FocusableInterface&AbstractWidget $widget): static
    {
        if (false !== $index = array_search($widget, $this->focusables, true)) {
            array_splice($this->focusables, $index, 1);
        }

        if ($this->focused === $widget) {
            $this->setFocus($this->autoFocus ? $this->getCycle()[0] ?? null : null);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function clear(): static
    {
        $this->focusables = [];
        $this->setFocus(null);

        return $this;
    }

    /**
     * @return FocusableInterface[]
     */
    public function all(): array
    {
        return $this->focusables;
    }

    /**
     * Register a listener for focus change events.
     *
     * @param callable(FocusEvent): void $callback
     *
     * @return $this
     */
    public function onFocusChanged(callable $callback): static
    {
        $this->eventDispatcher?->addListener(FocusEvent::class, $callback);

        return $this;
    }

    public function handleInput(string $data): bool
    {
        // Only handle focus navigation when there are multiple focusables, or one without the focus yet, like a page
        // nobody went through yet, which the first Tab goes into
        $count = \count($this->getCycle());
        if (0 === $count || (1 === $count && null !== $this->focused)) {
            return false;
        }

        if ($this->keybindings->matches($data, 'focus_next')) {
            $this->focusNext();

            return true;
        }

        if ($this->keybindings->matches($data, 'focus_previous')) {
            $this->focusPrevious();

            return true;
        }

        return false;
    }

    public function focusNext(): ?FocusableInterface
    {
        $cycle = $this->getCycle();
        if (!$count = \count($cycle)) {
            return null;
        }

        if (false === $index = array_search($this->focused, $cycle, true)) {
            $index = -1;
        } else {
            $index = (int) $index;
        }

        $nextIndex = ($index + 1) % $count;
        $next = $cycle[$nextIndex];
        $this->setFocus($next);

        return $next;
    }

    public function focusPrevious(): ?FocusableInterface
    {
        $cycle = $this->getCycle();
        if (!$count = \count($cycle)) {
            return null;
        }

        if (false === $index = array_search($this->focused, $cycle, true)) {
            $index = 0;
        } else {
            $index = (int) $index;
        }

        $previousIndex = ($index - 1 + $count) % $count;
        $previous = $cycle[$previousIndex];
        $this->setFocus($previous);

        return $previous;
    }

    /**
     * Returns the widgets F6 cycles through, in order.
     *
     * Disabled widgets and the ones with a negative tab index are skipped.
     *
     * @return list<FocusableInterface&AbstractWidget>
     */
    public function getCycle(): array
    {
        $positive = [];
        $default = [];
        foreach ($this->focusables as $position => $widget) {
            if (!$this->isInCycle($widget)) {
                continue;
            }
            $tabIndex = $this->tabIndexes[$widget] ?? 0;
            if ($tabIndex > 0) {
                $positive[] = [$tabIndex, $position, $widget];
            } else {
                $default[] = $widget;
            }
        }

        usort($positive, static fn (array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return [...array_column($positive, 2), ...$this->inReadingFlow($default)];
    }

    /**
     * The widgets in the order of the reading flows around them, like CSS reading-flow: the ones inside a reading-flow
     * container are ordered again among the places they take.
     *
     * @param list<FocusableInterface&AbstractWidget> $widgets
     *
     * @return list<FocusableInterface&AbstractWidget>
     */
    private function inReadingFlow(array $widgets): array
    {
        if (null === $this->policy) {
            return $widgets;
        }
        // The outermost reading-flow container of each widget
        $groups = [];
        foreach ($widgets as $index => $widget) {
            $container = null;
            for ($current = $widget->getParent(); null !== $current; $current = $current->getParent()) {
                if ([] !== $this->policy->getReadingOrder($current, $widget)) {
                    $container = $current;
                }
            }
            if (null !== $container) {
                $groups[spl_object_id($container)][0] = $container;
                $groups[spl_object_id($container)][1][] = $index;
            }
        }

        foreach ($groups as [$container, $indexes]) {
            $keys = [];
            foreach ($indexes as $index) {
                $keys[$index] = $this->readingKey($container, $widgets[$index]);
            }
            $sorted = $indexes;
            usort($sorted, static fn (int $a, int $b) => self::compareKeys($keys[$a], $keys[$b]) ?: $a <=> $b);
            $placed = $widgets;
            foreach ($indexes as $i => $index) {
                $placed[$index] = $widgets[$sorted[$i]];
            }
            $widgets = $placed;
        }

        return $widgets;
    }

    /**
     * Compares reading keys item by item, a shorter one first when it starts the other.
     *
     * @param list<int|float> $a
     * @param list<int|float> $b
     */
    private static function compareKeys(array $a, array $b): int
    {
        foreach ($a as $i => $value) {
            if (!isset($b[$i])) {
                return 1;
            }
            if (0 !== $order = $value <=> $b[$i]) {
                return $order;
            }
        }

        return \count($a) <=> \count($b);
    }

    public function setPolicy(?FocusPolicyInterface $policy): void
    {
        $this->policy = $policy;
    }

    private function readingKey(AbstractWidget $container, AbstractWidget $widget): array
    {
        return $this->policy?->getReadingOrder($container, $widget) ?? [];
    }

    private function isInCycle(AbstractWidget $widget): bool
    {
        return ($this->tabIndexes[$widget] ?? 0) >= 0 && !\in_array('disabled', $widget->getStateFlags(), true) && $this->isDrawn($widget);
    }

    /**
     * Moves the focus off a widget that is not drawn, like one CSS hides once styled, to the first one of the cycle,
     * not text with links, like the first Tab of a page, which would show a link as current, unclipping a skip link
     * that shows on focus; to none without another.
     *
     * @internal
     *
     * @return bool Whether the focus moved
     */
    public function leaveUndrawn(): bool
    {
        if (null === $this->focused || null === $this->focused->getContext() || $this->isDrawn($this->focused)) {
            return false;
        }
        $this->setFocus($this->autoFocus ? array_values(array_filter($this->getCycle(), fn (AbstractWidget $widget) => $this->policy?->allowsFocus($widget, true) ?? true))[0] ?? null : null);

        return true;
    }

    /**
     * A widget hidden by its style, or inside a hidden one or one whose content is hidden, cannot take the focus.
     */
    private function isDrawn(AbstractWidget $widget): bool
    {
        if (null !== $this->policy) {
            return $this->policy->allowsFocus($widget);
        }
        for ($current = $widget; null !== $current; $current = $current->getParent()) {
            if ($current->getContext()?->resolveStyle($current)->getHidden() || $current->hasState('inert')) {
                return false;
            }
        }

        return true;
    }

    private function notifyFocusChanged(?AbstractWidget $focused, ?AbstractWidget $previous): void
    {
        if (null === $focused || $focused === $previous) {
            return;
        }

        if (!$focused instanceof FocusableInterface) {
            return;
        }

        $this->eventDispatcher?->dispatch(new FocusEvent(
            $focused,
            $previous instanceof FocusableInterface ? $previous : null,
        ));
    }
}
