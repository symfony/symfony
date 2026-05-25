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

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Input\Keybindings;

/**
 * Interface for widgets that can receive focus.
 *
 * Widgets that accept user input (text editors, inputs, lists) implement
 * this interface so the focus manager can route keyboard events to them.
 *
 * Widgets that display a text cursor should emit
 * {@see AnsiUtils::cursorMarker()} at the cursor position when focused
 * so the terminal's hardware cursor handles blinking natively and IME
 * candidate windows appear at the right spot.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface FocusableInterface
{
    /**
     * Check if the widget currently has focus.
     */
    public function isFocused(): bool;

    /**
     * Set the focus state of the widget.
     *
     * @return $this
     */
    public function setFocused(bool $focused): static;

    /**
     * Register a callback invoked before handleInput().
     *
     * The callback receives the raw input string and should return true
     * to consume the event (preventing handleInput() from processing it)
     * or false to let the widget handle it normally.
     *
     * @param (callable(string): bool)|null $callback
     *
     * @return $this
     */
    public function onInput(?callable $callback): static;

    /**
     * Handle keyboard/terminal input when focused.
     */
    public function handleInput(string $data): void;

    /**
     * Get the keybindings for this widget.
     *
     * Resolution order (later overrides earlier):
     * 1. Widget defaults (from getDefaultKeybindings())
     * 2. Global keybindings from the TUI (via WidgetContext)
     * 3. Explicit keybindings set on this widget (via setKeybindings())
     */
    public function getKeybindings(): Keybindings;

    /**
     * Set explicit keybindings for this widget.
     *
     * When set, these keybindings take priority over the TUI's default.
     *
     * @return $this
     */
    public function setKeybindings(?Keybindings $keybindings): static;
}
