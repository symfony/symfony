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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * This authenticator authenticates pre-authenticated (by the
 * webserver) X.509 certificates.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 * @experimental in 5.2
 */
class X509Authenticator extends AbstractPreAuthenticatedAuthenticator
{
    private $userKey;
    private $credentialsKey;

    public function __construct(UserProviderInterface $userProvider, TokenStorageInterface $tokenStorage, string $firewallName, string $userKey = 'SSL_CLIENT_S_DN_Email', string $credentialsKey = 'SSL_CLIENT_S_DN', ?LoggerInterface $logger = null)
    {
        parent::__construct($userProvider, $tokenStorage, $firewallName, $logger);

        $this->userKey = $userKey;
        $this->credentialsKey = $credentialsKey;
    }

    protected function extractUsername(Request $request): string
    {
        $username = null;
        if ($request->server->has($this->userKey)) {
            $username = $request->server->get($this->userKey);
        } elseif (
            $request->server->has($this->credentialsKey)
            && preg_match('#emailAddress=([^,/@]++@[^,/]++)#', $request->server->get($this->credentialsKey), $matches)
        ) {
            $username = $matches[1];
        }

        if (null === $username) {
            throw new BadCredentialsException(sprintf('SSL credentials not found: %s, %s', $this->userKey, $this->credentialsKey));
        }

        return $username;
    }
}
