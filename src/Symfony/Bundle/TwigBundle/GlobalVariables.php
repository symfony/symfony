<?php

namespace Symfony\Bundle\TwigBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * AppVariables is the entry point for Symfony global variables in Twig templates.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class GlobalVariables
{
    protected $container;

    // act as a cache to avoid calling the getters more than once
    // request related variables cannot be cached as we can have sub-requests
    public $security;
    public $user;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getSecurity()
    {
        if ($this->container->has('security.context')) {
            $this->security = $this->container->get('security.context');
        }

        return $this->security;
    }

    public function getUser()
    {
        if ($security = $this->getSecurity() && $user = $security->getUser()) {
            $this->user = $user;
        }

        return $this->user;
    }

    public function getRequest()
    {
        if ($this->container->has('request') && $request = $this->container->get('request')) {
            return $request;
        }
    }

    public function getSession()
    {
        if ($request = $this->getRequest()) {
            return $request->getSession();
        }
    }
}
