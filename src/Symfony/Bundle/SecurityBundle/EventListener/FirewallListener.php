<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\EventListener;

use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class FirewallListener extends Firewall
{
    private FirewallMapInterface $map;
    private LogoutUrlGenerator $logoutUrlGenerator;

    public function __construct(FirewallMapInterface $map, EventDispatcherInterface $dispatcher, LogoutUrlGenerator $logoutUrlGenerator)
    {
        $this->map = $map;
        $this->logoutUrlGenerator = $logoutUrlGenerator;

        parent::__construct($map, $dispatcher);
    }

    public function configureLogoutUrlGenerator(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->map instanceof FirewallMap && $config = $this->map->getFirewallConfig($event->getRequest())) {
            $this->logoutUrlGenerator->setCurrentFirewall($config->getName(), $config->getContext());
        }
    }

    public function onKernelFinishRequest(FinishRequestEvent $event)
    {
        if ($event->isMainRequest()) {
            $this->logoutUrlGenerator->setCurrentFirewall(null);
        }

        parent::onKernelFinishRequest($event);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['configureLogoutUrlGenerator', 8],
                ['onKernelRequest', 8],
            ],
            KernelEvents::FINISH_REQUEST => 'onKernelFinishRequest',
        ];
    }
}
