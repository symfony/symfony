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
 * This authenticator authenticates a remote user.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Maxime Douailin <maxime.douailin@gmail.com>
 *
 * @final
 *
 * @internal in Symfony 5.1
 */
class RemoteUserAuthenticator extends AbstractPreAuthenticatedAuthenticator
{
    private $userKey;

    public function __construct(UserProviderInterface $userProvider, TokenStorageInterface $tokenStorage, string $firewallName, string $userKey = 'REMOTE_USER', ?LoggerInterface $logger = null)
    {
        parent::__construct($userProvider, $tokenStorage, $firewallName, $logger);

        $this->userKey = $userKey;
    }

    protected function extractUsername(Request $request): ?string
    {
        if (!$request->server->has($this->userKey)) {
            throw new BadCredentialsException(sprintf('User key was not found: "%s".', $this->userKey));
        }

        return $request->server->get($this->userKey);
    }
}
