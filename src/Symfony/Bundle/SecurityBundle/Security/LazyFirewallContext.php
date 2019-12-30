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
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\LazyResponseException;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Event\LazyResponseEvent;
use Symfony\Component\Security\Http\Firewall\AccessListener;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\LogoutListener;

/**
 * Lazily calls authentication listeners when actually required by the access listener.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class LazyFirewallContext extends FirewallContext
{
    private $accessListener;
    private $tokenStorage;
    private $map;

    public function __construct(iterable $listeners, ?ExceptionListener $exceptionListener, ?LogoutListener $logoutListener, ?FirewallConfig $config, AccessListener $accessListener, TokenStorage $tokenStorage, AccessMapInterface $map)
    {
        parent::__construct($listeners, $exceptionListener, $logoutListener, $config);

        $this->accessListener = $accessListener;
        $this->tokenStorage = $tokenStorage;
        $this->map = $map;
    }

    public function getListeners(): iterable
    {
        return [$this];
    }

    public function __invoke(RequestEvent $event)
    {
        $this->tokenStorage->setInitializer(function () use ($event) {
            $event = new LazyResponseEvent($event);
            foreach (parent::getListeners() as $listener) {
                $listener($event);
            }
        });

        try {
            [$attributes] = $this->map->getPatterns($event->getRequest());

            if ($attributes && [AuthenticatedVoter::IS_AUTHENTICATED_ANONYMOUSLY] !== $attributes) {
                ($this->accessListener)($event);
            }
        } catch (LazyResponseException $e) {
            $event->setResponse($e->getResponse());
        }
    }
}
