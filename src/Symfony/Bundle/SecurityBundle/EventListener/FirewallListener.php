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
    private LogoutUrlGenerator $logoutUrlGenerator;

    /**
     * @param LogoutUrlGenerator $logoutUrlGenerator Passing the removed "EventDispatcherInterface $dispatcher" as 2nd argument (pre-8.2 signature) is deprecated since Symfony 8.2
     */
    public function __construct(
        private FirewallMapInterface $map,
        LogoutUrlGenerator|EventDispatcherInterface $logoutUrlGenerator,
    ) {
        if ($logoutUrlGenerator instanceof EventDispatcherInterface) {
            trigger_deprecation('symfony/security-bundle', '8.2', 'Passing an event dispatcher to "%s::__construct()" is deprecated, the argument will be removed in 9.0.', self::class);

            $logoutUrlGenerator = func_get_arg(2);
        }

        $this->logoutUrlGenerator = $logoutUrlGenerator;

        parent::__construct($map);
    }

    public function configureLogoutUrlGenerator(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->map instanceof FirewallMap && $config = $this->map->getFirewallConfig($event->getRequest())) {
            $this->logoutUrlGenerator->setCurrentFirewall($config->getName(), $config->getContext());
        }
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->logoutUrlGenerator->setCurrentFirewall(null);
        }

        parent::onKernelFinishRequest($event);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['configureLogoutUrlGenerator', 8],
                ['onKernelRequest', 8],
            ],
            KernelEvents::EXCEPTION => ['onKernelException', 1],
            KernelEvents::FINISH_REQUEST => 'onKernelFinishRequest',
        ];
    }
}
