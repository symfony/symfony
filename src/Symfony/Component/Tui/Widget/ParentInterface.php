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
 * Interface for widgets that have child widgets.
 *
 * This is a read-only interface for tree traversal. Use WidgetContainerInterface
 * when you need to add or remove children.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ParentInterface
{
    /**
     * Get all child widgets.
     *
     * @return AbstractWidget[]
     */
    public function all(): array;
}
