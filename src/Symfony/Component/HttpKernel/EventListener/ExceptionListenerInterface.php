<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * ExceptionListenerInterface handles kernel exception event.
 */
interface ExceptionListenerInterface
{
    /**
     * Handle some type of exception and create an appropriate Response to return for the exception.
     *
     * @param ExceptionEvent $event
     *
     * @return mixed
     */
    public function onKernelException(ExceptionEvent $event);
}
