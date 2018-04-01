<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Security;

use Symphony\Component\Security\Http\Firewall\ExceptionListener;

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

    public function __construct(iterable $listeners, ExceptionListener $exceptionListener = null, FirewallConfig $config = null)
    {
        $this->listeners = $listeners;
        $this->exceptionListener = $exceptionListener;
        $this->config = $config;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getListeners(): iterable
    {
        return $this->listeners;
    }

    public function getExceptionListener()
    {
        return $this->exceptionListener;
    }
}
