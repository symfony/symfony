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

use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;
use Symfony\Component\Security\Http\Firewall\LogoutListener;

/**
 * This is a wrapper around the actual firewall configuration which allows us
 * to lazy load the context for one specific firewall only when we need it.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class FirewallContext
{
    /**
     * @param iterable<mixed, FirewallListenerInterface> $listeners
     */
    public function __construct(
        private iterable $listeners,
        private ?ExceptionListener $exceptionListener = null,
        private ?LogoutListener $logoutListener = null,
        private ?FirewallConfig $config = null,
    ) {
    }

    public function getConfig(): ?FirewallConfig
    {
        return $this->config;
    }

    /**
     * @return iterable<mixed, FirewallListenerInterface>
     */
    public function getListeners(): iterable
    {
        return $this->listeners;
    }

    public function getExceptionListener(): ?ExceptionListener
    {
        return $this->exceptionListener;
    }

    public function getLogoutListener(): ?LogoutListener
    {
        return $this->logoutListener;
    }
}
