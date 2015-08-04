<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes some Symfony parameters and services as an "app" global variable.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AppVariable
{
    private $container;
    private $tokenStorage;
    private $requestStack;
    private $environment;
    private $debug;

    /**
     * @deprecated since version 2.7, to be removed in 3.0.
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function setTokenStorage(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function setRequestStack(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function setEnvironment($environment)
    {
        $this->environment = $environment;
    }

    public function setDebug($debug)
    {
        $this->debug = (bool) $debug;
    }

    /**
     * Returns the security context service.
     *
     * @deprecated since version 2.6, to be removed in 3.0.
     *
     * @return SecurityContext|null The security context
     */
    public function getSecurity()
    {
        @trigger_error('The "app.security" variable is deprecated since version 2.6 and will be removed in 3.0.', E_USER_DEPRECATED);

        if (null === $this->container) {
            throw new \RuntimeException('The "app.security" variable is not available.');
        }

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
        if (null === $this->tokenStorage) {
            if (null === $this->container) {
                throw new \RuntimeException('The "app.user" variable is not available.');
            } elseif (!$this->container->has('security.context')) {
                return;
            }

            $this->tokenStorage = $this->container->get('security.context');
        }

        if (!$token = $this->tokenStorage->getToken()) {
            return;
        }

        $user = $token->getUser();
        if (is_object($user)) {
            return $user;
        }
    }

    /**
     * Returns the current request.
     *
     * @return Request|null The HTTP request object
     */
    public function getRequest()
    {
        if (null === $this->requestStack) {
            if (null === $this->container) {
                throw new \RuntimeException('The "app.request" variable is not available.');
            }

            $this->requestStack = $this->container->get('request_stack');
        }

        return $this->requestStack->getCurrentRequest();
    }

    /**
     * Returns the current session.
     *
     * @return Session|null The session
     */
    public function getSession()
    {
        if (null === $this->requestStack && null === $this->container) {
            throw new \RuntimeException('The "app.session" variable is not available.');
        }

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
        if (null === $this->environment) {
            throw new \RuntimeException('The "app.environment" variable is not available.');
        }

        return $this->environment;
    }

    /**
     * Returns the current app debug mode.
     *
     * @return bool The current debug mode
     */
    public function getDebug()
    {
        if (null === $this->debug) {
            throw new \RuntimeException('The "app.debug" variable is not available.');
        }

        return $this->debug;
    }
}
