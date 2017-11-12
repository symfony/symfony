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

/**
 * GlobalVariables is the entry point for Symfony global variables in PHP templates.
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
     * @return TokenInterface|null
     */
    public function getToken()
    {
        if (!$this->container->has('security.token_storage')) {
            return;
        }

        return $this->container->get('security.token_storage')->getToken();
    }

    public function getUser(): void
    {
        if (!$token = $this->getToken()) {
            return;
        }

        $user = $token->getUser();
        if (!is_object($user)) {
            return;
        }

        return $user;
    }

    /**
     * @return Request|null The HTTP request object
     */
    public function getRequest(): ?Request
    {
        if ($this->container->has('request_stack')) {
            return $this->container->get('request_stack')->getCurrentRequest();
        }
    }

    /**
     * @return Session|null The session
     */
    public function getSession(): ?Session
    {
        if ($request = $this->getRequest()) {
            return $request->getSession();
        }
    }

    /**
     * @return string The current environment string (e.g 'dev')
     */
    public function getEnvironment(): string
    {
        return $this->container->getParameter('kernel.environment');
    }

    /**
     * @return bool The current debug mode
     */
    public function getDebug(): bool
    {
        return (bool) $this->container->getParameter('kernel.debug');
    }
}
