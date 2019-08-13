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
     * @param string $providerKey
     * @param string $secret      A secret used to make sure the token is created by the app and not by a malicious client
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(UserInterface $user, $providerKey, $secret)
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
    public function serialize()
    {
        $serialized = [$this->secret, $this->providerKey, parent::serialize(true)];

        return $this->doSerialize($serialized, \func_num_args() ? func_get_arg(0) : null);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->secret, $this->providerKey, $parentStr) = \is_array($serialized) ? $serialized : unserialize($serialized);
        parent::unserialize($parentStr);
    }
}
