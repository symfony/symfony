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
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

/**
 * Wraps a security listener for calls record.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal since Symfony 4.3
 */
final class WrappedListener implements ListenerInterface
{
    use TraceableListenerTrait;

    /**
     * @param callable $listener
     */
    public function __construct($listener)
    {
        $this->listener = $listener;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(RequestEvent $event)
    {
        $startTime = microtime(true);
        if (\is_callable($this->listener)) {
            ($this->listener)($event);
        } else {
            @trigger_error(sprintf('Calling the "%s::handle()" method from the firewall is deprecated since Symfony 4.3, extend "%s" instead.', \get_class($this->listener), AbstractListener::class), \E_USER_DEPRECATED);
            $this->listener->handle($event);
        }
        $this->time = microtime(true) - $startTime;
        $this->response = $event->getResponse();
    }
}
