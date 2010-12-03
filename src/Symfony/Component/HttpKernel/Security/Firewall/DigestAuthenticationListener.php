<?php

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\Security\SecurityContext;
use Symfony\Component\Security\User\UserProviderInterface;
use Symfony\Component\HttpKernel\Security\EntryPoint\DigestAuthenticationEntryPoint;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Exception\BadCredentialsException;
use Symfony\Component\Security\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Exception\UsernameNotFoundException;
use Symfony\Component\HttpKernel\Security\EntryPoint\NonceExpiredException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Exception\AuthenticationException;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DigestAuthenticationListener implements Digest HTTP authentication.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DigestAuthenticationListener implements ListenerInterface
{
    protected $securityContext;
    protected $provider;
    protected $authenticationEntryPoint;
    protected $logger;

    public function __construct(SecurityContext $securityContext, UserProviderInterface $provider, DigestAuthenticationEntryPoint $authenticationEntryPoint, LoggerInterface $logger = null)
    {
        $this->securityContext = $securityContext;
        $this->provider = $provider;
        $this->authenticationEntryPoint = $authenticationEntryPoint;
        $this->logger = $logger;
    }

    /**
     * 
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('core.security', array($this, 'handle'), 0);
    }
    
    /**
     * {@inheritDoc}
     */
    public function unregister(EventDispatcher $dispatcher)
    {
    }

    /**
     * Handles digest authentication.
     *
     * @param Event $event An Event instance
     */
    public function handle(Event $event)
    {
        $request = $event->get('request');

        if (!$header = $request->server->get('PHP_AUTH_DIGEST')) {
            return;
        }

        if (null !== $token = $this->securityContext->getToken()) {
            if ($token->isImmutable()) {
                return;
            }

            if ($token instanceof UsernamePasswordToken && $token->isAuthenticated() && (string) $token === $username) {
                return;
            }
        }

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Digest Authorization header received from user agent: %s', $header));
        }

        $digestAuth = new DigestData($header);

        try {
            $digestAuth->validateAndDecode($this->authenticationEntryPoint->getKey(), $this->authenticationEntryPoint->getRealmName());
        } catch (BadCredentialsException $e) {
            $this->fail($request, $e);

            return;
        }

        try {
            $user = $this->provider->loadUserByUsername($digestAuth->getUsername());

            if (null === $user) {
                throw new AuthenticationServiceException('AuthenticationDao returned null, which is an interface contract violation');
            }

            $serverDigestMd5 = $digestAuth->calculateServerDigest($user->getPassword(), $request->getMethod());
        } catch (UsernameNotFoundException $notFound) {
            $this->fail($request, new BadCredentialsException(sprintf('Username %s not found.', $digestAuth->getUsername())));

            return;
        }

        if ($serverDigestMd5 !== $digestAuth->getResponse()) {
            if (null !== $this->logger) {
                $this->logger->debug(sprintf("Expected response: '%s' but received: '%s'; is AuthenticationDao returning clear text passwords?", $serverDigestMd5, $digestAuth->getResponse()));
            }

            $this->fail($request, new BadCredentialsException('Incorrect response'));

            return;
        }

        if ($digestAuth->isNonceExpired()) {
            $this->fail($request, new NonceExpiredException('Nonce has expired/timed out.'));

            return;
        }

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Authentication success for user "%s" with response "%s"', $digestAuth->getUsername(), $digestAuth->getResponse()));
        }

        $this->securityContext->setToken(new UsernamePasswordToken($user, $user->getPassword()));
    }

    protected function fail(Request $request, AuthenticationException $failed)
    {
        $this->securityContext->setToken(null);

        if (null !== $this->logger) {
            $this->logger->debug($failed);
        }

        $this->authenticationEntryPoint->start($request, $failed);
    }
}

class DigestData
{
    protected $elements;
    protected $header;
    protected $nonceExpiryTime;

    public function __construct($header)
    {
        $this->header = $header;
        $parts = preg_split('/, /', $header);
        $this->elements = array();
        foreach ($parts as $part) {
            list($key, $value) = explode('=', $part);
            $this->elements[$key] = '"' === $value[0] ? substr($value, 1, -1) : $value;
        }
    }

    public function getResponse()
    {
        return $this->elements['response'];
    }

    public function getUsername()
    {
        return $this->elements['username'];
    }

    public function validateAndDecode($entryPointKey, $expectedRealm)
    {
        if ($keys = array_diff(array('username', 'realm', 'nonce', 'uri', 'response'), array_keys($this->elements))) {
            throw new BadCredentialsException(sprintf('Missing mandatory digest value; received header "%s" (%s)', $this->header, implode(', ', $keys)));
        }

        if ('auth' === $this->elements['qop']) {
            if (!isset($this->elements['nc']) || !isset($this->elements['cnonce'])) {
                throw new BadCredentialsException(sprintf('Missing mandatory digest value; received header "%s"', $this->header));
            }
        }

        if ($expectedRealm !== $this->elements['realm']) {
            throw new BadCredentialsException(sprintf('Response realm name "%s" does not match system realm name of "%s".', $this->elements['realm'], $expectedRealm));
        }

        if (false === $nonceAsPlainText = base64_decode($this->elements['nonce'])) {
            throw new BadCredentialsException(sprintf('Nonce is not encoded in Base64; received nonce "%s".', $this->elements['nonce']));
        }

        $nonceTokens = explode(':', $nonceAsPlainText);

        if (2 !== count($nonceTokens)) {
            throw new BadCredentialsException(sprintf('Nonce should have yielded two tokens but was "%s".', $nonceAsPlainText));
        }

        $this->nonceExpiryTime = $nonceTokens[0];

        if (md5($this->nonceExpiryTime.':'.$entryPointKey) !== $nonceTokens[1]) {
            new BadCredentialsException(sprintf('Nonce token compromised "%s".', $nonceAsPlainText));
        }
    }

    public function calculateServerDigest($password, $httpMethod)
    {
        $a2Md5 = md5(strtoupper($httpMethod).':'.$this->elements['uri']);
        $a1Md5 = md5($this->elements['username'].':'.$this->elements['realm'].':'.$password);

        $digest = $a1Md5.':'.$this->elements['nonce'];
        if (!isset($this->elements['qop'])) {
        } elseif ('auth' === $this->elements['qop']) {
            $digest .= ':'.$this->elements['nc'].':'.$this->elements['cnonce'].':'.$this->elements['qop'];
        } else {
            throw new \InvalidArgumentException('This method does not support a qop: "%s".', $this->elements['qop']);
        }
        $digest .= ':'.$a2Md5;

        return md5($digest);
    }

    public function isNonceExpired()
    {
        return $this->nonceExpiryTime < microtime(true);
    }
}
