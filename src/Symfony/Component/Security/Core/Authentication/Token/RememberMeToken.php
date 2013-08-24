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
 *
 * @since v2.0.0
 */
class RememberMeToken extends AbstractToken
{
    private $key;
    private $providerKey;

    /**
     * Constructor.
     *
     * @param UserInterface $user
     * @param string        $providerKey
     * @param string        $key
     *
     * @throws \InvalidArgumentException
     *
     * @since v2.0.10
     */
    public function __construct(UserInterface $user, $providerKey, $key)
    {
        parent::__construct($user->getRoles());

        if (empty($key)) {
            throw new \InvalidArgumentException('$key must not be empty.');
        }

        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->providerKey = $providerKey;
        $this->key = $key;

        $this->setUser($user);
        parent::setAuthenticated(true);
    }

    /**
     * @since v2.0.0
     */
    public function setAuthenticated($authenticated)
    {
        if ($authenticated) {
            throw new \LogicException('You cannot set this token to authenticated after creation.');
        }

        parent::setAuthenticated(false);
    }

    /**
     * @since v2.0.0
     */
    public function getProviderKey()
    {
        return $this->providerKey;
    }

    /**
     * @since v2.0.0
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @since v2.0.0
     */
    public function getCredentials()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function serialize()
    {
        return serialize(array(
            $this->key,
            $this->providerKey,
            parent::serialize(),
        ));
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function unserialize($serialized)
    {
        list($this->key, $this->providerKey, $parentStr) = unserialize($serialized);
        parent::unserialize($parentStr);
    }
}
