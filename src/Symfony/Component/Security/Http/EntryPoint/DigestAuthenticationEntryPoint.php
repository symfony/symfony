<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EntryPoint;

use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Core\Exception\NonceExpiredException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/**
 * DigestAuthenticationEntryPoint starts an HTTP Digest authentication.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DigestAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    protected $key;
    protected $realmName;
    protected $nonceValiditySeconds;
    protected $logger;

    public function __construct($realmName, $key, $nonceValiditySeconds = 300, LoggerInterface $logger = null)
    {
        $this->realmName = $realmName;
        $this->key = $key;
        $this->nonceValiditySeconds = $nonceValiditySeconds;
        $this->logger = $logger;
    }

    public function start(EventInterface $event, Request $request, AuthenticationException $authException = null)
    {
        $expiryTime = microtime(true) + $this->nonceValiditySeconds * 1000;
        $signatureValue = md5($expiryTime.':'.$this->key);
        $nonceValue = $expiryTime.':'.$signatureValue;
        $nonceValueBase64 = base64_encode($nonceValue);

        $authenticateHeader = sprintf('Digest realm="%s", qop="auth", nonce="%s"', $this->realmName, $nonceValueBase64);

        if ($authException instanceof NonceExpiredException) {
            $authenticateHeader = $authenticateHeader.', stale="true"';
        }

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('WWW-Authenticate header sent to user agent: "%s"', $authenticateHeader));
        }

        $response = new Response();
        $response->headers->set('WWW-Authenticate', $authenticateHeader);
        $response->setStatusCode(401, $authException->getMessage());

        return $response;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getRealmName()
    {
        return $this->realmName;
    }
}
