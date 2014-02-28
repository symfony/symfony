<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug;

/**
 * An ExceptionHandler does something useful with an exception.
 *
 * @author Andrew Moore <me@andrewmoore.ca>
 */
interface ExceptionHandlerInterface
{
    /**
     * Handles an exception.
     *
     * @param \Exception $exception An \Exception instance
     */
    public function handle(\Exception $exception);
}
