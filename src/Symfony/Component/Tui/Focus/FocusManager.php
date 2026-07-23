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

            if (null === $this->focused) {
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
            $next = $this->focusables[0] ?? null;
            $this->setFocus($next);
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
        // Only handle focus navigation when there are multiple focusables
        if (\count($this->focusables) <= 1) {
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
        if (!$count = \count($this->focusables)) {
            return null;
        }

        if (false === $index = array_search($this->focused, $this->focusables, true)) {
            $index = -1;
        } else {
            $index = (int) $index;
        }

        $nextIndex = ($index + 1) % $count;
        $next = $this->focusables[$nextIndex];
        $this->setFocus($next);

        return $next;
    }

    public function focusPrevious(): ?FocusableInterface
    {
        if (!$count = \count($this->focusables)) {
            return null;
        }

        if (false === $index = array_search($this->focused, $this->focusables, true)) {
            $index = 0;
        } else {
            $index = (int) $index;
        }

        $previousIndex = ($index - 1 + $count) % $count;
        $previous = $this->focusables[$previousIndex];
        $this->setFocus($previous);

        return $previous;
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
