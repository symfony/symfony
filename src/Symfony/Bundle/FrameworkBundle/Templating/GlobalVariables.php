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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\SecurityContext;

/**
 * GlobalVariables is the entry point for Symfony global variables in Twig templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class GlobalVariables
{
    protected $container;

    /**
     * @param ContainerInterface $container The DI container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns the security context service.
     *
     * @deprecated Deprecated since version 2.6, to be removed in 3.0.
     * @return SecurityContext|null The security context
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
     * @return mixed
     *
     * @see TokenInterface::getUser()
     */
    public function getUser()
    {
        if (!$this->container->has('security.token_storage')) {
            return;
        }

        $tokenStorage = $this->container->get('security.token_storage');

        if (!$token = $tokenStorage->getToken()) {
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
     * @return Request|null The HTTP request object
     */
    public function getRequest()
    {
        if ($this->container->has('request_stack')) {
            return $this->container->get('request_stack')->getCurrentRequest();
        }
    }

    /**
     * Returns the current session.
     *
     * @return Session|null The session
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
     * @return bool The current debug mode
     */
    public function getDebug()
    {
        return (bool) $this->container->getParameter('kernel.debug');
    }
}
