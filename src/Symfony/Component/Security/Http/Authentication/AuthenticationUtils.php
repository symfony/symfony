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
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Extracts Security Errors from Request.
 *
 * @author Boris Vujicic <boris.vujicic@gmail.com>
 */
class AuthenticationUtils
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function getLastAuthenticationError(bool $clearSession = true): ?AuthenticationException
    {
        $request = $this->getRequest();
        $authenticationException = null;

        if ($request->attributes->has(SecurityRequestAttributes::AUTHENTICATION_ERROR)) {
            $authenticationException = $request->attributes->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);
        } elseif ($request->hasSession() && ($session = $request->getSession())->has(SecurityRequestAttributes::AUTHENTICATION_ERROR)) {
            $authenticationException = $session->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);

            if ($clearSession) {
                $session->remove(SecurityRequestAttributes::AUTHENTICATION_ERROR);
            }
        }

        return $authenticationException;
    }

    public function getLastUsername(): string
    {
        $request = $this->getRequest();

        if ($request->attributes->has(SecurityRequestAttributes::LAST_USERNAME)) {
            return $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME) ?? '';
        }

        return $request->hasSession() ? ($request->getSession()->get(SecurityRequestAttributes::LAST_USERNAME) ?? '') : '';
    }

    /**
     * Returns the security attribute the current request is asking a re-authentication for, if it is.
     *
     * Reading it does not end the re-authentication, a successful login does, so that the page
     * still knows what it is asking for when the user mistypes their password.
     */
    public function getReAuthenticationAttribute(): ?string
    {
        $request = $this->getRequest();

        if ($request->attributes->has(SecurityRequestAttributes::RE_AUTHENTICATION_ATTRIBUTE)) {
            return $request->attributes->get(SecurityRequestAttributes::RE_AUTHENTICATION_ATTRIBUTE);
        }

        return $request->hasSession() ? $request->getSession()->get(SecurityRequestAttributes::RE_AUTHENTICATION_ATTRIBUTE) : null;
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
