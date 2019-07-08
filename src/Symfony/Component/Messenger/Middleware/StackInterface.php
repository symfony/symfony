<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * Implementations must be cloneable, and each clone must unstack the stack independently.
 */
interface StackInterface
{
    /**
     * Returns the next middleware to process a message.
     */
    public function next(): MiddlewareInterface;
}
