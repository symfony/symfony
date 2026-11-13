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
 * A widget with a default action, like pressing a button or toggling a box.
 *
 * Pointer handlers call it on click, the way a browser activates an element.
 *
 * @experimental
 */
interface ActivatableInterface
{
    /**
     * Performs the default action.
     *
     * @return bool Whether something happened: false when the widget is disabled, for instance
     */
    public function activate(): bool;
}
