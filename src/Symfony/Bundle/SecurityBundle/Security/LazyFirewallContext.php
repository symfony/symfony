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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Http\Event\LazyResponseEvent;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;
use Symfony\Component\Security\Http\Firewall\LogoutListener;

/**
 * Lazily calls authentication listeners when actually required by the access listener.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class LazyFirewallContext extends FirewallContext implements FirewallListenerInterface
{
    public function __construct(
        iterable $listeners,
        ?ExceptionListener $exceptionListener,
        ?LogoutListener $logoutListener,
        ?FirewallConfig $config,
        private TokenStorage $tokenStorage,
    ) {
        parent::__construct($listeners, $exceptionListener, $logoutListener, $config);
    }

    public function getListeners(): iterable
    {
        return [$this];
    }

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(RequestEvent $event): void
    {
        $listeners = [];
        $request = $event->getRequest();
        $lazy = $request->isMethodCacheable();

        foreach (parent::getListeners() as $listener) {
            if (!$listener instanceof FirewallListenerInterface) {
                trigger_deprecation('symfony/security-http', '7.4', 'Using a callable as firewall listener is deprecated, extend "%s" or implement "%s" instead.', AbstractListener::class, FirewallListenerInterface::class);

                $listeners[] = $listener;
                $lazy = false;
            } elseif (false !== $supports = $listener->supports($request)) {
                $listeners[] = [$listener, 'authenticate'];
                $lazy = $lazy && null === $supports;
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

    public static function getPriority(): int
    {
        return 0;
    }

    /**
     * @deprecated since Symfony 7.4, to be removed in 8.0
     */
    public function __invoke(RequestEvent $event): void
    {
        trigger_deprecation('symfony/security-bundle', '7.4', 'The "%s()" method is deprecated since Symfony 7.4 and will be removed in 8.0.', __METHOD__);

        $this->authenticate($event);
    }
}
