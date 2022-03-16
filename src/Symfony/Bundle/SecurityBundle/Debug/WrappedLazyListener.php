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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Exception\LazyResponseException;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;

/**
 * Wraps a lazy security listener.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
final class WrappedLazyListener extends AbstractListener
{
    use TraceableListenerTrait;

    public function __construct(FirewallListenerInterface $listener)
    {
        $this->listener = $listener;
    }

    public function supports(Request $request): ?bool
    {
        return $this->listener->supports($request);
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(RequestEvent $event)
    {
        $startTime = microtime(true);

        try {
            $ret = $this->listener->authenticate($event);
        } catch (LazyResponseException $e) {
            $this->response = $e->getResponse();

            throw $e;
        } finally {
            $this->time = microtime(true) - $startTime;
        }

        $this->response = $event->getResponse();

        return $ret;
    }
}
