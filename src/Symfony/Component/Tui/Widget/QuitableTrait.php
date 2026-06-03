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

use Symfony\Component\Tui\Event\QuitEvent;

/**
 * Trait for widgets that support a quit action.
 *
 * Dispatches a {@see QuitEvent} when the quit key is pressed.
 * If no listener is registered for QuitEvent (neither globally on the
 * Tui nor locally on the widget), the default behavior is to stop the TUI.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait QuitableTrait
{
    /**
     * Register a listener for the quit event on this widget.
     *
     * @param callable(QuitEvent): void $callback
     *
     * @return $this
     */
    public function onQuit(callable $callback): static
    {
        return $this->on(QuitEvent::class, $callback);
    }

    /**
     * Dispatch the quit event.
     *
     * Call this from handleInput() when quit key is pressed.
     * If no listener is registered for QuitEvent, stops the TUI.
     */
    protected function dispatchQuit(): void
    {
        $context = $this->getContext();
        if (null === $context) {
            return;
        }

        $hasListeners = $context->getEventDispatcher()->hasListeners(QuitEvent::class)
            || $this->hasListeners(QuitEvent::class);

        if ($hasListeners) {
            $this->dispatch(new QuitEvent($this));
        } else {
            // Default behavior: stop the TUI
            $context->stop();
        }
    }
}
