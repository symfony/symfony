<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AppVariables is the entry point for Symfony global variables in Twig templates.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class GlobalVariables
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getSecurity()
    {
        if ($this->container->has('security.context')) {
            return $this->container->get('security.context');
        }
    }

    public function getUser()
    {
        $security = $this->getSecurity();
        if ($security && $user = $security->getUser()) {
            return $user;
        }
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
