<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * GlobalVariables is the entry point for Symfony global variables in Twig templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class GlobalVariables
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns the security context service.
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
     * Returns the current user.
     *
     * @return mixed|void
     *
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
     * Returns the current request.
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
     * Returns the current session.
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
     * Returns the current app environment.
     *
     * @return string The current environment string (e.g 'dev')
     */
    public function getEnvironment()
    {
        return $this->container->getParameter('kernel.environment');
    }

    /**
     * Returns the current app debug mode.
     *
     * @return Boolean The current debug mode
     */
    public function getDebug()
    {
        return (Boolean) $this->container->getParameter('kernel.debug');
    }
}
