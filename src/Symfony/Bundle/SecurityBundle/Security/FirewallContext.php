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
use Symfony\Component\Security\Http\Firewall\LogoutListener;

/**
 * This is a wrapper around the actual firewall configuration which allows us
 * to lazy load the context for one specific firewall only when we need it.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class FirewallContext
{
    private $listeners;
    private $exceptionListener;
    private $config;
    private $logoutListener = null;

    /**
     * @param iterable<mixed, callable> $listeners
     */
    public function __construct(iterable $listeners, ExceptionListener $exceptionListener = null, /*FirewallConfig*/ $config = null)
    {
        if ($config instanceof LogoutListener) {
            trigger_deprecation('symfony/security-bundle', '5.4', 'Passing the LogoutListener as third argument is deprecated, add it to $listeners instead.', __METHOD__);
            $this->logoutListener = $config;
            $config = \func_num_args() > 3 ? func_get_arg(3) : null;
        }

        $this->listeners = $listeners;
        $this->exceptionListener = $exceptionListener;
        $this->config = $config;
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return iterable<mixed, callable>
     *
     * @deprecated since Symfony 5.4, use "getFirewallListeners()" instead
     */
    public function getListeners(): iterable
    {
        if (0 === \func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/security-bundle', '5.4', 'The %s() method is deprecated, use getFirewallListeners() instead.', __METHOD__);
        }

        // Ensure LogoutListener is not included
        foreach ($this->listeners as $listener) {
            if (!($listener instanceof LogoutListener)) {
                yield $listener;
            }
        }
    }

    /**
     * @return iterable<mixed, callable>
     */
    public function getFirewallListeners(): iterable
    {
        $containedLogoutListener = false;
        foreach ($this->listeners as $listener) {
            yield $listener;
            $containedLogoutListener |= $listener instanceof LogoutListener;
        }

        // Ensure the LogoutListener is contained
        if (null !== $this->logoutListener && !$containedLogoutListener) {
            yield $this->logoutListener;
        }
    }

    public function getExceptionListener(): ?ExceptionListener
    {
        return $this->exceptionListener;
    }

    /**
     * @deprecated since Symfony 5.4, use "getFirewallListeners()" instead
     */
    public function getLogoutListener()
    {
        if (0 === \func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/security-bundle', '5.4', 'The %s() method is deprecated, use getFirewallListeners() instead.', __METHOD__);
        }

        if (null !== $this->logoutListener) {
            return $this->logoutListener;
        }

        // Return LogoutListener from listeners list
        foreach ($this->listeners as $listener) {
            if ($listener instanceof LogoutListener) {
                return $listener;
            }
        }

        return null;
    }
}
