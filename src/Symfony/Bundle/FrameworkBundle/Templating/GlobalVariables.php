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

@trigger_error('The '.GlobalVariables::class.' class is deprecated since version 4.3 and will be removed in 5.0; use Twig instead.', \E_USER_DEPRECATED);

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * GlobalVariables is the entry point for Symfony global variables in PHP templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 4.3, to be removed in 5.0; use Twig instead.
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
            return null;
        }

        return $this->container->get('security.token_storage')->getToken();
    }

    public function getUser()
    {
        if (!$token = $this->getToken()) {
            return null;
        }

        $user = $token->getUser();

        return \is_object($user) ? $user : null;
    }

    /**
     * @return Request|null The HTTP request object
     */
    public function getRequest()
    {
        return $this->container->has('request_stack') ? $this->container->get('request_stack')->getCurrentRequest() : null;
    }

    /**
     * @return Session|null The session
     */
    public function getSession()
    {
        $request = $this->getRequest();

        return $request && $request->hasSession() ? $request->getSession() : null;
    }

    /**
     * @return string The current environment string (e.g 'dev')
     */
    public function getEnvironment()
    {
        return $this->container->getParameter('kernel.environment');
    }

    /**
     * @return bool The current debug mode
     */
    public function getDebug()
    {
        return (bool) $this->container->getParameter('kernel.debug');
    }
}
