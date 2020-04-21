<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\RememberMe\AbstractRememberMeServices;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

/**
 * The RememberMe *Authenticator* performs remember me authentication.
 *
 * This authenticator is executed whenever a user's session
 * expired and a remember me cookie was found. This authenticator
 * then "re-authenticates" the user using the information in the
 * cookie.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class RememberMeAuthenticator implements InteractiveAuthenticatorInterface
{
    private $rememberMeServices;
    private $secret;
    private $tokenStorage;
    private $options = [];

    public function __construct(RememberMeServicesInterface $rememberMeServices, string $secret, TokenStorageInterface $tokenStorage, array $options)
    {
        $this->rememberMeServices = $rememberMeServices;
        $this->secret = $secret;
        $this->tokenStorage = $tokenStorage;
        $this->options = $options;
    }

    public function supports(Request $request): ?bool
    {
        // do not overwrite already stored tokens (i.e. from the session)
        if (null !== $this->tokenStorage->getToken()) {
            return false;
        }

        if (($cookie = $request->attributes->get(AbstractRememberMeServices::COOKIE_ATTR_NAME)) && null === $cookie->getValue()) {
            return false;
        }

        if (isset($this->options['name']) && !$request->cookies->has($this->options['name'])) {
            return false;
        }

        // the `null` return value indicates that this authenticator supports lazy firewalls
        return null;
    }

    public function authenticate(Request $request): PassportInterface
    {
        $token = $this->rememberMeServices->autoLogin($request);

        return new SelfValidatingPassport($token->getUser());
    }

    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        return new RememberMeToken($passport->getUser(), $firewallName, $this->secret);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // let the original request continue
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->rememberMeServices->loginFail($request, $exception);

        return null;
    }

    public function isInteractive(): bool
    {
        return true;
    }
}
