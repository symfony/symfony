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

    /**
     * Returns security context service
     * 
     * @return Symfony\Component\Security\Core\SecurityContext|void The security context
     */
    public function getSecurity()
    {
        if ($this->container->has('security.context')) {
            return $this->container->get('security.context');
        }
    }

    /**
     * Returns current user
     * 
     * @return mixed|void
     * @see Symfony\Component\Security\Core\Authentication\Token\TokenInterface::getUser()
     */
    public function getUser()
    {
        if (!$security = $this->getSecurity()) {
            return;
        }

        if (!$token = $security->getToken()) {
            return;
        }

        $user = $token->getUser();
        if (!is_object($user)) {
            return;
        }

        return $user;
    }

    /**
     * Returns security context service
     * 
     * @return Symfony\Component\HttpFoundation\Request|void The http request object
     */
    public function getRequest()
    {
        if ($this->container->has('request') && $request = $this->container->get('request')) {
            return $request;
        }
    }

    /**
     * Returns security context service
     * 
     * @return Symfony\Component\HttpFoundation\Session|void The session
     */
    public function getSession()
    {
        if ($request = $this->getRequest()) {
            return $request->getSession();
        }
    }
    
    /**
     * Returns current app environment
     * 
     * @return string|void The current environment string (e.g 'dev')
     */
    public function getEnvironment()
    {
        if ($this->container->hasParameter('kernel.environment')) {
            return $this->container->getParameter('kernel.environment');
        }
    }
    
    /**
     * Returns current app debug mode
     * 
     * @return boolean|void The current debug mode
     */
    public function getDebug()
    {
        if ($this->container->hasParameter('kernel.debug')) {
            return (bool)$this->container->getParameter('kernel.debug');
        }
    }
}
