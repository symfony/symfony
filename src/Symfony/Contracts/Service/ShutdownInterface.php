<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Service;

/**
 * Resets an object and optionally releases its resources.
 *
 * When $shutdown is false, the object must remain usable, as required by ResetInterface.
 * When $shutdown is true, the object won't be used again.
 * Implementations can then close resources and break circular references.
 */
interface ShutdownInterface extends ResetInterface
{
    public function reset(bool $shutdown = false): void;
}
