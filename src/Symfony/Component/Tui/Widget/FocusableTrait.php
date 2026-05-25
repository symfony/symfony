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

/**
 * Default implementation of focus state for focusable widgets.
 *
 * Invalidates the widget when focus changes. Override setFocused()
 * for custom behavior (e.g. cursor blinker management).
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait FocusableTrait
{
    private bool $focused = false;

    public function isFocused(): bool
    {
        return $this->focused;
    }

    /**
     * @return $this
     */
    public function setFocused(bool $focused): static
    {
        if ($this->focused !== $focused) {
            $this->focused = $focused;
            $this->invalidate();
        }

        return $this;
    }
}
