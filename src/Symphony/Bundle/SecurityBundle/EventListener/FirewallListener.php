<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\EventListener;

use Symphony\Bundle\SecurityBundle\Security\FirewallMap;
use Symphony\Component\EventDispatcher\EventDispatcherInterface;
use Symphony\Component\HttpKernel\Event\FinishRequestEvent;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\Security\Http\Firewall;
use Symphony\Component\Security\Http\FirewallMapInterface;
use Symphony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class FirewallListener extends Firewall
{
    private $map;
    private $logoutUrlGenerator;

    public function __construct(FirewallMapInterface $map, EventDispatcherInterface $dispatcher, LogoutUrlGenerator $logoutUrlGenerator)
    {
        $this->map = $map;
        $this->logoutUrlGenerator = $logoutUrlGenerator;

        parent::__construct($map, $dispatcher);
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if ($this->map instanceof FirewallMap && $config = $this->map->getFirewallConfig($event->getRequest())) {
            $this->logoutUrlGenerator->setCurrentFirewall($config->getName(), $config->getContext());
        }

        parent::onKernelRequest($event);
    }

    public function onKernelFinishRequest(FinishRequestEvent $event)
    {
        if ($event->isMasterRequest()) {
            $this->logoutUrlGenerator->setCurrentFirewall(null);
        }

        parent::onKernelFinishRequest($event);
    }
}
