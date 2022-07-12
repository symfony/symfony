<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter;

interface LazyGhostObjectInterface
{
    /**
     * Forces initialization of a lazy ghost object.
     */
    public function initializeLazyGhostObject(): void;

    /**
     * @return bool Returns false when the object cannot be reset, ie when it's not a ghost object
     */
    public function resetLazyGhostObject(): bool;
}
