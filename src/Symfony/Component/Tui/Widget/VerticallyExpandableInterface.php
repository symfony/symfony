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
 * Interface for widgets that can expand to fill available vertical space.
 *
 * When a widget implements this interface and vertical expansion is enabled,
 * it will expand to use available vertical space in its parent container.
 * In vertical layouts, multiple expanded siblings share the space equally.
 * In horizontal layouts, all children receive the full available height.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface VerticallyExpandableInterface
{
    /**
     * Set whether the widget should expand to fill available height.
     *
     * @return $this
     */
    public function expandVertically(bool $expand): static;

    /**
     * Check if the widget should expand to fill available height.
     */
    public function isVerticallyExpanded(): bool;
}
