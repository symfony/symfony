<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;

/**
 * This class decorates internal authenticators to add the LDAP integration.
 *
 * In your own authenticators, it is recommended to directly use the
 * LdapBadge in the authenticate() method. This class should only be
 * used for Symfony or third party authenticators.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class LdapAuthenticator implements AuthenticatorInterface
{
    private $authenticator;
    private $ldapServiceId;
    private $dnString;
    private $searchDn;
    private $searchPassword;
    private $queryString;

    public function __construct(AuthenticatorInterface $authenticator, string $ldapServiceId, string $dnString = '{username}', string $searchDn = '', string $searchPassword = '', string $queryString = '')
    {
        $this->authenticator = $authenticator;
        $this->ldapServiceId = $ldapServiceId;
        $this->dnString = $dnString;
        $this->searchDn = $searchDn;
        $this->searchPassword = $searchPassword;
        $this->queryString = $queryString;
    }

    public function supports(Request $request): ?bool
    {
        return $this->authenticator->supports($request);
    }

    public function authenticate(Request $request): PassportInterface
    {
        $passport = $this->authenticator->authenticate($request);
        $passport->addBadge(new LdapBadge($this->ldapServiceId, $this->dnString, $this->searchDn, $this->searchPassword, $this->queryString));

        return $passport;
    }

    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        return $this->authenticator->createAuthenticatedToken($passport, $firewallName);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->authenticator->onAuthenticationSuccess($request, $token, $firewallName);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->authenticator->onAuthenticationFailure($request, $exception);
    }
}
