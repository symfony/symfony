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

    /** How many of the focusables have a positive tab index */
    private int $positiveTabIndexes = 0;

    private ?FocusPolicyInterface $policy = null;

    /** Whether a widget takes the focus by itself when nothing has it, see setTabStart() */
    private bool $autoFocus = true;

    /** @var \WeakReference<AbstractWidget>|null The widget that lost the focus by no longer taking it, where the next Tab goes on from, see revalidateFocus() */
    private ?\WeakReference $startingPoint = null;

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
     * Set the position of a widget in the focus cycle.
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
        // Counted to keep the order of addition, and focus moves that walk from the focused widget, without any
        if (\in_array($widget, $this->focusables, true)) {
            $this->positiveTabIndexes += ($tabIndex > 0 ? 1 : 0) - (($this->tabIndexes[$widget] ?? 0) > 0 ? 1 : 0);
        }
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
        if (null !== $widget) {
            $this->startingPoint = null;
        }

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

    /**
     * Blurs the focused widget when it no longer takes the focus: when it has the disabled state, or the focus policy
     * refuses it now, like a hidden one. The next Tab goes on from where it was.
     *
     * Tui calls it when it selects another renderer, after each frame it draws, and when the focused widget gets the
     * disabled state.
     *
     * @return bool Whether a widget still has the focus
     */
    public function revalidateFocus(): bool
    {
        if (null === $this->focused) {
            return false;
        }
        if (!$this->focused->hasState(AbstractWidget::STATE_DISABLED) && $this->allowsFocus($this->focused)) {
            return true;
        }
        // Set before the focus event, for the listeners that move the focus, which clears it
        $this->startingPoint = \WeakReference::create($this->focused);
        $this->setFocus(null);

        return false;
    }

    /**
     * Leaves the focus to the first Tab: no widget takes it by itself, neither the first one added nor the next one when
     * the focused one is removed. False gives it back to the first focusable, the default.
     */
    public function setTabStart(bool $tabStart = true): void
    {
        $this->autoFocus = !$tabStart;
    }

    /**
     * Whether the focus is left to the first Tab, see setTabStart().
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
            if (($this->tabIndexes[$widget] ?? 0) > 0) {
                ++$this->positiveTabIndexes;
            }

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
            if (($this->tabIndexes[$widget] ?? 0) > 0) {
                --$this->positiveTabIndexes;
            }
        }

        if ($this->focused === $widget) {
            $this->setFocus($this->autoFocus ? $this->allowed($this->candidates(), 1)[0] ?? null : null);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function clear(): static
    {
        $this->focusables = [];
        $this->positiveTabIndexes = 0;
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
        if ($this->keybindings->matches($data, 'focus_next')) {
            $step = 1;
        } elseif ($this->keybindings->matches($data, 'focus_previous')) {
            $step = -1;
        } else {
            return false;
        }

        // Only handle focus navigation when there are multiple focusables, or one without the focus yet, like a form
        // nobody went through yet, which the first Tab goes into
        $candidates = $this->candidates();
        $count = \count($this->allowed($candidates, 2));
        if (0 === $count || (1 === $count && null !== $this->focused)) {
            return false;
        }
        $this->move($step, $candidates);

        return true;
    }

    public function focusNext(): ?FocusableInterface
    {
        return $this->move(1);
    }

    public function focusPrevious(): ?FocusableInterface
    {
        return $this->move(-1);
    }

    /**
     * Focuses the next widget of the cycle in a direction, from the focused one, or from an end without one in the
     * cycle: the widgets are asked whether they take the focus only up to the one that does, not all of them on each
     * Tab, which resolves the styles of each one and of its parents.
     *
     * @param list<FocusableInterface&AbstractWidget>|null $candidates
     */
    private function move(int $step, ?array $candidates = null): ?FocusableInterface
    {
        // From the focused widget, or the one that lost the focus, in the cycle or not, or else from an end
        $from = $this->focused ?? $this->startingPoint?->get();
        if (null !== $from && (null === $candidates || !\in_array($from, $candidates, true))) {
            $candidates = $this->candidates($from);
        }
        $candidates ??= $this->candidates();
        if (!$count = \count($candidates)) {
            return null;
        }
        $index = null !== $from ? array_search($from, $candidates, true) : false;
        if (false === $index) {
            $index = 1 === $step ? -1 : $count;
        }
        for ($i = 1; $i <= $count; ++$i) {
            $candidate = $candidates[(($index + $i * $step) % $count + $count) % $count];
            if ($this->isCandidate($candidate) && $this->allowsFocus($candidate)) {
                $this->setFocus($candidate);

                return $candidate;
            }
        }

        return null;
    }

    /**
     * Returns the widgets F6 cycles through, in order.
     *
     * The widgets with a negative tab index or the disabled state are
     * skipped, and those the focus policy of the renderer refuses, or without
     * one, those hidden by their style.
     *
     * @return list<FocusableInterface&AbstractWidget>
     */
    public function getCycle(): array
    {
        return $this->allowed($this->candidates());
    }

    /**
     * The first widgets of the candidates that take the focus, all of them without a limit.
     *
     * @param list<FocusableInterface&AbstractWidget> $candidates
     *
     * @return list<FocusableInterface&AbstractWidget>
     */
    private function allowed(array $candidates, ?int $limit = null): array
    {
        $allowed = [];
        foreach ($candidates as $widget) {
            if (!$this->isCandidate($widget) || !$this->allowsFocus($widget)) {
                continue;
            }
            $allowed[] = $widget;
            if (\count($allowed) === $limit) {
                break;
            }
        }

        return $allowed;
    }

    /**
     * The widgets of the cycle in order, before asking whether they take the focus: those without a negative tab index
     * nor the disabled state, and the one to keep in its place whatever its state, to go on from.
     *
     * Without a positive tab index nor a policy to reorder them, they are all the focusables in the order they were
     * added, the others left to isCandidate(): a focus move then only checks the widgets it goes through.
     *
     * @return list<FocusableInterface&AbstractWidget>
     */
    private function candidates(?AbstractWidget $keep = null): array
    {
        if (0 === $this->positiveTabIndexes && null === $this->policy) {
            return $this->focusables;
        }
        $positive = [];
        $default = [];
        foreach ($this->focusables as $position => $widget) {
            if ($widget !== $keep && !$this->isCandidate($widget)) {
                continue;
            }
            $tabIndex = $this->tabIndexes[$widget] ?? 0;
            if ($tabIndex > 0) {
                $positive[] = [$tabIndex, $position, $widget];
            } else {
                $default[] = $widget;
            }
        }

        if ($positive) {
            usort($positive, static fn (array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $positive = array_column($positive, 2);
        }

        return [...$positive, ...($this->policy?->orderFocusCycle($default) ?? $default)];
    }

    /**
     * Sets what decides which widgets take the focus and in what order, see FocusPolicyInterface.
     *
     * @internal Set by Tui from its renderer
     */
    public function setPolicy(?FocusPolicyInterface $policy): void
    {
        $this->policy = $policy;
    }

    private function isInCycle(AbstractWidget $widget): bool
    {
        return $this->isCandidate($widget) && $this->allowsFocus($widget);
    }

    private function isCandidate(AbstractWidget $widget): bool
    {
        return ($this->tabIndexes[$widget] ?? 0) >= 0 && !$widget->hasState(AbstractWidget::STATE_DISABLED);
    }

    /**
     * What the focus policy says, or without one, whether neither the widget nor any of its parents is hidden by its style.
     */
    private function allowsFocus(AbstractWidget $widget): bool
    {
        if (null !== $this->policy) {
            return $this->policy->allowsFocus($widget);
        }
        for ($current = $widget; null !== $current; $current = $current->getParent()) {
            if ($current->getContext()?->resolveStyle($current)->getHidden()) {
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
