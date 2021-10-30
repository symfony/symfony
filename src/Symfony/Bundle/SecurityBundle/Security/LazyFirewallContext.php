<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Security;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Http\Event\LazyResponseEvent;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;

/**
 * Lazily calls authentication listeners when actually required by the access listener.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class LazyFirewallContext extends FirewallContext
{
    private $tokenStorage;

    /**
     * @param iterable<mixed, callable> $listeners
     */
    public function __construct(iterable $listeners, ?ExceptionListener $exceptionListener, /*?FirewallConfig*/ $config, /*TokenStorage*/ $tokenStorage)
    {
        $arguments = \func_get_args();

        if (\count($arguments) > 4 && $arguments[4] instanceof TokenStorage) {
            trigger_deprecation('symfony/security-bundle', '5.4', 'Passing the LogoutListener as third argument is deprecated, add it to $listeners instead.', __METHOD__);

            $logoutListener = $arguments[2];
            $config = $arguments[3];
            $this->tokenStorage = $arguments[4];

            parent::__construct($listeners, $exceptionListener, $logoutListener, $config);

            return;
        }

        parent::__construct($listeners, $exceptionListener, $config);

        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getListeners(): iterable
    {
        return [$this];
    }

    public function __invoke(RequestEvent $event)
    {
        $listeners = [];
        $request = $event->getRequest();
        $lazy = $request->isMethodCacheable();

        foreach (parent::getListeners(false) as $listener) {
            if (!$lazy || !$listener instanceof FirewallListenerInterface) {
                $listeners[] = $listener;
                $lazy = $lazy && $listener instanceof FirewallListenerInterface;
            } elseif (false !== $supports = $listener->supports($request)) {
                $listeners[] = [$listener, 'authenticate'];
                $lazy = null === $supports;
            }
        }

        if (!$lazy) {
            foreach ($listeners as $listener) {
                $listener($event);

                if ($event->hasResponse()) {
                    return;
                }
            }

            return;
        }

        $this->tokenStorage->setInitializer(function () use ($event, $listeners) {
            $event = new LazyResponseEvent($event);
            foreach ($listeners as $listener) {
                $listener($event);
            }
        });
    }
}
