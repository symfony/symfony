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

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Exception\LazyResponseException;
use Symfony\Component\Security\Http\Event\LazyResponseEvent;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\LazyAccessListener;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\Security\Http\Firewall\LogoutListener;

/**
 * Lazily calls authentication listeners when actually required by the access listener.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class LazyFirewallContext extends FirewallContext implements ListenerInterface
{
    private $accessListener;

    public function __construct(iterable $listeners, ?ExceptionListener $exceptionListener, ?LogoutListener $logoutListener, ?FirewallConfig $config, LazyAccessListener $accessListener)
    {
        parent::__construct($listeners, $exceptionListener, $logoutListener, $config);

        $this->accessListener = $accessListener;
    }

    public function getListeners(): iterable
    {
        return array($this);
    }

    public function handle(GetResponseEvent $event)
    {
        $this->accessListener->getTokenStorage()->setInitializer(function () use ($event) {
            $event = new LazyResponseEvent($event);
            foreach (parent::getListeners() as $listener) {
                $listener->handle($event);
            }
        });

        try {
            $this->accessListener->handle($event);
        } catch (LazyResponseException $e) {
            $event->setResponse($e->getResponse());
        }
    }
}
