<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\User\UserInterface;

trigger_deprecation('symfony/security-guard', '5.3', 'The "%s" class is deprecated, use the new authenticator system instead.', PostAuthenticationGuardToken::class);

/**
 * Used as an "authenticated" token, though it could be set to not-authenticated later.
 *
 * If you're using Guard authentication, you *must* use a class that implements
 * GuardTokenInterface as your authenticated token (like this class).
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @deprecated since Symfony 5.3, use the new authenticator system instead
 */
class PostAuthenticationGuardToken extends AbstractToken implements GuardTokenInterface
{
    private $providerKey;

    /**
     * @param string   $providerKey The provider (firewall) key
     * @param string[] $roles       An array of roles
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(UserInterface $user, string $providerKey, array $roles)
    {
        parent::__construct($roles);

        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey (i.e. firewall key) must not be empty.');
        }

        $this->setUser($user);
        $this->providerKey = $providerKey;

        // this token is meant to be used after authentication success, so it is always authenticated
        // you could set it as non authenticated later if you need to
        $this->setAuthenticated(true);
    }

    /**
     * This is meant to be only an authenticated token, where credentials
     * have already been used and are thus cleared.
     *
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        return [];
    }

    /**
     * Returns the provider (firewall) key.
     *
     * @return string
     */
    public function getProviderKey()
    {
        return $this->providerKey;
    }

    public function getFirewallName(): string
    {
        return $this->getProviderKey();
    }

    /**
     * {@inheritdoc}
     */
    public function __serialize(): array
    {
        return [$this->providerKey, parent::__serialize()];
    }

    /**
     * {@inheritdoc}
     */
    public function __unserialize(array $data): void
    {
        [$this->providerKey, $parentData] = $data;
        $parentData = \is_array($parentData) ? $parentData : unserialize($parentData);
        parent::__unserialize($parentData);
    }
}
