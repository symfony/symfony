<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\EntryPoint\DigestAuthenticationEntryPoint;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\NonceExpiredException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * DigestAuthenticationListener implements Digest HTTP authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DigestAuthenticationListener implements ListenerInterface
{
    private $securityContext;
    private $provider;
    private $providerKey;
    private $authenticationEntryPoint;
    private $logger;

    public function __construct(SecurityContextInterface $securityContext, UserProviderInterface $provider, $providerKey, DigestAuthenticationEntryPoint $authenticationEntryPoint, LoggerInterface $logger = null)
    {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->securityContext = $securityContext;
        $this->provider = $provider;
        $this->providerKey = $providerKey;
        $this->authenticationEntryPoint = $authenticationEntryPoint;
        $this->logger = $logger;
    }

    /**
     * Handles digest authentication.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if (!$header = $request->server->get('PHP_AUTH_DIGEST')) {
            return;
        }

        $digestAuth = new DigestData($header);

        if (null !== $token = $this->securityContext->getToken()) {
            if ($token instanceof UsernamePasswordToken && $token->isAuthenticated() && $token->getUsername() === $digestAuth->getUsername()) {
                return;
            }
        }

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Digest Authorization header received from user agent: %s', $header));
        }

        try {
            $digestAuth->validateAndDecode($this->authenticationEntryPoint->getKey(), $this->authenticationEntryPoint->getRealmName());
        } catch (BadCredentialsException $e) {
            $this->fail($event, $request, $e);

            return;
        }

        try {
            $user = $this->provider->loadUserByUsername($digestAuth->getUsername());

            if (null === $user) {
                throw new AuthenticationServiceException('AuthenticationDao returned null, which is an interface contract violation');
            }

            $serverDigestMd5 = $digestAuth->calculateServerDigest($user->getPassword(), $request->getMethod());
        } catch (UsernameNotFoundException $notFound) {
            $this->fail($event, $request, new BadCredentialsException(sprintf('Username %s not found.', $digestAuth->getUsername())));

            return;
        }

        if ($serverDigestMd5 !== $digestAuth->getResponse()) {
            if (null !== $this->logger) {
                $this->logger->debug(sprintf("Expected response: '%s' but received: '%s'; is AuthenticationDao returning clear text passwords?", $serverDigestMd5, $digestAuth->getResponse()));
            }

            $this->fail($event, $request, new BadCredentialsException('Incorrect response'));

            return;
        }

        if ($digestAuth->isNonceExpired()) {
            $this->fail($event, $request, new NonceExpiredException('Nonce has expired/timed out.'));

            return;
        }

        if (null !== $this->logger) {
            $this->logger->info(sprintf('Authentication success for user "%s" with response "%s"', $digestAuth->getUsername(), $digestAuth->getResponse()));
        }

        $this->securityContext->setToken(new UsernamePasswordToken($user, $user->getPassword(), $this->providerKey));
    }

    private function fail(GetResponseEvent $event, Request $request, AuthenticationException $authException)
    {
        $this->securityContext->setToken(null);

        if (null !== $this->logger) {
            $this->logger->info($authException);
        }

        $event->setResponse($this->authenticationEntryPoint->start($request, $authException));
    }
}
