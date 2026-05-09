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

use Symfony\Component\Tui\Input\Keybindings;

/**
 * Default implementation of keybindings for focusable widgets.
 *
 * Resolution order (later overrides earlier):
 * 1. Widget defaults (from getDefaultKeybindings())
 * 2. Global keybindings from the TUI (via WidgetContext)
 * 3. Explicit keybindings set on this widget (via setKeybindings())
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait KeybindingsTrait
{
    private ?Keybindings $keybindings = null;

    /** @var (\Closure(string): bool)|null */
    private ?\Closure $onInput = null;

    /**
     * Return the effective keybindings for this widget.
     *
     * Resolution order (later overrides earlier):
     * 1. Widget defaults (from getDefaultKeybindings())
     * 2. Global keybindings from the TUI (via WidgetContext)
     * 3. Explicit keybindings set on this widget (via setKeybindings())
     */
    public function getKeybindings(): Keybindings
    {
        $context = $this->getContext()?->keybindings();
        $bindings = array_merge(
            static::getDefaultKeybindings(),
            $context?->all() ?? [],
            $this->keybindings?->all() ?? [],
        );

        return new Keybindings($bindings, $context?->getParser());
    }

    /**
     * @return $this
     */
    public function setKeybindings(?Keybindings $keybindings): static
    {
        $this->keybindings = $keybindings;

        return $this;
    }

    /**
     * @param (callable(string): bool)|null $callback
     */
    public function onInput(?callable $callback): static
    {
        $this->onInput = $callback ? $callback(...) : null;

        return $this;
    }

    /**
     * Return the default keybindings for this widget.
     *
     * Override in widgets that define their own actions.
     *
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return [];
    }
}
