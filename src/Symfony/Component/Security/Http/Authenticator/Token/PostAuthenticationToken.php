<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\User\UserInterface;

class PostAuthenticationToken extends AbstractToken
{
    private string $firewallName;

    /**
     * @param string[] $roles An array of roles
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(UserInterface $user, string $firewallName, array $roles)
    {
        parent::__construct($roles);

        if ('' === $firewallName) {
            throw new \InvalidArgumentException('$firewallName must not be empty.');
        }

        $this->setUser($user);
        $this->firewallName = $firewallName;

        // required for compatibility with Symfony 5.4
        if (method_exists($this, 'setAuthenticated')) {
            // this token is meant to be used after authentication success, so it is always authenticated
            $this->setAuthenticated(true, false);
        }
    }

    /**
     * This is meant to be only a token, where credentials
     * have already been used and are thus cleared.
     */
    public function getCredentials(): mixed
    {
        return [];
    }

    public function getFirewallName(): string
    {
        return $this->firewallName;
    }

    public function __serialize(): array
    {
        return [$this->firewallName, parent::__serialize()];
    }

    public function __unserialize(array $data): void
    {
        [$this->firewallName, $parentData] = $data;
        parent::__unserialize($parentData);
    }
}
