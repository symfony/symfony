<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Authentication Token for "Remember-Me".
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RememberMeToken extends AbstractToken
{
    private $secret;
    private $providerKey;

    /**
     * @param string $secret A secret used to make sure the token is created by the app and not by a malicious client
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(UserInterface $user, string $providerKey, string $secret)
    {
        parent::__construct($user->getRoles());

        if (empty($secret)) {
            throw new \InvalidArgumentException('$secret must not be empty.');
        }

        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->providerKey = $providerKey;
        $this->secret = $secret;

        $this->setUser($user);
        parent::setAuthenticated(true);
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthenticated($authenticated)
    {
        if ($authenticated) {
            throw new \LogicException('You cannot set this token to authenticated after creation.');
        }

        parent::setAuthenticated(false);
    }

    /**
     * Returns the provider secret.
     *
     * @return string The provider secret
     */
    public function getProviderKey()
    {
        return $this->providerKey;
    }

    /**
     * Returns the secret.
     *
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function __serialize(): array
    {
        return [$this->secret, $this->providerKey, parent::__serialize()];
    }

    /**
     * {@inheritdoc}
     */
    public function __unserialize(array $data): void
    {
        [$this->secret, $this->providerKey, $parentData] = $data;
        $parentData = \is_array($parentData) ? $parentData : unserialize($parentData);
        parent::__unserialize($parentData);
    }
}
