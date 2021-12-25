<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;

/**
 * Extracts Security Errors from Request.
 *
 * @author Boris Vujicic <boris.vujicic@gmail.com>
 */
class AuthenticationUtils
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getLastAuthenticationError(bool $clearSession = true): ?AuthenticationException
    {
        $request = $this->getRequest();
        $authenticationException = null;

        if ($request->attributes->has(Security::AUTHENTICATION_ERROR)) {
            $authenticationException = $request->attributes->get(Security::AUTHENTICATION_ERROR);
        } elseif ($request->hasSession() && ($session = $request->getSession())->has(Security::AUTHENTICATION_ERROR)) {
            $authenticationException = $session->get(Security::AUTHENTICATION_ERROR);

            if ($clearSession) {
                $session->remove(Security::AUTHENTICATION_ERROR);
            }
        }

        return $authenticationException;
    }

    public function getLastUsername(): string
    {
        $request = $this->getRequest();

        if ($request->attributes->has(Security::LAST_USERNAME)) {
            return $request->attributes->get(Security::LAST_USERNAME, '');
        }

        return $request->hasSession() ? $request->getSession()->get(Security::LAST_USERNAME, '') : '';
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
