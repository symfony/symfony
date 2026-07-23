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
 * Interface for widgets that can contain and mutate child widgets.
 *
 * Extends ParentInterface with mutation methods (add, remove, clear).
 * Use ParentInterface when you only need read-only tree traversal.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface WidgetContainerInterface extends ParentInterface
{
    /**
     * @return $this
     */
    public function add(AbstractWidget $widget): static;

    /**
     * @return $this
     */
    public function remove(AbstractWidget $widget): static;

    /**
     * Remove all child widgets.
     *
     * @return $this
     */
    public function clear(): static;
}
