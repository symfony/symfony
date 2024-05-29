<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Debug;

use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Wraps a security listener for calls record.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
final class WrappedListener
{
    use TraceableListenerTrait;

    /**
     * @param callable(RequestEvent):void $listener
     */
    public function __construct(callable $listener)
    {
        $this->listener = $listener;
    }

    public function __invoke(RequestEvent $event): void
    {
        $startTime = microtime(true);
        ($this->listener)($event);
        $this->time = microtime(true) - $startTime;
        $this->response = $event->getResponse();
    }
}
