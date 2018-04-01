<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Authentication;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\RequestStack;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Core\Security;

/**
 * Extracts Security Errors from Request.
 *
 * @author Boris Vujicic <boris.vujicic@gmail.com>
 */
class AuthenticationUtils
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param bool $clearSession
     *
     * @return AuthenticationException|null
     */
    public function getLastAuthenticationError($clearSession = true)
    {
        $request = $this->getRequest();
        $session = $request->getSession();
        $authenticationException = null;

        if ($request->attributes->has(Security::AUTHENTICATION_ERROR)) {
            $authenticationException = $request->attributes->get(Security::AUTHENTICATION_ERROR);
        } elseif (null !== $session && $session->has(Security::AUTHENTICATION_ERROR)) {
            $authenticationException = $session->get(Security::AUTHENTICATION_ERROR);

            if ($clearSession) {
                $session->remove(Security::AUTHENTICATION_ERROR);
            }
        }

        return $authenticationException;
    }

    /**
     * @return string
     */
    public function getLastUsername()
    {
        $request = $this->getRequest();

        if ($request->attributes->has(Security::LAST_USERNAME)) {
            return $request->attributes->get(Security::LAST_USERNAME, '');
        }

        $session = $request->getSession();

        return null === $session ? '' : $session->get(Security::LAST_USERNAME, '');
    }

    /**
     * @throws \LogicException
     */
    private function getRequest(): Request
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \LogicException('Request should exist so it can be processed for error.');
        }

        return $request;
    }
}
